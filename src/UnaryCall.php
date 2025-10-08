<?php

declare(strict_types=1);

namespace Iris;

use Google\Protobuf\Internal\Message;

/**
 * Represents a gRPC unary call.
 *
 * @property Message $data The reply message, present in each reply class.
 */
abstract class UnaryCall
{
    /**
     * The request arguments.
     */
    public Message $args;

    /**
     * The call options.
     */
    public CallOptions $options;

    /**
     * The method name.
     */
    public string $method;

    /**
     * The gRPC status code.
     */
    public Code $code;

    /**
     * The gRPC status message.
     */
    public string $message;

    /**
     * The cURL info from the request.
     *
     * @var array<string, mixed>
     */
    public array $curlInfo;

    /**
     * The metadata from the request.
     *
     * @var array<string, string[]>
     */
    public array $meta;

    /**
     * Wait for the call to complete.
     * When waiting for multiple calls, please use the `Client::waitCalls` method instead.
     */
    public function wait(): void
    {
        dd('wait');
    }
}
