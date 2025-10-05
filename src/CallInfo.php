<?php

declare(strict_types=1);

namespace Iris;

/**
 * Mutable information for configuring a gRPC call.
 */
class CallInfo
{
    /**
     * The encoding to use for the request.
     */
    public Encoding $enc = Encoding::Identity;

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
