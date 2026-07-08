<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/MediaForge/database/core-schema.md § Historie: watch_state_events
// Monthly range partitioning by occurred_at (retention via DROP PARTITION).
// Postgres requires the partition key in the primary key, so PK is
// (id, occurred_at); id remains globally unique (ULID). A DEFAULT partition
// guarantees inserts always land somewhere; a scheduler job pre-creates monthly
// partitions in V2.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE watch_state_events (
                id             CHAR(26)    NOT NULL,
                user_id        CHAR(26)    NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                media_item_id  CHAR(26)    NOT NULL REFERENCES media_items(id) ON DELETE CASCADE,
                event_type     TEXT        NOT NULL
                    CHECK (event_type IN ('progress','watched','unwatched','abandoned','reset')),
                position_ms    BIGINT,
                source         TEXT        NOT NULL,
                context        JSONB       NOT NULL DEFAULT '{}',
                occurred_at    TIMESTAMPTZ NOT NULL,
                recorded_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
                PRIMARY KEY (id, occurred_at)
            ) PARTITION BY RANGE (occurred_at)
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX watch_state_events_subject_idx
                ON watch_state_events (user_id, media_item_id, occurred_at DESC)
        SQL);

        // Catch-all partition so writes never fail before the monthly-partition
        // automation lands.
        DB::statement('CREATE TABLE watch_state_events_default PARTITION OF watch_state_events DEFAULT');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS watch_state_events CASCADE');
    }
};
