<?php

declare(strict_types=1);

namespace Iris\Interceptor;

use Google\Protobuf\Internal\Message;
use Iris\CallOption;
use Iris\Code;
use Iris\Error;
use Iris\Interceptor;

/**
 * RetryInterceptor automatically retries failed gRPC calls with exponential backoff.
 */
class RetryInterceptor extends Interceptor
{
    /**
     * @param Code[] $retryableCodes
     */
    public function __construct(
        private readonly int $maxAttempts = 3,
        private readonly int $delayMs = 100,
        private readonly float $multiplier = 2.0,
        private readonly array $retryableCodes = [Code::Unavailable, Code::Aborted, Code::DeadlineExceeded],
    ) {}

    /**
     * @param callable(string,Message,Message,CallOption...): null|Error $invoker
     */
    public function intercept(
        string $method,
        Message $args,
        Message $reply,
        callable $invoker,
        CallOption ...$opts,
    ): null|Error {
        $attempt = 0;
        $result = null;

        while ($attempt < $this->maxAttempts) {
            $attempt++;

            $result = $invoker($method, $args, $reply, ...$opts);

            if ($result === null) {
                return null;
            }

            // Check if we should retry this error
            if (!$this->isRetryable($result->code)) {
                return $result;
            }

            // Sleep before the next attempt
            if ($attempt < $this->maxAttempts) {
                // exponential backoff: initialDelay * multiplier^(attempt-1)
                $baseDelay = (int) ($this->delayMs * ($this->multiplier ** ($attempt - 1)));
                // @mago-ignore analysis:possibly-invalid-argument
                usleep($baseDelay * 1000);
            }
        }

        return $result;
    }

    private function isRetryable(Code $code): bool
    {
        foreach ($this->retryableCodes as $c) {
            if ($c === $code) {
                return true;
            }
        }
        return false;
    }
}
