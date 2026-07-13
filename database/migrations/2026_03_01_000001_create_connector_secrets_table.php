<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Encrypted secret store for connector credentials (API keys/tokens). Referenced
// by connector_instances.secrets_ref; the ciphertext is a Laravel-encrypted
// string (APP_KEY). Plaintext secrets never live in connector_instances, the
// audit log, application logs or the frontend.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE connector_secrets (
                secrets_ref TEXT        PRIMARY KEY,
                ciphertext  TEXT        NOT NULL,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS connector_secrets CASCADE');
    }
};
