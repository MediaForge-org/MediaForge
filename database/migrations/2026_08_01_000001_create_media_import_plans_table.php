<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V2 Package D: one import DRY RUN over the normalized connector catalog.
//
// A plan describes what a LATER import would create. It is a PLAN TABLE, not a
// media-library table: it creates no media_items, no media_editions and no
// media_files, it touches no file, it changes nothing on Jellyfin/Audiobookshelf
// and it accepts no match. `summary` holds sanitized counts and reason codes only
// — never a secret, a token, a raw API payload, a stack trace or a local path.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE media_import_plans (
                id                    CHAR(26)    PRIMARY KEY,
                scope_type            TEXT        NOT NULL DEFAULT 'all'
                    CHECK (scope_type IN ('all','connector','library')),
                connector_instance_id CHAR(26)    REFERENCES connector_instances(id) ON DELETE CASCADE,
                connector_library_id  CHAR(26)    REFERENCES connector_libraries(id) ON DELETE SET NULL,
                status                TEXT        NOT NULL DEFAULT 'empty'
                    CHECK (status IN ('ready','warnings','blocked','empty')),
                source_item_count     INTEGER     NOT NULL DEFAULT 0 CHECK (source_item_count >= 0),
                planned_item_count    INTEGER     NOT NULL DEFAULT 0 CHECK (planned_item_count >= 0),
                ready_count           INTEGER     NOT NULL DEFAULT 0 CHECK (ready_count >= 0),
                warning_count         INTEGER     NOT NULL DEFAULT 0 CHECK (warning_count >= 0),
                blocked_count         INTEGER     NOT NULL DEFAULT 0 CHECK (blocked_count >= 0),
                skipped_count         INTEGER     NOT NULL DEFAULT 0 CHECK (skipped_count >= 0),
                review_count          INTEGER     NOT NULL DEFAULT 0 CHECK (review_count >= 0),
                duplicate_count       INTEGER     NOT NULL DEFAULT 0 CHECK (duplicate_count >= 0),
                unsupported_count     INTEGER     NOT NULL DEFAULT 0 CHECK (unsupported_count >= 0),
                summary               JSONB       NOT NULL DEFAULT '{}',
                created_by            TEXT,
                created_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
                -- A scoped plan must name what it is scoped to; a global one must not.
                CONSTRAINT media_import_plans_scope_check CHECK (
                    (scope_type = 'all'       AND connector_instance_id IS NULL AND connector_library_id IS NULL)
                 OR (scope_type = 'connector' AND connector_instance_id IS NOT NULL AND connector_library_id IS NULL)
                 OR (scope_type = 'library'   AND connector_instance_id IS NOT NULL AND connector_library_id IS NOT NULL)
                )
            )
        SQL);

        // The /imports list: newest first, optionally narrowed by status or scope.
        DB::statement('CREATE INDEX media_import_plans_created_idx ON media_import_plans (created_at DESC, id DESC)');
        DB::statement('CREATE INDEX media_import_plans_status_idx ON media_import_plans (status, created_at DESC)');
        DB::statement('CREATE INDEX media_import_plans_scope_idx ON media_import_plans (scope_type, connector_instance_id, connector_library_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS media_import_plans CASCADE');
    }
};
