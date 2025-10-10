<?php

declare(strict_types=1);

namespace Iris;

use CurlHandle;
use CurlMultiHandle;
use Exception;
use Fiber;
use Google\Protobuf\Internal\Message;
use InvalidArgumentException;
use Iris\Internal\EventLoop;
use ReflectionProperty;

// TODO: Add global call options merging
class Connection
{
    /**
     * The target host of the connection.
     */
    public string $host;

    /**
     * The options for the connection.
     */
    private CallOptions $options;

    /**
     * The pool of curl handles. (avoiding allocation of new handles on every call)
     */
    private HandlePool $pool;

    /**
     * The curl multi handle of the connection. (used in the event loop)
     */
    private CurlMultiHandle $mh;

    public function __construct(string $host, CallOptions $options = new CallOptions())
    {
        $this->host = $host;
        $this->pool = new HandlePool(50);
        $this->mh = curl_multi_init();
        $this->options = $options;
    }

    /**
     * Performs the actual gRPC call with interceptors.
     */
    public function invoke(UnaryCall ...$calls): void
    {
        $ev = new EventLoop();

        foreach ($calls as $call) {
            $call->options = CallOptions::merge($this->options, $call->options);

            $fiber = new Fiber(function (UnaryCall $call): void {
                $invoker = function (UnaryCall $c): UnaryCall {
                    $replyHeaders = [];

                    $ch = $this->setupHandle($c, $replyHeaders);
                    curl_setopt($ch, CURLOPT_PRIVATE, Fiber::getCurrent());
                    curl_multi_add_handle($this->mh, $ch);

                    // Yield control to the event loop to process the call
                    Fiber::suspend();

                    $this->processHandle($ch, $c, $replyHeaders);
                    curl_multi_remove_handle($this->mh, $ch);

                    return $c;
                };

                foreach (array_reverse($call->options->interceptors) as $i) {
                    $next = $invoker;
                    $invoker = fn(UnaryCall $c) => $i->interceptUnary($c, $next);
                }

                $invoker($call);
            });

            $ev->addFiber($fiber);
            $fiber->start($call);
        }

        $ev->run($this->mh);
    }

    private function processHandle(CurlHandle $ch, UnaryCall $call, array $replyHeaders): void
    {
        /** @var string */
        $rawReply = curl_multi_getcontent($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);

        /** @var array<string, mixed> */
        $curlInfo = curl_getinfo($ch);
        $call->curlInfo = $curlInfo;

        $this->pool->release($ch);

        if ($errno !== 0) {
            $call->message = $error;

            // Because we use the grpc-timeout header
            // see https://github.com/grpc/grpc-go/blob/master/internal/transport/http2_server.go#L629
            if (str_contains($error, 'HTTP/2 stream 1 was not closed cleanly: CANCEL')) {
                $call->code = Code::DeadlineExceeded;
            } else {
                $call->code = match ($errno) {
                    CURLE_OPERATION_TIMEDOUT => Code::DeadlineExceeded,
                    CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_RESOLVE_PROXY => Code::Unavailable,
                    default => Code::Unknown,
                };
            }
            return;
        }

        $this->decodeReply($rawReply, $call, $replyHeaders);
    }

    private function setupHandle(UnaryCall $call, array &$replyHeaders): CurlHandle
    {
        $ch = $this->pool->aquire();

        $headers = [
            'content-type: application/grpc',
            'user-agent: ' . $call->options->userAgent,
            'te: trailers',
            'grpc-encoding: ' . $call->options->encoding->value,
            'grpc-accept-encoding: ' . Encoding::list(),
        ];

        if ($call->options->timeout !== null) {
            $headers[] = 'grpc-timeout: ' . $call->options->timeout . 'm';
        }

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

        // Set a local timeout as an additional safeguard,
        // even though grpc-timeout header handles it at the protocol level.
        if ($call->options->timeout !== null) {
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, $call->options->timeout);
        }

        if ($call->options->verbose !== null) {
            curl_setopt($ch, CURLOPT_VERBOSE, $call->options->verbose);
        }

        /** @var mixed $v silence linter */
        foreach ($call->options->curlOpts as $k => $v) {
            curl_setopt($ch, $k, $v);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->host . $call->method,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_PRIOR_KNOWLEDGE,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $this->prepareMsg($call->args, $call->options->encoding),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => static function (CurlHandle $_, string $h) use (&$replyHeaders): int {
                $l = trim($h);
                if ($l !== '' && !str_starts_with($l, 'HTTP/2 ')) {
                    $p = explode(':', $l, 2);
                    $replyHeaders[trim($p[0])][] = trim($p[1] ?? '');
                }
                return strlen($h);
            },
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
     */
    private function decodeReply(string $rawReply, UnaryCall $call, array $replyHeaders): void
    {
        try {
            $call->meta = $this->decodeMeta($replyHeaders);
        } catch (\Throwable $e) {
            $call->code = Code::Internal;
            $call->message = $e->getMessage();
            return;
        }

        $getHdrVal = static fn(string $key): string => $replyHeaders[$key][0] ?? '';

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
        $call->message = implode(', ', $replyHeaders['grpc-message'] ?? []);
    }
}
