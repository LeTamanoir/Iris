<?php

declare(strict_types=1);

namespace Iris\Interceptor;

use Fiber;
use Iris\Interceptor;
use Iris\UnaryCall;

/**
 * ThrottleInterceptor limits the number of simultaneously executing requests.
 */
class ThrottleInterceptor extends Interceptor
{
    private int $activeCount = 0;

    /**
     * @param int $maxConcurrent Maximum number of concurrent requests
     */
    public function __construct(
        private readonly int $maxConcurrent = 10,
    ) {
        if ($this->maxConcurrent < 1) {
            throw new \InvalidArgumentException('Max concurrent must be at least 1');
        }
    }

    /**
     * @param callable(UnaryCall): UnaryCall $invoker
     */
    #[\Override]
    public function interceptUnary(UnaryCall $call, callable $invoker): UnaryCall
    {
        // Wait until we have an available slot
        while ($this->activeCount >= $this->maxConcurrent) {
            // Suspend this fiber with a short delay to allow other fibers to progress
            // The event loop will resume this fiber after the delay
            $this->sleep(0.0001);
        }

        // Acquire a slot
        $this->activeCount++;

        try {
            // Execute the actual request
            $result = $invoker($call);
            return $result;
        } finally {
            // Release the slot
            $this->activeCount--;
        }
    }
}
