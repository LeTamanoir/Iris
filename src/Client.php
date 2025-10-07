<?php

declare(strict_types=1);

namespace Iris;

use CurlHandle;
use Exception;
use Google\Protobuf\Internal\Message;
use InvalidArgumentException;

class Client
{
    /**
     * The pool of curl handles.
     */
    private HandlePool $pool;

    /**
     * Context for the next call.
     */
    private CallCtx $pendingCtx;

    public function __construct(
        public string $host,
    ) {
        $this->pool = new HandlePool(1);
        $this->pendingCtx = new CallCtx();
    }

    /**
     * When cloning, retain the shared handle pool,
     * but deep clone the pending context to ensure isolation.
     */
    public function __clone()
    {
        $this->pendingCtx = clone $this->pendingCtx;
    }

    /**
     * Returns a new client with the given interceptors.
     */
    public function interceptors(Interceptor ...$interceptors): static
    {
        $clone = clone $this;
        $clone->pendingCtx->interceptors = array_merge($clone->pendingCtx->interceptors, $interceptors);
        return $clone;
    }

    /**
     * Returns a new client with the given timeout.
     */
    public function timeout(int $ms): static
    {
        $clone = clone $this;
        $clone->pendingCtx->curlOpts[CURLOPT_TIMEOUT_MS] = $ms;
        return $clone;
    }

    /**
     * Returns a new client with the given curl options.
     *
     * @param  array<int, mixed>  $curlOpts
     */
    public function curlOpts(array $curlOpts): static
    {
        $clone = clone $this;
        $clone->pendingCtx->curlOpts = $curlOpts;
        return $clone;
    }

    /**
     * Returns a new client with the given encoding.
     */
    public function encoding(Encoding $enc): static
    {
        $clone = clone $this;
        $clone->pendingCtx->enc = $enc;
        return $clone;
    }

    /**
     * Returns a new client with the given metadata.
     *
     * @param  array<string, string>  $meta
     */
    public function meta(array $meta): static
    {
        $clone = clone $this;
        $clone->pendingCtx->meta = $meta;
        return $clone;
    }

    /**
     * Performs the actual gRPC call with interceptors.
     */
    public function invoke(string $method, Message $args, UnaryCall $reply): UnaryCall
    {
        $invoker = fn(CallCtx $ctx, UnaryCall $reply) => $this->invokeUnary($ctx, $reply);

        foreach (array_reverse($this->pendingCtx->interceptors) as $i) {
            $next = $invoker;
            $invoker = fn(CallCtx $ctx, UnaryCall $reply) => $i->interceptUnary($ctx, $reply, $next);
        }

        $ctx = $this->pendingCtx;

        // @mago-ignore analysis:unhandled-thrown-type
        $ctx->id = bin2hex(random_bytes(16));
        $ctx->method = $method;
        $ctx->args = $args;

        return $invoker($ctx, $reply);
    }

    /**
     * Performs the actual gRPC call without interceptors.
     *
     * @throws InvalidArgumentException if the message could not be encoded
     */
    private function invokeUnary(CallCtx $ctx, UnaryCall $reply): UnaryCall
    {
        $msg = $this->prepareMsg($ctx->args, $ctx->enc);

        /** @var array<string, string> */
        $replyHdr = [];
        $ch = $this->setupHandle($ctx, $ctx->method, $msg, $replyHdr);

        /** @var string|false */
        $rawReply = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);

        /** @var array<string, mixed> */
        $curlInfo = curl_getinfo($ch);
        $reply->curlInfo = $curlInfo;

        $this->pool->release($ch);

        if ($rawReply === false) {
            $reply->message = $error;
            $reply->code = match ($errno) {
                CURLE_OPERATION_TIMEDOUT => Code::DeadlineExceeded,
                CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_RESOLVE_PROXY => Code::Unavailable,
                default => Code::Unknown,
            };
        } else {
            $this->decodeReply($rawReply, $replyHdr, $reply);
        }

        return $reply;
    }

    /**
     * @param  array<string, string>  $replyHdr
     */
    private function setupHandle(CallCtx $ctx, string $method, string $msg, array &$replyHdr): CurlHandle
    {
        $ch = $this->pool->aquire();

        $headers = [
            'content-type: application/grpc',
            'user-agent: ' . $ctx->userAgent,
            'te: trailers',
            'grpc-encoding: ' . $ctx->enc->value,
            'grpc-accept-encoding: ' . Encoding::list(),
        ];

        $handleHdr = static function (\CurlHandle $_, string $h) use (&$replyHdr) {
            $l = trim($h);
            if ($l !== '') {
                $p = explode(':', $l, 2);
                $replyHdr[trim($p[0])] = trim($p[1] ?? '');
            }
            return strlen($h);
        };

        /** @var mixed $v silence linter */
        foreach ($ctx->curlOpts as $k => $v) {
            curl_setopt($ch, $k, $v);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->host . $method,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_PRIOR_KNOWLEDGE,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $msg,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => $handleHdr,
        ]);

        return $ch;
    }

    /**
     * Encode a message with gRPC 5-byte framing header.
     * Format: 1 byte Compressed-Flag + 4 bytes Message-Length (big-endian)
     *
     * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md#requests
     *
     * @throws InvalidArgumentException if failed to encode gzip message
     */
    private function prepareMsg(Message $args, Encoding $enc): string
    {
        $binary = $args->serializeToString();

        if ($enc === Encoding::Identity) {
            $cFlag = 0;
            $data = $binary;
        } else {
            $cFlag = 1;
            $data = gzencode($binary, 6) ?: throw new InvalidArgumentException('Failed to encode gzip message');
        }

        $header = pack('CN', $cFlag, strlen($data));
        return $header . $data;
    }

    /**
     * Decode gRPC message by stripping 5-byte framing header and decompressing if needed.
     *
     * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md#requests
     *
     * @throws Exception if failed to decode message
     */
    private function decodeMsg(string $msg, null|Encoding $enc): string
    {
        $cFlag = unpack('C', $msg[0])[1];
        $data = substr($msg, 5);

        // No compression
        if ($cFlag === 0) {
            return $data;
        }

        return match ($enc) {
            null => throw new Exception('Message is compressed but no encoding specified'),
            Encoding::Identity => throw new Exception('Message is compressed but encoding is identity'),
            Encoding::Gzip => gzdecode($data) ?: throw new Exception('Failed to decode gzip message'),
        };
    }

    /**
     * Decode a gRPC reply message.
     *
     * @param  array<string, string>  $replyHdr
     */
    private function decodeReply(string $rawReply, array $replyHdr, UnaryCall $reply): void
    {
        if (($replyHdr['content-type'] ?? '') !== 'application/grpc') {
            $reply->code = Code::Unknown;
            $reply->message = 'Invalid content-type: ' . $replyHdr['content-type'];
            return;
        }

        $code = Code::tryFrom((int) ($replyHdr['grpc-status'] ?? ''));
        if ($code === null) {
            $reply->code = Code::Unknown;
            $reply->message = 'Unknown grpc-status code: ' . $replyHdr['grpc-status'];
            return;
        }
        if ($code !== Code::OK) {
            $reply->code = $code;
            $reply->message = $replyHdr['grpc-message'] ?? 'Unknown error';
            return;
        }

        $enc = Encoding::tryFrom($replyHdr['grpc-encoding'] ?? '');

        try {
            // @mago-ignore analysis:missing-magic-method
            $reply->data->mergeFromString($this->decodeMsg($rawReply, $enc));
        } catch (\Throwable $e) {
            $reply->code = Code::Internal;
            $reply->message = $e->getMessage();
            return;
        }

        $reply->code = Code::OK;
        $reply->message = $replyHdr['grpc-message'] ?? '';
    }
}
