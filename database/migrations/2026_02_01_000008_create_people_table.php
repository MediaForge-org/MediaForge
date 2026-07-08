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
            CREATE TABLE people (
                id          CHAR(26)    PRIMARY KEY,
                name        TEXT        NOT NULL,
                sort_name   TEXT,
                kind        TEXT        NOT NULL DEFAULT 'person'
                    CHECK (kind IN ('person','group')),
                created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX people_name_trgm ON people USING gin (name gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS people CASCADE');
    }
};
