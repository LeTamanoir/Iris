<?php

declare(strict_types=1);

namespace Iris;

use Google\Protobuf\Internal\Message;

/**
 * Interceptor allows you to intercept and modify gRPC calls.
 * Interceptors form a chain where each interceptor can call the next one.
 */
abstract class Interceptor
{
    /**
     * @param callable(CallCtx,Message): UnaryCall $invoker
     */
    public function interceptUnary(CallCtx $ctx, Message $reply, callable $invoker): UnaryCall
    {
        return $invoker($ctx, $reply);
    }
}
