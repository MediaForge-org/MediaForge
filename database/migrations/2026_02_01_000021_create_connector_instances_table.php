<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/MediaForge/connectors/connector-sdk.md § Datenmodell.
// Non-secret config lives in `settings`; API keys/tokens are encrypted in the
// secret store and referenced by `secrets_ref` (never stored here).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE connector_instances (
                id                CHAR(26)    PRIMARY KEY,
                connector_key     TEXT        NOT NULL,
                name              TEXT        NOT NULL,
                base_url          TEXT        NOT NULL,
                settings          JSONB       NOT NULL DEFAULT '{}',
                secrets_ref       TEXT        NOT NULL,
                enabled           BOOLEAN     NOT NULL DEFAULT true,
                conflict_strategy TEXT        NOT NULL DEFAULT 'latest_wins'
                    CHECK (conflict_strategy IN ('latest_wins','mediaforge_wins','remote_wins','review')),
                health_status     TEXT        NOT NULL DEFAULT 'unknown'
                    CHECK (health_status IN ('unknown','healthy','degraded','unreachable','auth_failed')),
                health_detail     TEXT,
                last_healthy_at   TIMESTAMPTZ,
                created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE (connector_key, name)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS connector_instances CASCADE');
    }
};
