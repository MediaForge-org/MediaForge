<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/MediaForge/database/core-schema.md § Personen und Credits
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE credits (
                id             CHAR(26) PRIMARY KEY,
                media_item_id  CHAR(26) NOT NULL REFERENCES media_items(id) ON DELETE CASCADE,
                person_id      CHAR(26) NOT NULL REFERENCES people(id) ON DELETE RESTRICT,
                role           TEXT     NOT NULL
                    CHECK (role IN ('actor','director','writer','author','narrator',
                                    'composer','artist','producer','translator','other')),
                character_name TEXT,
                sort_index     INTEGER,
                source         TEXT     NOT NULL DEFAULT 'provider'
                    CHECK (source IN ('provider','manual','ai','import')),
                UNIQUE (media_item_id, person_id, role, character_name)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS credits CASCADE');
    }
};
