<?php

declare(strict_types=1);

namespace Iris;

/**
 * Mutable context for configuring a gRPC call.
 */
class CallCtx
{
    // TODO: add encoding support
    // /**
    //  * The encoding to use for the request.
    //  */
    // public null|Encoding $enc = null;

    /**
     * The curl options to use for the request.
     *
     * @var array<int, int|string|bool>
     */
    public array $curlOpts = [];

    /**
     * The user agent to use for the request.
     */
    public string $userAgent = 'iris-php/' . \Iris\VERSION;
}
