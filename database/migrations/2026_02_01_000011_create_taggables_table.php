<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/MediaForge/database/core-schema.md § Tags
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE taggables (
                tag_id        CHAR(26) NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
                taggable_type TEXT     NOT NULL,
                taggable_id   CHAR(26) NOT NULL,
                source        TEXT     NOT NULL DEFAULT 'manual'
                    CHECK (source IN ('manual','provider','rule','ai','import')),
                PRIMARY KEY (tag_id, taggable_type, taggable_id)
            )
        SQL);

        DB::statement('CREATE INDEX taggables_subject_idx ON taggables (taggable_type, taggable_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS taggables CASCADE');
    }
};
