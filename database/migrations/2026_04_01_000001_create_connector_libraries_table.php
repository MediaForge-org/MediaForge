<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V1 Package D: connector library discovery. One row per library exposed by a
// configured connector (Jellyfin/Audiobookshelf). This is library-LEVEL metadata
// only — no media items, no secrets, no raw API payloads. `is_enabled` marks a
// library the operator selected for a LATER sync (V2); `discovery_status` lets a
// vanished library be flagged 'missing' instead of being deleted.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE connector_libraries (
                id                    CHAR(26)    PRIMARY KEY,
                connector_instance_id CHAR(26)    NOT NULL REFERENCES connector_instances(id) ON DELETE CASCADE,
                provider_key          TEXT        NOT NULL,
                external_id           TEXT        NOT NULL,
                name                  TEXT        NOT NULL,
                collection_type       TEXT,
                path                  TEXT,
                is_enabled            BOOLEAN     NOT NULL DEFAULT false,
                discovery_status      TEXT        NOT NULL DEFAULT 'present'
                    CHECK (discovery_status IN ('present','missing')),
                last_seen_at          TIMESTAMPTZ,
                metadata              JSONB       NOT NULL DEFAULT '{}',
                created_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE (connector_instance_id, external_id)
            )
        SQL);

        DB::statement('CREATE INDEX connector_libraries_instance_idx ON connector_libraries (connector_instance_id, discovery_status)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS connector_libraries CASCADE');
    }
};
