<?php

declare(strict_types=1);

namespace Iris;

/**
 * Supported compression encodings.
 *
 * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md (gRPC Protocol)
 * @see https://pkg.go.dev/google.golang.org/grpc/encoding (Go encoding package)
 */
enum Encoding: string
{
    case Identity = 'identity';
    case Gzip = 'gzip';

    public static function list(): string
    {
        return 'identity,gzip';
    }
}
