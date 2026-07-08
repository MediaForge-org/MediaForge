<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/MediaForge/database/core-schema.md § Einstellungen.
// Holds only deltas from code defaults; every write goes through UpdateSetting.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE settings (
                key         TEXT PRIMARY KEY,
                value       JSONB       NOT NULL,
                updated_by  CHAR(26)    REFERENCES users(id) ON DELETE SET NULL,
                updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS settings CASCADE');
    }
};
