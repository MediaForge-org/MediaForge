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
            CREATE TABLE tags (
                id         CHAR(26) PRIMARY KEY,
                name       TEXT NOT NULL,
                namespace  TEXT NOT NULL DEFAULT 'user',
                UNIQUE (namespace, name)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS tags CASCADE');
    }
};
