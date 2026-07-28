<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V2 Package E: one run of the FIRST INTERNAL IMPORT over a V2 D plan.
//
// An execution turns the plan's READY lines into MediaForge database records and
// records exactly what it did. It is a DATABASE-ONLY import: it copies, moves,
// deletes or renames NO file, stores NO file path, creates NO media_files row, and
// sends NO write to Jellyfin/Audiobookshelf (no scan, no library refresh). It
// accepts no match and merges no duplicate; needs-review / blocked / skipped /
// duplicate / unsupported plan lines are never imported.
//
// `summary` holds sanitized counts and reason codes only — never secrets, tokens,
// raw API payloads, stack traces or local paths.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE media_import_executions (
                id                     CHAR(26)    PRIMARY KEY,
                media_import_plan_id   CHAR(26)    NOT NULL REFERENCES media_import_plans(id) ON DELETE CASCADE,
                status                 TEXT        NOT NULL DEFAULT 'empty'
                    CHECK (status IN ('completed','completed_with_warnings','failed','empty')),
                imported_count         INTEGER     NOT NULL DEFAULT 0 CHECK (imported_count >= 0),
                skipped_count          INTEGER     NOT NULL DEFAULT 0 CHECK (skipped_count >= 0),
                already_existing_count INTEGER     NOT NULL DEFAULT 0 CHECK (already_existing_count >= 0),
                failed_count           INTEGER     NOT NULL DEFAULT 0 CHECK (failed_count >= 0),
                summary                JSONB       NOT NULL DEFAULT '{}',
                created_by             TEXT,
                created_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at             TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX media_import_executions_plan_idx ON media_import_executions (media_import_plan_id, created_at DESC)');
        DB::statement('CREATE INDEX media_import_executions_status_idx ON media_import_executions (status, created_at DESC)');
        DB::statement('CREATE INDEX media_import_executions_created_idx ON media_import_executions (created_at DESC, id DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS media_import_executions CASCADE');
    }
};
