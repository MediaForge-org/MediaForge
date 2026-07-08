<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/MediaForge/database/core-schema.md § Editionen und Dateizuordnung
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE media_editions (
                id             CHAR(26)    PRIMARY KEY,
                media_item_id  CHAR(26)    NOT NULL REFERENCES media_items(id) ON DELETE CASCADE,
                name           TEXT        NOT NULL DEFAULT 'default',
                edition_kind   TEXT        NOT NULL DEFAULT 'release'
                    CHECK (edition_kind IN ('release','cut','remaster','language','quality','upscale')),
                is_primary     BOOLEAN     NOT NULL DEFAULT false,
                source_note    TEXT,
                created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
                deleted_at     TIMESTAMPTZ
            )
        SQL);

        // "At most one primary edition per item" — guaranteed by the database.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX media_editions_one_primary
                ON media_editions (media_item_id) WHERE is_primary AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS media_editions CASCADE');
    }
};
