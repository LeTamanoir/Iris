<?php

use Iris\CallInfo;
use Iris\CallOption;
use Iris\Duration;
use Iris\Error;

if (!function_exists('verbose')) {
    function verbose(bool $verbose = true): CallOption
    {
        return new class($verbose) extends CallOption {
            public function __construct(
                private bool $verbose,
            ) {}

            public function before(CallInfo $info): null|Error
            {
                $info->curlOpts[CURLOPT_VERBOSE] = $this->verbose;
                return null;
            }
        };
    }
}

if (!function_exists('timeout')) {
    /**
     * CallOption for setting the timeout for a call.
     */
    function timeout(int $ms): CallOption
    {
        return new class($ms) extends CallOption {
            public function __construct(
                private int $ms,
            ) {}

            public function before(CallInfo $info): null|Error
            {
                $info->curlOpts[CURLOPT_TIMEOUT_MS] = $this->ms;
                return null;
            }
        };
    }
}
