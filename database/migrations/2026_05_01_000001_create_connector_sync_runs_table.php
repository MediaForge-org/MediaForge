<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V1 Package F: Sync Foundation. One row per sync-foundation "dry run" over a
// configured connector. A dry run inspects STORED discovery/health state only —
// it performs no network calls, imports no media items and touches no files.
// `summary` holds counts + issue codes (never secrets, never raw API payloads).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE connector_sync_runs (
                id                    CHAR(26)    PRIMARY KEY,
                connector_instance_id CHAR(26)    NOT NULL REFERENCES connector_instances(id) ON DELETE CASCADE,
                mode                  TEXT        NOT NULL DEFAULT 'dry_run'
                    CHECK (mode IN ('dry_run')),
                status                TEXT        NOT NULL
                    CHECK (status IN ('pending','running','completed','completed_with_warnings','failed','cancelled')),
                started_at            TIMESTAMPTZ,
                finished_at           TIMESTAMPTZ,
                summary               JSONB       NOT NULL DEFAULT '{}',
                error_message         TEXT,
                created_by            TEXT        NOT NULL,
                created_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at            TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX connector_sync_runs_latest_idx ON connector_sync_runs (connector_instance_id, created_at DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS connector_sync_runs CASCADE');
    }
};
