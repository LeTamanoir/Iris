<?php

declare(strict_types=1);

namespace Iris;

use CurlHandle;
use Exception;
use Google\Protobuf\Internal\Message;
use InvalidArgumentException;
use ReflectionProperty;

// TODO: Add global call options merging
class Connection
{
    /**
     * The pool of curl handles.
     */
    private HandlePool $pool;

    public function __construct(
        public string $host,
    ) {
        $this->pool = new HandlePool(1);
    }

    /**
     * Performs the actual gRPC call with interceptors.
     */
    public function call(UnaryCall $call): void
    {
        $invoker = $this->invokeUnary(...);

        foreach (array_reverse($call->options->interceptors) as $i) {
            $next = $invoker;
            $invoker = fn(UnaryCall $c) => $i->interceptUnary($c, $next);
        }

        // @mago-ignore analysis:unhandled-thrown-type
        // $ctx->id = bin2hex(random_bytes(16)); TODO: add call id

        $invoker($call);
    }

    /**
     * Performs the actual gRPC call without interceptors.
     *
     * @throws InvalidArgumentException if the message could not be encoded
     */
    private function invokeUnary(UnaryCall $call): void
    {
        $msg = $this->prepareMsg($call->args, $call->options->enc);

        /** @var array<string, string[]> */
        $replyHdr = [];
        $ch = $this->setupHandle($call, $msg, $replyHdr);

        /** @var string|false */
        $rawReply = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);

        /** @var array<string, mixed> */
        $curlInfo = curl_getinfo($ch);
        $call->curlInfo = $curlInfo;

        $this->pool->release($ch);

        if ($rawReply === false) {
            $call->message = $error;
            $call->code = match ($errno) {
                CURLE_OPERATION_TIMEDOUT => Code::DeadlineExceeded,
                CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_RESOLVE_PROXY => Code::Unavailable,
                default => Code::Unknown,
            };
            return;
        }

        $this->decodeReply($rawReply, $replyHdr, $call);
    }

    /**
     * @param  array<string, string[]>  $replyHdr
     */
    private function setupHandle(UnaryCall $call, string $msg, array &$replyHdr): CurlHandle
    {
        $ch = $this->pool->aquire();

        $headers = [
            'content-type: application/grpc',
            'user-agent: ' . $call->options->userAgent,
            'te: trailers',
            'grpc-encoding: ' . $call->options->enc->value,
            'grpc-accept-encoding: ' . Encoding::list(),
        ];

        // set metadata headers
        foreach ($call->options->meta as $key => $values) {
            foreach ($values as $value) {
                if (str_ends_with($key, '-bin')) {
                    $headers[] = $key . ': ' . base64_encode($value);
                } else {
                    $headers[] = $key . ': ' . $value;
                }
            }
        }

        $handleHdr = static function (\CurlHandle $_, string $h) use (&$replyHdr) {
            $l = trim($h);
            if ($l !== '' && !str_starts_with($l, 'HTTP/2 ')) {
                $p = explode(':', $l, 2);
                $replyHdr[trim($p[0])][] = trim($p[1] ?? '');
            }
            return strlen($h);
        };

        /** @var mixed $v silence linter */
        foreach ($call->options->curlOpts as $k => $v) {
            curl_setopt($ch, $k, $v);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->host . $call->method,
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
     * Decode metadata from reply headers.
     *
     * @param  array<string, string[]>  $replyHdr
     * @return array<string, string[]>
     * @throws Exception if invalid base64 metadata is found
     */
    private function decodeMeta(array $replyHdr): array
    {
        $meta = [];

        foreach ($replyHdr as $key => $values) {
            // filter out grpc-related headers
            if (str_starts_with($key, 'grpc-')) {
                continue;
            }

            // decode binary metadata
            if (str_ends_with($key, '-bin')) {
                foreach ($values as $value) {
                    $decoded = base64_decode($value, true);
                    if ($decoded === false) {
                        throw new Exception('Invalid base64 metadata: ' . $value);
                    }
                    $meta[$key][] = $decoded;
                }
                continue;
            }

            $meta[$key] = $values;
        }

        return $meta;
    }

    /**
     * Decode a gRPC reply message.
     *
     * @param  array<string, string[]>  $replyHdr
     */
    private function decodeReply(string $rawReply, array $replyHdr, UnaryCall $call): void
    {
        try {
            $call->meta = $this->decodeMeta($replyHdr);
        } catch (\Throwable $e) {
            $call->code = Code::Internal;
            $call->message = $e->getMessage();
            return;
        }

        $getHdrVal = static fn(string $key): string => $replyHdr[$key][0] ?? '';

        if ($getHdrVal('content-type') !== 'application/grpc') {
            $call->code = Code::Unknown;
            $call->message = 'Invalid content-type: ' . $getHdrVal('content-type');
            return;
        }

        $code = Code::tryFrom((int) $getHdrVal('grpc-status'));
        if ($code === null) {
            $call->code = Code::Unknown;
            $call->message = 'Unknown grpc-status code: ' . $getHdrVal('grpc-status');
            return;
        }
        if ($code !== Code::OK) {
            $call->code = $code;
            $call->message = $getHdrVal('grpc-message') ?: 'Unknown error';
            return;
        }

        $enc = Encoding::tryFrom($getHdrVal('grpc-encoding'));

        try {
            // @mago-ignore analysis:non-existent-method,mixed-assignment,possible-method-access-on-null,non-existent-method
            $dataType = new ReflectionProperty($call, 'data')->getType()->getName();
            // @mago-ignore analysis:missing-magic-method,property-type-coercion,unknown-class-instantiation
            $call->data = new $dataType();
            $call->data->mergeFromString($this->decodeMsg($rawReply, $enc));
        } catch (\Throwable $e) {
            $call->code = Code::Internal;
            $call->message = $e->getMessage();
            return;
        }

        $call->code = Code::OK;
        $call->message = implode(', ', $replyHdr['grpc-message'] ?? []);
    }
}
