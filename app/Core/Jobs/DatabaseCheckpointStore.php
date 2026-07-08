<?php

declare(strict_types=1);

namespace App\Core\Jobs;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

final class DatabaseCheckpointStore implements CheckpointStore
{
    public function __construct(private readonly DatabaseManager $db) {}

    /** @return list<string> */
    public function completedSteps(string $checkpointKey): array
    {
        $steps = [];

        foreach ($this->table()->where('checkpoint_key', $checkpointKey)->whereNotNull('completed_at')->pluck('step_name') as $step) {
            $steps[] = is_scalar($step) ? (string) $step : '';
        }

        return $steps;
    }

    public function markCompleted(string $checkpointKey, string $stepName): void
    {
        $this->table()->updateOrInsert(
            ['checkpoint_key' => $checkpointKey, 'step_name' => $stepName],
            ['completed_at' => now(), 'id' => (string) Str::ulid()],
        );
    }

    public function recordAttempt(string $checkpointKey, string $stepName): int
    {
        return $this->db->transaction(function () use ($checkpointKey, $stepName): int {
            $row = $this->table()
                ->where('checkpoint_key', $checkpointKey)
                ->where('step_name', $stepName)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $this->table()->insert([
                    'id' => (string) Str::ulid(),
                    'checkpoint_key' => $checkpointKey,
                    'step_name' => $stepName,
                    'attempts' => 1,
                ]);

                return 1;
            }

            $attempts = (is_numeric($row->attempts) ? (int) $row->attempts : 0) + 1;
            $this->table()
                ->where('checkpoint_key', $checkpointKey)
                ->where('step_name', $stepName)
                ->update(['attempts' => $attempts]);

            return $attempts;
        });
    }

    public function clear(string $checkpointKey): void
    {
        $this->table()->where('checkpoint_key', $checkpointKey)->delete();
    }

    private function table(): Builder
    {
        return $this->db->table('job_checkpoints');
    }
}
