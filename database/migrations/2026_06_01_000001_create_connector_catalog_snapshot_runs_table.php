<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V2 Package A: read-only connector catalog snapshots. One row per explicitly
// triggered snapshot of a connector library. A snapshot READS external items from
// Jellyfin/Audiobookshelf and stores them as a read-only connector read-model — it
// imports no media, creates no media_items/editions/files and touches no files.
// `summary` holds counts + sanitized notes (never secrets, never raw API payloads).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE connector_catalog_snapshot_runs (
                id                    CHAR(26)    PRIMARY KEY,
                connector_instance_id CHAR(26)    NOT NULL REFERENCES connector_instances(id) ON DELETE CASCADE,
                connector_library_id  CHAR(26)    REFERENCES connector_libraries(id) ON DELETE SET NULL,
                status                TEXT        NOT NULL
                    CHECK (status IN ('pending','running','completed','completed_with_warnings','failed','cancelled')),
                started_at            TIMESTAMPTZ,
                finished_at           TIMESTAMPTZ,
                items_seen_count      INTEGER     NOT NULL DEFAULT 0,
                items_stored_count    INTEGER     NOT NULL DEFAULT 0,
                warnings_count        INTEGER     NOT NULL DEFAULT 0,
                errors_count          INTEGER     NOT NULL DEFAULT 0,
                summary               JSONB       NOT NULL DEFAULT '{}',
                error_message         TEXT,
                created_by            TEXT,
                created_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at            TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX connector_catalog_snapshot_runs_latest_idx ON connector_catalog_snapshot_runs (connector_instance_id, created_at DESC)');
        DB::statement('CREATE INDEX connector_catalog_snapshot_runs_library_idx ON connector_catalog_snapshot_runs (connector_library_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS connector_catalog_snapshot_runs CASCADE');
    }
};
