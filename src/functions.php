<?php

namespace Iris;

use Iris\CallInfo;
use Iris\CallOption;
use Iris\Error;

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

function encoding(Encoding $enc): CallOption
{
    return new class($enc) extends CallOption {
        public function __construct(
            private Encoding $enc,
        ) {}

        public function before(CallInfo $info): null|Error
        {
            $info->enc = $this->enc;
            return null;
        }
    };
}

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
