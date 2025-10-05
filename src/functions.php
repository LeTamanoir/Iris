<?php

use Iris\CallCtx;
use Iris\CallOption;
use Iris\Error;

if (!function_exists('verbose')) {
    function verbose(bool $verbose = true): CallOption
    {
        return new class($verbose) extends CallOption {
            public function __construct(
                private bool $verbose,
            ) {}

            public function before(CallCtx $ctx): null|Error
            {
                $ctx->curlOpts[CURLOPT_VERBOSE] = $this->verbose;
                return null;
            }
        };
    }
}
