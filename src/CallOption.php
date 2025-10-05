<?php

declare(strict_types=1);

namespace Iris;

/**
 * CallOption configures a Call before it starts or
 * extracts information from a Call after it completes.
 */
abstract class CallOption
{
    public function before(CallInfo $info): null|Error
    {
        $info; // silence linter
        return null;
    }

    public function after(CallInfo $info, CallAttempt $attempt): void
    {
        $info; // silence linter
        $attempt; // silence linter
        return;
    }
}
