<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/MediaForge/database/core-schema.md § Katalog: media_items
// The parent-type-compatibility trigger is enforced in the Core catalog Actions
// and added with the ingest pipeline (V2); V1 does not populate this table.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE media_items (
                id             CHAR(26)    PRIMARY KEY,
                library_id     CHAR(26)    REFERENCES libraries(id) ON DELETE SET NULL,
                media_type     TEXT        NOT NULL
                    CHECK (media_type IN ('movie','show','season','episode',
                                          'audiobook','album','track',
                                          'photo_mirror','comic_series','comic_volume','ebook')),
                parent_id      CHAR(26)    REFERENCES media_items(id) ON DELETE CASCADE,
                sort_index     INTEGER,
                title          TEXT        NOT NULL,
                sort_title     TEXT,
                original_title TEXT,
                year           INTEGER,
                release_date   DATE,
                summary        TEXT,
                runtime_ms     BIGINT,
                presence       TEXT        NOT NULL DEFAULT 'present'
                    CHECK (presence IN ('present','wanted','absent')),
                metadata_locked_fields TEXT[] NOT NULL DEFAULT '{}',
                created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
                deleted_at     TIMESTAMPTZ
            )
        SQL);

        DB::statement('CREATE INDEX media_items_parent_idx ON media_items (parent_id, sort_index)');
        DB::statement('CREATE INDEX media_items_type_idx ON media_items (media_type, library_id)');
        DB::statement('CREATE INDEX media_items_title_trgm ON media_items USING gin (title gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS media_items CASCADE');
    }
};
