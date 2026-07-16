<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V2 Package A: one row per external item captured from a connector library by a
// read-only snapshot. This is a CONNECTOR READ-MODEL, NOT a MediaForge media item —
// it never becomes a media_item/edition/file and no file is ever touched. Only
// small, sanitized display fields are stored (never secrets, tokens, raw API
// payloads, or full local paths). Items are upserted per (instance, external_id);
// a vanished item is flagged is_present=false rather than deleted.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE connector_catalog_items (
                id                    CHAR(26)    PRIMARY KEY,
                connector_instance_id CHAR(26)    NOT NULL REFERENCES connector_instances(id) ON DELETE CASCADE,
                connector_library_id  CHAR(26)    REFERENCES connector_libraries(id) ON DELETE SET NULL,
                snapshot_run_id       CHAR(26)    REFERENCES connector_catalog_snapshot_runs(id) ON DELETE SET NULL,
                external_id           TEXT        NOT NULL,
                external_parent_id    TEXT,
                media_kind            TEXT        NOT NULL DEFAULT 'unknown'
                    CHECK (media_kind IN ('movie','series','season','episode','audiobook','book','podcast','music','playlist','folder','unknown')),
                title                 TEXT        NOT NULL,
                sort_title            TEXT,
                original_title        TEXT,
                year                  INTEGER,
                index_number          INTEGER,
                parent_index_number   INTEGER,
                runtime_seconds       INTEGER,
                external_updated_at   TIMESTAMPTZ,
                first_seen_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
                last_seen_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
                missing_since         TIMESTAMPTZ,
                is_present            BOOLEAN     NOT NULL DEFAULT true,
                metadata              JSONB       NOT NULL DEFAULT '{}',
                created_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE (connector_instance_id, external_id)
            )
        SQL);

        DB::statement('CREATE INDEX connector_catalog_items_library_idx ON connector_catalog_items (connector_instance_id, connector_library_id)');
        DB::statement('CREATE INDEX connector_catalog_items_run_idx ON connector_catalog_items (snapshot_run_id)');
        DB::statement('CREATE INDEX connector_catalog_items_recent_idx ON connector_catalog_items (connector_instance_id, last_seen_at DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS connector_catalog_items CASCADE');
    }
};
