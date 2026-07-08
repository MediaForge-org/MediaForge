<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/MediaForge/database/core-schema.md § Benutzer und Watch-State
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE user_watch_states (
                id              CHAR(26)    PRIMARY KEY,
                user_id         CHAR(26)    NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                media_item_id   CHAR(26)    NOT NULL REFERENCES media_items(id) ON DELETE CASCADE,
                status          TEXT        NOT NULL
                    CHECK (status IN ('in_progress','watched','abandoned')),
                position_ms     BIGINT,
                duration_ms     BIGINT,
                play_count      INTEGER     NOT NULL DEFAULT 0,
                first_played_at TIMESTAMPTZ,
                last_played_at  TIMESTAMPTZ,
                watched_at      TIMESTAMPTZ,
                source          TEXT        NOT NULL
                    CHECK (source IN ('manual','player','connector:jellyfin','connector:abs',
                                      'connector:stash','external_player','import')),
                created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE (user_id, media_item_id)
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX user_watch_states_user_idx
                ON user_watch_states (user_id, status, last_played_at DESC)
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS user_watch_states CASCADE');
    }
};
