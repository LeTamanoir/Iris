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
}
