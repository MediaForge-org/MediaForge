<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V2 Package C: the normalized read-model of one captured external catalog item.
// It interprets what a connector reported (title/kind/year/season/episode/runtime)
// into a consistent shape plus a quality verdict, so data problems and match
// candidates become visible. This is STILL a connector read-model, NOT a MediaForge
// media item: it creates no media_items/editions/files, touches no files, changes
// nothing on the remote, and accepts no match. `issues`/`normalized_data` hold only
// small sanitized values — never secrets, tokens, raw API payloads or local paths.
// One row per catalog item (unique FK), rebuilt in place on every normalization run.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE connector_catalog_item_normalizations (
                id                        CHAR(26)    PRIMARY KEY,
                connector_catalog_item_id CHAR(26)    NOT NULL UNIQUE REFERENCES connector_catalog_items(id) ON DELETE CASCADE,
                connector_instance_id     CHAR(26)    NOT NULL REFERENCES connector_instances(id) ON DELETE CASCADE,
                connector_library_id      CHAR(26)    REFERENCES connector_libraries(id) ON DELETE SET NULL,
                normalized_kind           TEXT        NOT NULL DEFAULT 'unknown'
                    CHECK (normalized_kind IN ('movie','series','season','episode','audiobook','book','podcast','music','playlist','folder','unknown')),
                normalized_title          TEXT        NOT NULL,
                normalized_sort_title     TEXT,
                normalized_original_title TEXT,
                release_year              INTEGER,
                season_number             INTEGER,
                episode_number            INTEGER,
                parent_title              TEXT,
                runtime_seconds           INTEGER,
                confidence                INTEGER     NOT NULL DEFAULT 0
                    CHECK (confidence BETWEEN 0 AND 100),
                status                    TEXT        NOT NULL DEFAULT 'needs_review'
                    CHECK (status IN ('clean','warning','needs_review','unsupported')),
                issues                    JSONB       NOT NULL DEFAULT '[]',
                normalized_data           JSONB       NOT NULL DEFAULT '{}',
                normalized_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
                created_at                TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at                TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL);

        // Scoped summaries (per connector / per library) and status filtering.
        DB::statement('CREATE INDEX cat_item_norm_instance_status_idx ON connector_catalog_item_normalizations (connector_instance_id, status)');
        DB::statement('CREATE INDEX cat_item_norm_library_idx ON connector_catalog_item_normalizations (connector_library_id)');
        // Duplicate-suspect grouping: same normalized title + year + kind.
        DB::statement('CREATE INDEX cat_item_norm_duplicate_idx ON connector_catalog_item_normalizations (normalized_title, release_year, normalized_kind)');
        // Episode grouping preview.
        DB::statement('CREATE INDEX cat_item_norm_episode_idx ON connector_catalog_item_normalizations (parent_title, season_number, episode_number)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS connector_catalog_item_normalizations CASCADE');
    }
};
