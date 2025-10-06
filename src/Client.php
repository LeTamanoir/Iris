<?php

declare(strict_types=1);

namespace Iris;

use CurlHandle;
use Google\Protobuf\Internal\Message;

class Client
{
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
     * Returns a new client with the given interceptors.
     */
    public function interceptors(Interceptor ...$interceptors): static
    {
        $clone = clone $this;
        $clone->pendingCtx->interceptors = $interceptors;
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
    public function invoke(string $method, Message $args, Message $reply, Interceptor ...$interceptors): UnaryCall
    {
        $invoker = fn(CallCtx $ctx, Message $reply) => $this->invokeUnary($ctx, $reply);

        foreach (array_reverse(array_merge($this->pendingCtx->interceptors, $interceptors)) as $i) {
            $next = $invoker;
            $invoker = fn(CallCtx $ctx, Message $reply) => $i->interceptUnary($ctx, $reply, $next);
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
     */
    private function invokeUnary(CallCtx $ctx, Message $reply): UnaryCall
    {
        $msg = $this->prepareMsg($ctx->args, $ctx->enc);
        if ($msg instanceof UnaryCall) {
            return $msg;
        }

        /** @var array<string, string> */
        $replyHdr = [];
        $ch = $this->setupHandle($ctx, $ctx->method, $msg, $replyHdr);

        /** @var string|false */
        $rawReply = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);

        /** @var array<string, mixed> */
        $curlInfo = curl_getinfo($ch);

        $this->pool->release($ch);

        if ($rawReply === false) {
            $code = match ($errno) {
                CURLE_OPERATION_TIMEDOUT => Code::DeadlineExceeded,
                CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_RESOLVE_PROXY => Code::Unavailable,
                default => throw new \Exception('Unknown curl error (errno: ' . $errno . ', error: ' . $error . ')'),
            };
            $result = new UnaryCall($code, $error, $curlInfo);
        } else {
            $result = $this->decodeReply($rawReply, $replyHdr, $reply);
        }

        $result->curlInfo = $curlInfo;

        return $result;
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
     */
    private function prepareMsg(Message $args, Encoding $enc): string|UnaryCall
    {
        $binary = $args->serializeToString();

        if ($enc === Encoding::Identity) {
            $cFlag = 0;
            $data = $binary;
        } else {
            $cFlag = 1;
            $data = match ($enc) {
                Encoding::Gzip => gzencode($binary, 6) ?: new UnaryCall(Code::Unknown, 'Failed to encode gzip message'),
            };
        }

        if ($data instanceof UnaryCall) {
            return $data;
        }

        $header = pack('CN', $cFlag, strlen($data));
        return $header . $data;
    }

    /**
     * Decode gRPC message by stripping 5-byte framing header and decompressing if needed.
     *
     * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md#requests
     */
    private function decodeMsg(string $msg, null|Encoding $enc): string|UnaryCall
    {
        $cFlag = unpack('C', $msg[0])[1];
        $data = substr($msg, 5);

        // No compression
        if ($cFlag === 0) {
            return $data;
        }

        if ($enc === null) {
            return new UnaryCall(Code::Unknown, 'Message is compressed but no encoding specified');
        }
        return match ($enc) {
            Encoding::Identity => new UnaryCall(Code::Unknown, 'Message is compressed but encoding is identity'),
            Encoding::Gzip => gzdecode($data) ?: new UnaryCall(Code::Unknown, 'Failed to decode gzip message'),
        };
    }

    /**
     * Decode a gRPC reply message.
     *
     * @param  array<string, string>  $replyHdr
     */
    private function decodeReply(string $rawReply, array $replyHdr, Message $reply): UnaryCall
    {
        if (($replyHdr['content-type'] ?? '') !== 'application/grpc') {
            return new UnaryCall(Code::Unknown, 'Invalid content-type: ' . $replyHdr['content-type']);
        }

        $code = Code::tryFrom((int) ($replyHdr['grpc-status'] ?? ''));
        if ($code === null) {
            return new UnaryCall(Code::Unknown, 'Unknown grpc-status code: ' . $replyHdr['grpc-status']);
        }
        if ($code !== Code::OK) {
            return new UnaryCall($code, $replyHdr['grpc-message'] ?? 'Unknown error');
        }

        $enc = Encoding::tryFrom($replyHdr['grpc-encoding'] ?? '');
        $msg = $this->decodeMsg($rawReply, $enc);
        if ($msg instanceof UnaryCall) {
            return $msg;
        }

        try {
            $reply->mergeFromString($msg);
        } catch (\Throwable $e) {
            return new UnaryCall(Code::Internal, $e->getMessage());
        }

        return new UnaryCall(Code::OK, $replyHdr['grpc-message'] ?? '');
    }
}
