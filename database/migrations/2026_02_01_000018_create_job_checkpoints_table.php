<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/MediaForge/database/core-schema.md § Job-Infrastruktur (ResumableJob).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE job_checkpoints (
                id             CHAR(26)    PRIMARY KEY,
                checkpoint_key TEXT        NOT NULL,
                step_name      TEXT        NOT NULL,
                attempts       INTEGER     NOT NULL DEFAULT 1,
                completed_at   TIMESTAMPTZ,
                created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE (checkpoint_key, step_name)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS job_checkpoints CASCADE');
    }
};
