<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/MediaForge/database/core-schema.md § Dateien
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE files (
                id                   CHAR(26)    PRIMARY KEY,
                library_id           CHAR(26)    NOT NULL REFERENCES libraries(id) ON DELETE CASCADE,
                path                 TEXT        NOT NULL,
                is_container_dir     BOOLEAN     NOT NULL DEFAULT false,
                size_bytes           BIGINT      NOT NULL,
                mtime                TIMESTAMPTZ NOT NULL,
                inode_key            TEXT,
                quick_hash           TEXT,
                content_hash         TEXT,
                status               TEXT        NOT NULL DEFAULT 'present'
                    CHECK (status IN ('present','missing','removed')),
                missing_since        TIMESTAMPTZ,
                candidate_type       TEXT
                    CHECK (candidate_type IN ('video','disc_image','audiobook_folder','audio',
                                              'image','comic','ebook','subtitle','sidecar','unknown')),
                candidate_confidence NUMERIC(4,3),
                analysis_status      TEXT        NOT NULL DEFAULT 'pending'
                    CHECK (analysis_status IN ('pending','running','analyzed','failed','skipped')),
                analysis_error       TEXT,
                created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE (library_id, path)
            )
        SQL);

        DB::statement('CREATE INDEX files_content_hash_idx ON files (content_hash) WHERE content_hash IS NOT NULL');
        DB::statement('CREATE INDEX files_status_idx ON files (library_id, status)');
        DB::statement('CREATE INDEX files_candidate_idx ON files (candidate_type, analysis_status)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS files CASCADE');
    }
};
