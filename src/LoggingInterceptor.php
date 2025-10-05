<?php

declare(strict_types=1);

namespace Iris;

use Google\Protobuf\Internal\Message;

/**
 * LoggingInterceptor logs gRPC call information including method, duration, and status.
 */
class LoggingInterceptor extends Interceptor
{
    public function __construct(
        private readonly \Psr\Log\LoggerInterface $logger,
    ) {}

    public function intercept(
        string $method,
        Message $args,
        Message $reply,
        callable $invoker,
        CallOption ...$opts,
    ): null|Error {
        $start = microtime(true);

        $this->logger->info('gRPC call started', [
            'method' => $method,
            'request' => get_class($args),
        ]);

        $result = $invoker($method, $args, $reply, ...$opts);

        $duration = microtime(true) - $start;

        if ($result instanceof Error) {
            $this->logger->error('gRPC call failed', [
                'method' => $method,
                'duration' => $duration,
                'code' => $result->code->name,
                'message' => $result->message,
            ]);
        } else {
            $this->logger->info('gRPC call completed', [
                'method' => $method,
                'duration' => $duration,
                'reply' => get_class($reply),
            ]);
        }

        return $result;
    }
}
