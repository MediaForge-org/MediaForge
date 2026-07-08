<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Append-only audit trail. Every business write goes through an AuditableAction
// which records one row here in the same transaction (docs/MediaForge/modules/
// audit.md; architecture/overview.md Rule 6). The actor is resolved by
// Actor::current() (user | job | connector | ai | system). Secrets are masked by
// the recorder denylist before they reach `changes`/`context`.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE audit_log (
                id             CHAR(26)    PRIMARY KEY,
                correlation_id TEXT,
                actor_type     TEXT        NOT NULL
                    CHECK (actor_type IN ('user','job','connector','ai','system')),
                actor_id       CHAR(26),
                actor_label    TEXT        NOT NULL,
                action         TEXT        NOT NULL,
                subject_type   TEXT        NOT NULL,
                subject_id     TEXT        NOT NULL, -- ULID for entities, or a natural key (e.g. a setting key)
                changes        JSONB       NOT NULL DEFAULT '{}',
                context        JSONB       NOT NULL DEFAULT '{}',
                created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX audit_log_subject_idx ON audit_log (subject_type, subject_id, created_at DESC)');
        DB::statement('CREATE INDEX audit_log_actor_idx ON audit_log (actor_type, actor_id)');
        DB::statement('CREATE INDEX audit_log_correlation_idx ON audit_log (correlation_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS audit_log CASCADE');
    }
};
