<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/MediaForge/database/core-schema.md § Job-Infrastruktur (progress reporting).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE job_progress (
                id            CHAR(26)    PRIMARY KEY,
                subject_type  TEXT        NOT NULL,
                subject_id    CHAR(26)    NOT NULL,
                job_class     TEXT        NOT NULL,
                phase         TEXT        NOT NULL,
                done          BIGINT      NOT NULL DEFAULT 0,
                total         BIGINT,
                message       TEXT,
                started_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
                finished_at   TIMESTAMPTZ,
                outcome       TEXT
                    CHECK (outcome IN ('succeeded','failed','superseded')),
                UNIQUE (subject_type, subject_id, job_class)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS job_progress CASCADE');
    }
};
