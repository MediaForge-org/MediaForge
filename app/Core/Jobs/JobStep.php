<?php

declare(strict_types=1);

namespace App\Core\Jobs;

use Closure;

/**
 * A single named, idempotent step of a ResumableJob. The step commits its own
 * database results before its checkpoint is recorded.
 */
final readonly class JobStep
{
    /** @param Closure(): void $callback */
    public function __construct(
        public string $name,
        private Closure $callback,
    ) {}

    public function run(): void
    {
        ($this->callback)();
    }
}
