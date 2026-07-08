<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/MediaForge/database/core-schema.md § Provider-ID-Mapping (ADR-0003).
// Deliberately no FK to the target entity (polymorphic); consistency is kept by
// the Core Actions plus a nightly orphan check (DataQuality, V2).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE provider_ids (
                id            CHAR(26) PRIMARY KEY,
                entity_type   TEXT     NOT NULL,
                entity_id     CHAR(26) NOT NULL,
                provider      TEXT     NOT NULL,
                external_id   TEXT     NOT NULL,
                confidence    NUMERIC(4,3) NOT NULL DEFAULT 1.000,
                source        TEXT     NOT NULL
                    CHECK (source IN ('matcher','manual','connector','import','ai')),
                verified_at   TIMESTAMPTZ,
                last_seen_at  TIMESTAMPTZ,
                created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE (provider, external_id, entity_type, entity_id)
            )
        SQL);

        // At most ONE active mapping per entity + provider.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX provider_ids_one_per_provider
                ON provider_ids (entity_type, entity_id, provider)
        SQL);

        // Lookup is deliberately NOT unique: two entities may transiently point at
        // the same external id (duplicate suspicion signal).
        DB::statement('CREATE INDEX provider_ids_lookup ON provider_ids (provider, external_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS provider_ids CASCADE');
    }
};
