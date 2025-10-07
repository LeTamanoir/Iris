<?php

declare(strict_types=1);

namespace Iris\Interceptor;

use Iris\CallCtx;
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
     * @param callable(CallCtx,UnaryCall): UnaryCall $invoker
     */
    #[\Override]
    public function interceptUnary(CallCtx $ctx, UnaryCall $reply, callable $invoker): UnaryCall
    {
        $this->logger->info('gRPC call started', [
            'method' => $ctx->method,
            'request' => get_class($ctx->args),
            'call_id' => $ctx->id,
        ]);

        $start = microtime(true);
        $call = $invoker($ctx, $reply);
        $duration = microtime(true) - $start;

        if ($call->code !== Code::OK) {
            $this->logger->error('gRPC call failed', [
                'method' => $ctx->method,
                'duration' => $duration,
                'code' => $call->code->name,
                'message' => $call->message,
                'call_id' => $ctx->id,
            ]);
        } else {
            $this->logger->info('gRPC call completed', [
                'code' => $call->code->name,
                'method' => $ctx->method,
                'duration' => $duration,
                // @mago-ignore analysis:missing-magic-method
                'reply' => get_class($reply->data),
                'call_id' => $ctx->id,
            ]);
        }

        return $call;
    }
}
