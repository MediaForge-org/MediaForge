<?php

declare(strict_types=1);

namespace App\Core\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * A long-running job decomposed into named, idempotent steps whose progress is
 * checkpointed in PostgreSQL, so a restart resumes rather than restarts
 * (architecture/overview.md § ResumableJob-Vertrag). A step that fails
 * `maxStepAttempts()` times routes the subject to the failure path instead of
 * retrying forever.
 */
abstract class ResumableJob implements ShouldQueue
{
    /** A business-unique key for this unit of work, e.g. "analyze-disc:{ulid}". */
    abstract public function checkpointKey(): string;

    /** @return list<JobStep> ordered, each individually idempotent */
    abstract public function steps(): array;

    public function handle(CheckpointStore $checkpoints): void
    {
        $done = $checkpoints->completedSteps($this->checkpointKey());

        foreach ($this->steps() as $step) {
            if (in_array($step->name, $done, strict: true)) {
                continue;
            }

            if ($checkpoints->recordAttempt($this->checkpointKey(), $step->name) > $this->maxStepAttempts()) {
                $this->onStepExhausted($step);

                return;
            }

            $step->run();
            $checkpoints->markCompleted($this->checkpointKey(), $step->name);
        }

        $checkpoints->clear($this->checkpointKey());
    }

    public function maxStepAttempts(): int
    {
        return 3;
    }

    /**
     * Called when a step exhausts its attempts. Subclasses mark their subject as
     * failed and (if relevant) create a review task, then terminate successfully
     * so the failed-queue is reserved for infrastructure faults.
     */
    protected function onStepExhausted(JobStep $step): void
    {
        // Default: give up quietly. Subclasses override to record the failure.
    }
}
