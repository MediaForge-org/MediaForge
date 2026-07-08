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
            CREATE TABLE episode_details (
                media_item_id   CHAR(26) PRIMARY KEY REFERENCES media_items(id) ON DELETE CASCADE,
                season_number   INTEGER  NOT NULL,
                episode_number  INTEGER  NOT NULL,
                absolute_number INTEGER,
                air_date        DATE
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS episode_details CASCADE');
    }
};
