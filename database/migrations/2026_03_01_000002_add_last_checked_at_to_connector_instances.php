<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V1 Package C surfaces "last checked" separately from "last healthy": a test can
// run (last_checked_at) and fail without ever having been healthy.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE connector_instances ADD COLUMN IF NOT EXISTS last_checked_at TIMESTAMPTZ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE connector_instances DROP COLUMN IF EXISTS last_checked_at');
    }
};
