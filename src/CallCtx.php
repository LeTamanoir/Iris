<?php

declare(strict_types=1);

namespace Iris;

use Google\Protobuf\Internal\Message;

/**
 * Call context for a gRPC call.
 */
class CallCtx
{
    /**
     * The encoding to use for the request.
     */
    public Encoding $enc = Encoding::Identity;

    /**
     * The request message.
     */
    public Message $args;

    /**
     * The method to call.
     */
    public string $method;

    /**
     * The ID of the call.
     */
    public string $id;

    /**
     * The interceptors to use for the call.
     *
     * @var Interceptor[]
     */
    public array $interceptors = [];

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

    /**
     * Additional metadata for the call.
     *
     * @var array<string, mixed>
     */
    public array $meta = [];
}
