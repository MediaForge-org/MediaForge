<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Users, sessions and password-reset tokens.
 *
 * Deviations from the Laravel default (per docs/MediaForge/database/core-schema.md
 * and architecture/security.md): ULID CHAR(26) primary key, `password_hash`
 * column, global role enum (admin|manager|member), per-user theme preference
 * (design-system.md), soft deletes. Argon2id hashing is configured in
 * config/hashing.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE users (
                id               CHAR(26)    PRIMARY KEY,
                name             TEXT        NOT NULL,
                email            TEXT        NOT NULL,
                password_hash    TEXT        NOT NULL,
                role             TEXT        NOT NULL DEFAULT 'member'
                    CHECK (role IN ('admin','manager','member')),
                theme_preference TEXT        NOT NULL DEFAULT 'system'
                    CHECK (theme_preference IN ('light','dark','system')),
                remember_token   VARCHAR(100),
                created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
                deleted_at       TIMESTAMPTZ,
                UNIQUE (email)
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE password_reset_tokens (
                email      TEXT PRIMARY KEY,
                token      TEXT NOT NULL,
                created_at TIMESTAMPTZ
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE sessions (
                id            TEXT PRIMARY KEY,
                user_id       CHAR(26),
                ip_address    VARCHAR(45),
                user_agent    TEXT,
                payload       TEXT    NOT NULL,
                last_activity INTEGER NOT NULL
            )
        SQL);
        DB::statement('CREATE INDEX sessions_user_id_idx ON sessions (user_id)');
        DB::statement('CREATE INDEX sessions_last_activity_idx ON sessions (last_activity)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS sessions CASCADE');
        DB::statement('DROP TABLE IF EXISTS password_reset_tokens CASCADE');
        DB::statement('DROP TABLE IF EXISTS users CASCADE');
    }
};
