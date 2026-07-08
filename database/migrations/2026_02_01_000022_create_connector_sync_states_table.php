<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/MediaForge/connectors/connector-sdk.md § Datenmodell.
// The table exists from the foundation; the bidirectional sync runtime that
// fills it (cursors, outbox, ingest log) is V2.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE connector_sync_states (
                id                   CHAR(26)    PRIMARY KEY,
                instance_id          CHAR(26)    NOT NULL REFERENCES connector_instances(id) ON DELETE CASCADE,
                stream               TEXT        NOT NULL,
                direction            TEXT        NOT NULL CHECK (direction IN ('ingest','egress')),
                cursor               JSONB,
                last_run_at          TIMESTAMPTZ,
                last_success_at      TIMESTAMPTZ,
                consecutive_failures INTEGER     NOT NULL DEFAULT 0,
                backoff_until        TIMESTAMPTZ,
                stats                JSONB       NOT NULL DEFAULT '{}',
                UNIQUE (instance_id, stream, direction)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS connector_sync_states CASCADE');
    }
};
