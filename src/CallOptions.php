<?php

declare(strict_types=1);

namespace Iris;

use InvalidArgumentException;

class CallOptions
{
    const DEFAULT_ENCODING = Encoding::Identity;

    const DEFAULT_USER_AGENT = 'iris-php/' . \Iris\VERSION;

    const DEFAULT_TIMEOUT = 30_000; // 30 seconds

    /**
     * @param  Interceptor[]  $interceptors The interceptors to use for the call.
     * @param  array<int, mixed>  $curlOpts The curl options to use for the call.
     * @param  array<string, string[]>  $meta The metadata to use for the call.
     * @param  null|string $userAgent The user agent to use for the call.
     * @param  null|int $timeout The timeout in milliseconds for the call. (default: 30 seconds)
     * @param  null|Encoding $encoding The encoding to use for the call. (default: Encoding::Identity)
     * @param  array<string, string[]> $meta The metadata to use for the call.
     * @param  bool $verbose Whether to print verbose output for the call. (default: false)
     */
    public function __construct(
        public null|array $interceptors = null,
        public null|array $curlOpts = null,
        public null|int $timeout = null,
        public null|Encoding $encoding = null,
        public null|array $meta = null,
        public null|string $userAgent = null,
        public null|bool $verbose = null,
    ) {
        $this->validateMeta();
    }

    private function validateMeta(): void
    {
        if ($this->meta === null) {
            return;
        }

        foreach ($this->meta as $key => $values) {
            if (!preg_match('/^[0-9a-z_.-]+$/', $key)) {
                throw new InvalidArgumentException("Invalid metadata key: '$key'");
            }

            foreach ($values as $value) {
                // printable chars from space (\x20) to tilde (\x7E)
                if (!preg_match('/^[\x20-\x7E]+$/', $value)) {
                    throw new InvalidArgumentException("Invalid metadata value for '$key': '$value'");
                }
            }
        }
    }

    public static function merge(CallOptions $a, CallOptions $b): self
    {
        return new self(
            interceptors: [...($a->interceptors ?? []), ...($b->interceptors ?? [])],
            curlOpts: ($a->curlOpts ?? []) + ($b->curlOpts ?? []),
            meta: [...($a->meta ?? []), ...($b->meta ?? [])],
            userAgent: $a->userAgent ?? $b->userAgent ?? self::DEFAULT_USER_AGENT,
            encoding: $a->encoding ?? $b->encoding ?? self::DEFAULT_ENCODING,
            timeout: $a->timeout ?? $b->timeout ?? self::DEFAULT_TIMEOUT,
            verbose: $a->verbose ?? $b->verbose ?? false,
        );
    }
}
