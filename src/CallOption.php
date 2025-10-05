<?php

declare(strict_types=1);

namespace Iris;

/**
 * Common call options for configuring gRPC calls.
 */
abstract class CallOption
{
    public function before(CallCtx $ctx): null|Error
    {
        $ctx;
        return null;
    }

    public function after(CallCtx $ctx): void
    {
        $ctx;
        return;
    }

    // TODO: add encoding support
    // public static function compress(Encoding $encoding): callable
    // {
    //     return fn(CallCtx $ctx) => $ctx->enc = $encoding;
    // }
    // public static function verbose(bool $verbose = true): callable
    // {
    //     return fn(CallCtx $ctx) => $ctx->curlOpts[CURLOPT_VERBOSE] = $verbose;
    // }
    // TODO: add timeout support
    // public static function timeout(int $ms): callable
    // {
    //     return fn(CallCtx $ctx) => $ctx->curlOpts[CURLOPT_TIMEOUT_MS] = $ms;
    // }
    //
    // TODO: switch param to int|string + add time parser
    // public static function connectTimeout(int $ms): callable
    // {
    //     return fn(CallCtx $ctx) => $ctx->curlOpts[CURLOPT_CONNECTTIMEOUT_MS] = $ms;
    // }
}
