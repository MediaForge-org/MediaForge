<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/MediaForge/database/core-schema.md § Bibliotheken
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE libraries (
                id                     CHAR(26)    PRIMARY KEY,
                name                   TEXT        NOT NULL,
                root_path              TEXT        NOT NULL,
                media_kind             TEXT        NOT NULL
                    CHECK (media_kind IN ('video','audiobook','music','photo','comic','ebook','mixed')),
                scan_enabled           BOOLEAN     NOT NULL DEFAULT true,
                scan_interval_min      INTEGER     NOT NULL DEFAULT 720,
                last_scan_started_at   TIMESTAMPTZ,
                last_scan_completed_at TIMESTAMPTZ,
                settings               JSONB       NOT NULL DEFAULT '{}',
                created_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE (root_path)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS libraries CASCADE');
    }
};
