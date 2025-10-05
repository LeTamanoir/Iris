<?php

declare(strict_types=1);

namespace Iris;

/**
 * Represents a gRPC error with status code and message.
 */
readonly class Error
{
    public function __construct(
        public Code $code,
        public string $message,
    ) {}
}
