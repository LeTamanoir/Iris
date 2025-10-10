<?php

declare(strict_types=1);

namespace Iris\Internal;

use Fiber;

/**
 * PendingFiber is a wrapper around a fiber that needs to be resumed at a specific time.
 */
readonly class PendingFiber
{
    public function __construct(
        public Fiber $fiber,
        public float $resumeAt,
    ) {}
}
