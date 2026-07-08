<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/MediaForge/database/core-schema.md § Review-Tasks
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE review_tasks (
                id            CHAR(26)    PRIMARY KEY,
                task_type     TEXT        NOT NULL
                    CHECK (task_type IN ('disc_episode_mapping','media_match','duplicate_suspect',
                                         'chapter_proposal','unexpected_media_kind','mass_deletion',
                                         'connector_conflict','metadata_conflict')),
                subject_type  TEXT        NOT NULL,
                subject_id    CHAR(26)    NOT NULL,
                status        TEXT        NOT NULL DEFAULT 'open'
                    CHECK (status IN ('open','in_review','resolved','dismissed','expired')),
                priority      TEXT        NOT NULL DEFAULT 'normal'
                    CHECK (priority IN ('low','normal','high')),
                evidence      JSONB       NOT NULL DEFAULT '{}',
                resolution    JSONB,
                created_by    TEXT        NOT NULL,
                resolved_by   CHAR(26)    REFERENCES users(id) ON DELETE SET NULL,
                resolved_at   TIMESTAMPTZ,
                created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX review_tasks_open_idx ON review_tasks (status, task_type, priority)
                WHERE status IN ('open','in_review')
        SQL);
        DB::statement('CREATE INDEX review_tasks_subject_idx ON review_tasks (subject_type, subject_id)');
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX review_tasks_no_duplicate_open
                ON review_tasks (task_type, subject_type, subject_id)
                WHERE status IN ('open','in_review')
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS review_tasks CASCADE');
    }
};
