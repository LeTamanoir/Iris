<?php

declare(strict_types=1);

namespace Iris;

/**
 * Represents a gRPC unary call.
 */
final class UnaryCall
{
    /**
     * @param array<string, mixed> $curlInfo
     */
    public function __construct(
        public Code $code,
        public string $message,
        public array $curlInfo = [],
    ) {}
}
