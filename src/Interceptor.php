<?php

declare(strict_types=1);

namespace Iris;

/**
 * Interceptor allows you to intercept and modify gRPC calls.
 * Interceptors form a chain where each interceptor can call the next one.
 */
abstract class Interceptor
{
    /**
     * @param callable(CallCtx,UnaryCall): UnaryCall $invoker
     */
    public function interceptUnary(CallCtx $ctx, UnaryCall $reply, callable $invoker): UnaryCall
    {
        return $invoker($ctx, $reply);
    }
}
