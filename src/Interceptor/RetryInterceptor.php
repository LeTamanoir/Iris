<?php

declare(strict_types=1);

namespace Iris\Interceptor;

use Fiber;
use Iris\Code;
use Iris\Interceptor;
use Iris\UnaryCall;

/**
 * RetryInterceptor automatically retries failed gRPC calls with exponential backoff.
 */
class RetryInterceptor extends Interceptor
{
    /**
     * @param int $maxAttempts Max number of attempts
     * @param int $delayMs Initial delay in milliseconds
     * @param float $multiplier Multiplier for the delay `delayMs * multiplier**(attempt-1)`
     * @param Code[] $retryableCodes Codes that are retryable
     */
    public function __construct(
        private readonly int $maxAttempts = 3,
        private readonly int $delayMs = 100,
        private readonly float $multiplier = 2.0,
        private readonly array $retryableCodes = [Code::Unavailable, Code::Aborted, Code::DeadlineExceeded],
    ) {
        if ($this->maxAttempts < 1) {
            throw new \InvalidArgumentException('Max attempts must be at least 1');
        }
        if ($this->delayMs < 0) {
            throw new \InvalidArgumentException('Delay must be at least 0');
        }
        if ($this->multiplier <= 0.0) {
            throw new \InvalidArgumentException('Multiplier must be greater than 0');
        }
    }

    /**
     * @param callable(UnaryCall): UnaryCall $invoker
     */
    #[\Override]
    public function interceptUnary(UnaryCall $call, callable $invoker): UnaryCall
    {
        $attempt = 0;
        $result = null;

        do {
            $attempt++;

            $result = $invoker($call);

            // Check if we should retry this error
            if ($result->code === Code::OK || !$this->isRetryable($result->code)) {
                return $result;
            }

            // Sleep before the next attempt using exponential backoff
            $delay = ($this->delayMs * ($this->multiplier ** ($attempt - 1))) / 1_000;
            $this->sleep($delay);
        } while ($attempt < $this->maxAttempts);

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
