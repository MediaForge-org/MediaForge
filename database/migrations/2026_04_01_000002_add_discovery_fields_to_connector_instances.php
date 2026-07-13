<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Discovery bookkeeping on the connector instance: when libraries were last
// discovered, and the sanitized error from the last failed attempt (never a
// token). The library count itself is derived from connector_libraries.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE connector_instances ADD COLUMN IF NOT EXISTS libraries_discovered_at TIMESTAMPTZ');
        DB::statement('ALTER TABLE connector_instances ADD COLUMN IF NOT EXISTS last_discovery_error TEXT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE connector_instances DROP COLUMN IF EXISTS libraries_discovered_at');
        DB::statement('ALTER TABLE connector_instances DROP COLUMN IF EXISTS last_discovery_error');
    }
};
