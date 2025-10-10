<?php

declare(strict_types=1);

namespace Iris\Internal;

use CurlMultiHandle;
use Fiber;
use InvalidArgumentException;

class EventLoop
{
    /**
     * @var PendingFiber[]
     */
    private array $pendingFibers = [];

    /**
     * Run the event loop.
     */
    public function run(CurlMultiHandle $mh): void
    {
        while (true) {
            $status = curl_multi_exec($mh, $unfinishedHandles);

            if ($status !== CURLM_OK) {
                // TODO fail all calls with this error
                dd(curl_multi_strerror(curl_multi_errno($mh)));
            }

            // Handle completed cURL requests
            while (($info = curl_multi_info_read($mh)) !== false) {
                if ($info['msg'] !== CURLMSG_DONE) {
                    continue;
                }

                /**
                 * @var Fiber
                 */
                $fiber = curl_getinfo($info['handle'], CURLINFO_PRIVATE);

                $this->resume($fiber);
            }

            if ($unfinishedHandles === 0 && count($this->pendingFibers) === 0) {
                break;
            }

            // If there are still active handles, wait for activity on them before proceeding
            if ($unfinishedHandles) {
                curl_multi_select($mh);
            }
            // When no handles are running, check and resume any pending fibers.
            // This is intentionally done after processing handles for optimal performance.
            else if (count($this->pendingFibers) > 0) {
                $now = hrtime(true) / 1e9;
                $next = 0.0;

                foreach ($this->pendingFibers as $idx => $pf) {
                    if ($pf->resumeAt <= $now) {
                        unset($this->pendingFibers[$idx]);
                        $this->resume($pf->fiber);
                        continue;
                    }
                    $next = max($next, $pf->resumeAt);
                }

                if ($next > 0) {
                    usleep((int) (($next - $now) * 1_000_000));
                }
            }
        }
    }

    /**
     * Resumes a fiber and adds it to the pending fibers list if it needs to be resumed later.
     */
    public function resume(Fiber $fiber): void
    {
        if (!$fiber->isSuspended()) {
            dd('[EVENT LOOP] fiber is not suspended');
        }

        $t = $fiber->resume();

        if (is_float($t) && $t > 0.0) {
            $this->pendingFibers[] = new PendingFiber(
                fiber: $fiber,
                resumeAt: (hrtime(true) / 1e9) + $t,
            );
            return;
        }

        if ($t !== null) {
            throw new InvalidArgumentException('Fiber resumed with invalid return value');
        }
    }
}
