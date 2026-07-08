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
            CREATE TABLE edition_files (
                id         CHAR(26) PRIMARY KEY,
                edition_id CHAR(26) NOT NULL REFERENCES media_editions(id) ON DELETE CASCADE,
                file_id    CHAR(26) NOT NULL REFERENCES files(id) ON DELETE CASCADE,
                role       TEXT     NOT NULL DEFAULT 'main'
                    CHECK (role IN ('main','part','subtitle','sidecar','artwork','sample')),
                part_index INTEGER,
                created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE (edition_id, file_id)
            )
        SQL);

        DB::statement('CREATE INDEX edition_files_file_idx ON edition_files (file_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS edition_files CASCADE');
    }
};
