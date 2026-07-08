<?php

declare(strict_types=1);

namespace App\Core\Jobs;

/**
 * Persists ResumableJob progress in PostgreSQL (job_checkpoints) — deliberately
 * not Redis: a cache flush must not cost progress.
 */
interface CheckpointStore
{
    /** @return list<string> names of the steps already completed for this key */
    public function completedSteps(string $checkpointKey): array;

    public function markCompleted(string $checkpointKey, string $stepName): void;

    /** Increments and returns the attempt counter for a step. */
    public function recordAttempt(string $checkpointKey, string $stepName): int;

    /** Clears all checkpoints for a key (called once the job completes). */
    public function clear(string $checkpointKey): void;
}
