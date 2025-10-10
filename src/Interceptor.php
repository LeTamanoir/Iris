<?php

declare(strict_types=1);

namespace Iris;

use Fiber;

/**
 * Interceptor allows you to intercept and modify gRPC calls.
 * Interceptors form a chain where each interceptor can call the next one.
 */
abstract class Interceptor
{
    /**
     * @param callable(UnaryCall): UnaryCall $invoker
     */
    public function interceptUnary(UnaryCall $call, callable $invoker): UnaryCall
    {
        return $invoker($call);
    }

    /**
     * Suspends the current interceptor for the given number of seconds.
     */
    public function sleep(float $seconds): void
    {
        Fiber::suspend($seconds);
    }
}
