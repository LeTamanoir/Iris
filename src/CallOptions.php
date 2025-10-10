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
     * @param  Interceptor[]  $interceptors
     * @param  array<int, mixed>  $curlOpts
     * @param  array<string, string[]>  $meta
     */
    public function __construct(
        /**
         * The interceptors to use for the call.
         */
        public array $interceptors = [],

        /**
         * The curl options to use for the call.
         */
        public array $curlOpts = [],

        /**
         * The timeout in milliseconds for the call. (default: 30 seconds)
         */
        public null|int $timeout = null,

        /**
         * The encoding to use for the call. (default: Encoding::Identity)
         */
        public null|Encoding $encoding = null,

        /**
         * The metadata to use for the call.
         */
        public array $meta = [],

        /**
         * The user agent to use for the request.
         */
        public null|string $userAgent = null,
    ) {
        $this->validateMeta();
    }

    private function validateMeta(): void
    {
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
            interceptors: [...$a->interceptors, ...$b->interceptors],
            curlOpts: $a->curlOpts + $b->curlOpts,
            meta: [...$a->meta, ...$b->meta],
            userAgent: $a->userAgent ?? $b->userAgent ?? self::DEFAULT_USER_AGENT,
            encoding: $a->encoding ?? $b->encoding ?? self::DEFAULT_ENCODING,
            timeout: $a->timeout ?? $b->timeout ?? self::DEFAULT_TIMEOUT,
        );
    }
}
