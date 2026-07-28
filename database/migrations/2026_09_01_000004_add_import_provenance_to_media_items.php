<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V2 Package E: make the canonical `media_items` table importable.
//
// `media_items` already exists as part of the V1 foundation
// (docs/MediaForge/database/core-schema.md § Katalog), created empty on purpose —
// its own migration says the ingest pipeline arrives in V2. V2 E IS that pipeline,
// so it POPULATES the canonical table rather than introducing a rival one. This
// migration only adds what an import needs and the foundation did not have:
//
//   source                          where the row came from (import vs. a human)
//   created_by_import_execution_id  which execution created it (provenance)
//   metadata                        small sanitized hints, never a payload
//   season_number / episode_number  the series hierarchy the foundation encoded
//                                   only as a single `sort_index`
//
// Everything else is reused as-is: media_type (kind), title, sort_title, year,
// runtime_ms, parent_id, presence.
//
// It also pins the plausibility rules the import relies on. NO path column is added
// — not now and not by V2 E at all: the internal import stores no file location,
// because it touches no file.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE media_items
                ADD COLUMN source TEXT NOT NULL DEFAULT 'manual'
                    CHECK (source IN ('connector_import','manual')),
                ADD COLUMN created_by_import_execution_id CHAR(26)
                    REFERENCES media_import_executions(id) ON DELETE SET NULL,
                ADD COLUMN metadata JSONB NOT NULL DEFAULT '{}',
                ADD COLUMN season_number INTEGER,
                ADD COLUMN episode_number INTEGER
        SQL);

        // Plausibility. A nullable field stays nullable — but a value that IS set
        // has to make sense, so a broken import fails loudly instead of quietly
        // storing a year of 12 or a negative episode number.
        DB::statement('ALTER TABLE media_items ADD CONSTRAINT media_items_year_check CHECK (year IS NULL OR (year BETWEEN 1800 AND 2200))');
        DB::statement('ALTER TABLE media_items ADD CONSTRAINT media_items_runtime_check CHECK (runtime_ms IS NULL OR runtime_ms > 0)');
        DB::statement('ALTER TABLE media_items ADD CONSTRAINT media_items_season_number_check CHECK (season_number IS NULL OR season_number >= 0)');
        DB::statement('ALTER TABLE media_items ADD CONSTRAINT media_items_episode_number_check CHECK (episode_number IS NULL OR episode_number >= 0)');

        DB::statement('CREATE INDEX media_items_source_idx ON media_items (source)');
        DB::statement('CREATE INDEX media_items_execution_idx ON media_items (created_by_import_execution_id)');
        DB::statement('CREATE INDEX media_items_hierarchy_idx ON media_items (parent_id, season_number, episode_number)');
        DB::statement('CREATE INDEX media_items_sort_title_idx ON media_items (sort_title)');
        DB::statement('CREATE INDEX media_items_year_idx ON media_items (year)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS media_items_source_idx');
        DB::statement('DROP INDEX IF EXISTS media_items_execution_idx');
        DB::statement('DROP INDEX IF EXISTS media_items_hierarchy_idx');
        DB::statement('DROP INDEX IF EXISTS media_items_sort_title_idx');
        DB::statement('DROP INDEX IF EXISTS media_items_year_idx');

        DB::statement('ALTER TABLE media_items DROP CONSTRAINT IF EXISTS media_items_year_check');
        DB::statement('ALTER TABLE media_items DROP CONSTRAINT IF EXISTS media_items_runtime_check');
        DB::statement('ALTER TABLE media_items DROP CONSTRAINT IF EXISTS media_items_season_number_check');
        DB::statement('ALTER TABLE media_items DROP CONSTRAINT IF EXISTS media_items_episode_number_check');

        DB::statement(<<<'SQL'
            ALTER TABLE media_items
                DROP COLUMN IF EXISTS source,
                DROP COLUMN IF EXISTS created_by_import_execution_id,
                DROP COLUMN IF EXISTS metadata,
                DROP COLUMN IF EXISTS season_number,
                DROP COLUMN IF EXISTS episode_number
        SQL);
    }
};
