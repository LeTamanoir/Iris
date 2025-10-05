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
     * @param callable(string,Message,Message,CallOption...): null|Error $invoker
     */
    abstract public function intercept(
        string $method,
        Message $args,
        Message $reply,
        callable $invoker,
        CallOption ...$opts,
    ): null|Error;
}
