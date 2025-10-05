<?php

declare(strict_types=1);

namespace Iris;

use CurlHandle;
use Google\Protobuf\Internal\Message;

class Client
{
    private HandlePool $pool;

    /**
     * @var CallOption[]
     */
    private array $callOpts = [];

    /**
     * @var Interceptor[]
     */
    private array $interceptors = [];

    public function __construct(
        public string $host,
    ) {
        $this->pool = new HandlePool(1);
    }

    public function globalOpts(CallOption ...$opts): void
    {
        $this->callOpts = $opts;
    }

    public function interceptors(Interceptor ...$interceptors): void
    {
        $this->interceptors = $interceptors;
    }

    /**
     * Performs the actual gRPC call with interceptors.
     */
    public function invoke(string $method, Message $args, Message $reply, CallOption ...$opts): null|Error
    {
        if (empty($this->interceptors)) {
            return $this->doInvoke($method, $args, $reply, ...$opts);
        }

        $invoker = fn(string $method, Message $args, Message $reply, CallOption ...$opts) => $this->doInvoke(
            $method,
            $args,
            $reply,
            ...$opts,
        );

        foreach (array_reverse($this->interceptors) as $interceptor) {
            $next = $invoker;
            $invoker = fn(
                string $method,
                Message $args,
                Message $reply,
                CallOption ...$opts,
            ) => $interceptor->intercept($method, $args, $reply, $next, ...$opts);
        }

        return $invoker($method, $args, $reply, ...$opts);
    }

    /**
     * Performs the actual gRPC call without interceptors.
     */
    private function doInvoke(string $method, Message $args, Message $reply, CallOption ...$opts): null|Error
    {
        $info = new CallInfo();
        foreach ([...$this->callOpts, ...$opts] as $o) {
            if ($err = $o->before($info)) {
                return $err;
            }
        }

        $msg = $this->prepareMsg($args, $info->enc);

        /** @var array<string, string> */
        $replyHdr = [];
        $ch = $this->setupHandle($info, $method, $msg, $replyHdr);

        /** @var string|false */
        $rawReply = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);

        $attempt = new CallAttempt();
        $attempt->curlInfo = curl_getinfo($ch);

        $this->pool->release($ch);

        if ($rawReply === false) {
            $code = match ($errno) {
                CURLE_OPERATION_TIMEDOUT => Code::DeadlineExceeded,
                CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_RESOLVE_PROXY => Code::Unavailable,
                default => throw new \Exception('Unknown curl error (errno: ' . $errno . ', error: ' . $error . ')'),
            };
            $result = new Error($code, $error);
        } else {
            $result = $this->decodeReply($rawReply, $replyHdr, $reply);
        }

        foreach ([...$this->callOpts, ...$opts] as $o) {
            $o->after($info, $attempt);
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $replyHdr
     */
    private function setupHandle(CallInfo $info, string $method, string $msg, array &$replyHdr): CurlHandle
    {
        $ch = $this->pool->aquire();

        $headers = [
            'content-type: application/grpc',
            'user-agent: ' . $info->userAgent,
            'te: trailers',
            'grpc-encoding: ' . $info->enc->value,
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

        foreach ($info->curlOpts as $k => $v) {
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
    private function prepareMsg(Message $args, Encoding $enc): string
    {
        $binary = $args->serializeToString();

        if ($enc === Encoding::Identity) {
            $cFlag = 0;
            $data = $binary;
        } else {
            $cFlag = 1;
            $data = match ($enc) { Encoding::Gzip => gzencode($binary, 6) };
        }

        $header = pack('CN', $cFlag, strlen($data));
        return $header . $data;
    }

    /**
     * Decode gRPC message by stripping 5-byte framing header and decompressing if needed.
     *
     * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md#requests
     */
    private function decodeMsg(string $msg, null|Encoding $enc): string|Error
    {
        $cFlag = unpack('C', $msg[0])[1];
        $data = substr($msg, 5);

        // No compression
        if ($cFlag === 0) {
            return $data;
        }

        if ($enc === null) {
            return new Error(Code::Unknown, 'Message is compressed but no encoding specified');
        }
        return match ($enc) {
            Encoding::Identity => new Error(Code::Unknown, 'Message is compressed but encoding is identity'),
            Encoding::Gzip => gzdecode($data) ?: new Error(Code::Unknown, 'Failed to decode gzip message'),
        };
    }

    /**
     * Decode a gRPC reply message.
     *
     * @param  array<string, string>  $replyHdr
     */
    private function decodeReply(string $rawReply, array $replyHdr, Message $reply): null|Error
    {
        if (!array_key_exists('content-type', $replyHdr)) {
            return new Error(Code::Unknown, 'Missing content-type header');
        }
        if ($replyHdr['content-type'] !== 'application/grpc') {
            return new Error(Code::Unknown, 'Invalid content-type: ' . $replyHdr['content-type']);
        }

        if (!array_key_exists('grpc-status', $replyHdr)) {
            return new Error(Code::Unknown, 'Missing grpc-status header');
        }
        $code = Code::tryFrom((int) $replyHdr['grpc-status']);
        if ($code === null) {
            return new Error(Code::Unknown, 'Unknown grpc-status code: ' . $replyHdr['grpc-status']);
        }
        if ($code !== Code::OK) {
            return new Error($code, $replyHdr['grpc-message'] ?? 'Unknown error');
        }

        $enc = Encoding::tryFrom($replyHdr['grpc-encoding'] ?? '');
        $msg = $this->decodeMsg($rawReply, $enc);
        if ($msg instanceof Error) {
            return $msg;
        }

        try {
            $reply->mergeFromString($msg);
        } catch (\Throwable $e) {
            return new Error(Code::Internal, $e->getMessage());
        }

        return null;
    }
}
