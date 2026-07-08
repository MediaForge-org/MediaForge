<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/MediaForge/database/core-schema.md § Artefakte
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE artifacts (
                id                CHAR(26)    PRIMARY KEY,
                artifact_type     TEXT        NOT NULL
                    CHECK (artifact_type IN ('m4b','cue','flac_upscale','wav_upscale','export_abs',
                                             'waveform_json','analysis_report','thumbnail','other')),
                source_type       TEXT        NOT NULL,
                source_id         CHAR(26)    NOT NULL,
                generator         TEXT        NOT NULL,
                generator_version TEXT        NOT NULL,
                input_signature   TEXT        NOT NULL,
                params            JSONB       NOT NULL DEFAULT '{}',
                path              TEXT        NOT NULL,
                size_bytes        BIGINT      NOT NULL,
                checksum          TEXT        NOT NULL,
                status            TEXT        NOT NULL DEFAULT 'active'
                    CHECK (status IN ('building','active','superseded','orphaned')),
                created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE (path)
            )
        SQL);

        DB::statement('CREATE INDEX artifacts_source_idx ON artifacts (source_type, source_id, artifact_type)');
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX artifacts_idempotency
                ON artifacts (generator, input_signature) WHERE status IN ('building','active')
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS artifacts CASCADE');
    }
};
