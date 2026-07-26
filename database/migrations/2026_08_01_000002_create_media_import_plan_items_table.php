<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V2 Package D: one planned line of an import dry run.
//
// A row says "IF an import ran, this external item would become X" — it is never
// the thing itself. No media_item / media_edition / media_file is created from it
// in V2 D, no file operation is planned or performed, and no match is accepted.
// `target_*` fields are logical identities (title/year/season/episode + a hashed
// key), deliberately NOT file paths: MediaForge plans no path here, so there is
// nothing to move, copy, delete or rename. `reasons` holds stable sanitized codes
// and `source_snapshot` a minimal display echo — never secrets, tokens, raw API
// payloads, stack traces or local paths.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE media_import_plan_items (
                id                                    CHAR(26)    PRIMARY KEY,
                media_import_plan_id                  CHAR(26)    NOT NULL REFERENCES media_import_plans(id) ON DELETE CASCADE,
                connector_catalog_item_id             CHAR(26)    REFERENCES connector_catalog_items(id) ON DELETE SET NULL,
                connector_catalog_item_normalization_id CHAR(26)  REFERENCES connector_catalog_item_normalizations(id) ON DELETE SET NULL,
                connector_instance_id                 CHAR(26)    NOT NULL REFERENCES connector_instances(id) ON DELETE CASCADE,
                connector_library_id                  CHAR(26)    REFERENCES connector_libraries(id) ON DELETE SET NULL,
                planned_kind                          TEXT        NOT NULL DEFAULT 'unknown'
                    CHECK (planned_kind IN ('movie','series','season','episode','audiobook','book','podcast','music','playlist','folder','unknown')),
                planned_action                        TEXT        NOT NULL DEFAULT 'needs_review'
                    CHECK (planned_action IN ('create_media','create_container','attach_to_parent','skip_unsupported','skip_duplicate','needs_review','blocked')),
                status                                TEXT        NOT NULL DEFAULT 'needs_review'
                    CHECK (status IN ('ready','warning','blocked','skipped','needs_review')),
                target_key                            TEXT,
                target_title                          TEXT        NOT NULL,
                target_parent_key                     TEXT,
                target_year                           INTEGER,
                target_season_number                  INTEGER,
                target_episode_number                 INTEGER,
                confidence                            INTEGER     NOT NULL DEFAULT 0
                    CHECK (confidence BETWEEN 0 AND 100),
                reasons                               JSONB       NOT NULL DEFAULT '[]',
                source_snapshot                       JSONB       NOT NULL DEFAULT '{}',
                created_at                            TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at                            TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL);

        // The plan detail page groups by status and lists deterministically.
        DB::statement('CREATE INDEX media_import_plan_items_plan_idx ON media_import_plan_items (media_import_plan_id, status, planned_kind)');
        DB::statement('CREATE INDEX media_import_plan_items_plan_order_idx ON media_import_plan_items (media_import_plan_id, id)');
        DB::statement('CREATE INDEX media_import_plan_items_instance_idx ON media_import_plan_items (connector_instance_id)');
        DB::statement('CREATE INDEX media_import_plan_items_library_idx ON media_import_plan_items (connector_library_id)');
        // Grouping the same logical target across connectors/libraries.
        DB::statement('CREATE INDEX media_import_plan_items_target_idx ON media_import_plan_items (target_key)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS media_import_plan_items CASCADE');
    }
};
