<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/MediaForge/database/core-schema.md § Satellitentabellen
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE audiobook_details (
                media_item_id   CHAR(26) PRIMARY KEY REFERENCES media_items(id) ON DELETE CASCADE,
                narrator_note   TEXT,
                abridged        BOOLEAN,
                series_name     TEXT,
                series_position NUMERIC(6,2)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS audiobook_details CASCADE');
    }
};
