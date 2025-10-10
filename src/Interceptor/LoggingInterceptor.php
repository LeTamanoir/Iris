<?php

declare(strict_types=1);

namespace Iris\Interceptor;

use Fiber;
use Iris\Code;
use Iris\Interceptor;
use Iris\UnaryCall;

/**
 * LoggingInterceptor logs gRPC call information including method, duration, and status.
 */
class LoggingInterceptor extends Interceptor
{
    public function __construct(
        private readonly \Psr\Log\LoggerInterface $logger,
    ) {}

    /**
     * @param callable(UnaryCall): UnaryCall $invoker
     */
    #[\Override]
    public function interceptUnary(UnaryCall $call, callable $invoker): UnaryCall
    {
        $this->logger->info('gRPC call started', [
            'method' => $call->method,
            'request' => get_class($call->args),
            // 'call_id' => $ctx->id, TODO: add call id
        ]);

        $start = hrtime(true) / 1e9;
        $call = $invoker($call);
        $duration = (hrtime(true) / 1e9) - $start;

        if ($call->code !== Code::OK) {
            $this->logger->error('gRPC call failed', [
                'method' => $call->method,
                'duration' => $duration,
                'code' => $call->code->name,
                'message' => $call->message,
                // 'call_id' => $ctx->id, TODO: add call id
            ]);
        } else {
            $this->logger->info('gRPC call completed', [
                'code' => $call->code->name,
                'method' => $call->method,
                'duration' => $duration,
                // @mago-ignore analysis:missing-magic-method
                'reply' => get_class($call->data),
                // 'call_id' => $ctx->id, TODO: add call id
            ]);
        }

        return $call;
    }
}
