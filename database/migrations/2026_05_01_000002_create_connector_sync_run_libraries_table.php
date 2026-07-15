<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V1 Package F: per-library plan captured for a dry run. This records what a
// FUTURE sync WOULD consider for each library (inspect-only) — no media items are
// created and no files are touched. The connector_library_id is nullable so the
// historical plan survives a later library deletion (ON DELETE SET NULL).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE connector_sync_run_libraries (
                id                       CHAR(26)    PRIMARY KEY,
                connector_sync_run_id    CHAR(26)    NOT NULL REFERENCES connector_sync_runs(id) ON DELETE CASCADE,
                connector_library_id     CHAR(26)    REFERENCES connector_libraries(id) ON DELETE SET NULL,
                external_id              TEXT        NOT NULL,
                name                     TEXT        NOT NULL,
                type                     TEXT,
                status                   TEXT        NOT NULL
                    CHECK (status IN ('planned','skipped','warning','failed','ready')),
                planned_action           TEXT        NOT NULL
                    CHECK (planned_action IN ('inspect_only','future_sync_candidate','skipped_not_selected','skipped_missing')),
                summary                  JSONB       NOT NULL DEFAULT '{}',
                created_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at               TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX connector_sync_run_libraries_run_idx ON connector_sync_run_libraries (connector_sync_run_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS connector_sync_run_libraries CASCADE');
    }
};
