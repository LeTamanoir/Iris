<?php

declare(strict_types=1);

namespace Iris;

use InvalidArgumentException;

class CallOptions
{
    /**
     * @param  Interceptor[]  $interceptors
     * @param  array<int, mixed>  $curlOpts
     * @param  array<string, string[]>  $meta
     * @param  int  $timeout
     * @param  Encoding  $enc
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
        public int $timeout = 30_000,

        /**
         * The encoding to use for the call.
         */
        public Encoding $enc = Encoding::Identity,

        /**
         * The metadata to use for the call.
         */
        public array $meta = [],

        /**
         * The user agent to use for the request.
         */
        public string $userAgent = 'iris-php/' . \Iris\VERSION,
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
}
