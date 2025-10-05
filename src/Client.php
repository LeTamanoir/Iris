<?php

declare(strict_types=1);

namespace Iris;

use Google\Protobuf\Internal\Message;

class Client
{
    public function __construct(
        public string $host,
    ) {}

    /**
     * @template T of Message
     *
     * @param  T  $reply
     * @return T|Error
     */
    public function invoke(string $method, Message $args, Message $reply): Message|Error
    {
        $ctx = new CallCtx();
        // TODO: add call options
        // foreach ($opts as $o) {
        //     $o($ctx);
        // }

        $msg = $this->prepareMsg($ctx, $args);

        $replyHdr = [];
        $ch = $this->setupHandle($ctx, $method, $msg, $replyHdr);

        $rawReply = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($rawReply === false) {
            $code = match ($errno) {
                CURLE_OPERATION_TIMEDOUT => Code::DeadlineExceeded,
                CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_RESOLVE_PROXY => Code::Unavailable,
                default => throw new \Exception('Unknown curl error (errno: ' . $errno . ', error: ' . $error . ')'),
            };
            return new Error($code, $error);
        }

        return $this->decodeReply($rawReply, $replyHdr, $reply);
    }

    private function setupHandle(CallCtx $ctx, string $method, string $msg, array &$replyHdr): \CurlHandle
    {
        $ch = curl_init($this->host . $method);

        $headers = [
            'content-type: application/grpc',
            'user-agent: ' . $ctx->userAgent,
            'te: trailers',
        ];
        // TODO: add encoding support
        // if ($ctx->enc !== null) {
        //     $headers[] = 'grpc-encoding: ' . $ctx->enc->value;
        //     $headers[] = 'grpc-accept-encoding: ' . Encoding::list();
        // }

        $handleHdr = static function (\CurlHandle $_, string $h) use (&$replyHdr) {
            $l = trim($h);
            if ($l !== '') {
                $p = explode(':', $l, 2);
                $replyHdr[trim($p[0])] = trim($p[1] ?? '');
            }
            return strlen($h);
        };

        foreach ($ctx->curlOpts as $k => $v) {
            curl_setopt($ch, $k, $v);
        }

        curl_setopt_array($ch, [
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
    private function prepareMsg(CallCtx $ctx, Message $args): string
    {
        $binary = $args->serializeToString();

        // if ($ctx->enc === null || $ctx->enc === Encoding::Identity) {
        $cFlag = 0;
        $data = $binary;
        // } else {
        // TODO: add encoding support
        // $cFlag = 1;
        // $data = match ($ctx->enc) {
        //     Encoding::Gzip => gzencode($binary, 6),
        //     Encoding::Deflate => gzcompress($binary, 6),
        // };
        // }

        $header = pack('CN', $cFlag, strlen($data));
        return $header . $data;
    }

    /**
     * Decode gRPC message by stripping 5-byte framing header and decompressing if needed.
     *
     * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md#requests
     */
    private function decodeMsg(string $msg): string|Error
    {
        $cFlag = unpack('C', $msg[0])[1];
        $data = substr($msg, 5);

        // No compression
        if ($cFlag === 0) {
            return $data;
        }

        throw new \Exception('Encoding not supported');

        // TODO: add encoding support
        // if ($enc === null) {
        //     return new Error(Code::Unknown, 'Message is compressed but no encoding specified');
        // }
        // return match ($enc) {
        //     Encoding::Identity => new Error(Code::Unknown, 'Message is compressed but encoding is identity'),
        //     Encoding::Gzip => gzdecode($data),
        //     Encoding::Deflate => gzuncompress($data),
        // };
    }

    /**
     * Decode a gRPC reply message.
     */
    private function decodeReply(string $rawReply, array $replyHdr, Message $reply): Message|Error
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

        // $enc = Encoding::tryFrom($replyHdr['grpc-encoding'] ?? '');

        $msg = $this->decodeMsg($rawReply);
        if ($msg instanceof Error) {
            return $msg;
        }

        try {
            $reply->mergeFromString($msg);
        } catch (\Throwable $e) {
            return new Error(Code::Internal, $e->getMessage());
        }

        return $reply;
    }
}
