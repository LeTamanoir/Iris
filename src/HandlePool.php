<?php

declare(strict_types=1);

namespace Iris;

use CurlHandle;

/**
 * Manages curl handle lifecycle with optional reuse for connection pooling.
 */
class HandlePool
{
    /**
     * @var CurlHandle[]
     */
    private array $handles = [];

    public function __construct(
        public int $maxHandles,
    ) {}

    public function aquire(): CurlHandle
    {
        // try to reuse a handle from the pool
        if (count($this->handles) > 0) {
            return array_pop($this->handles);
        }

        // if no handle is available, create a new one
        /** @var CurlHandle */
        return curl_init();
    }

    public function release(CurlHandle $ch): void
    {
        if (count($this->handles) >= $this->maxHandles) {
            // close the handle as we won't reuse it
            curl_close($ch);
        } else {
            // reset the handle, but keep it open and add it to the pool
            curl_reset($ch);
            $this->handles[] = $ch;
        }
    }

    public function __destruct()
    {
        foreach ($this->handles as $i => $ch) {
            curl_close($ch);
            unset($this->handles[$i]);
        }
    }
}
