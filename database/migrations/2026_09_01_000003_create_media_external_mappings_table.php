<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V2 Package E: the link between an internal MediaForge media item and the external
// item a connector reported.
//
// This table is the IDEMPOTENCY BACKBONE of the internal import: the unique
// (connector_instance_id, external_id) means the same external item can only ever
// own one internal media item, so re-running the same plan links the existing item
// instead of creating a second one.
//
// Why not `provider_ids`? That foundation table is polymorphic and provider-scoped
// ((entity_type, entity_id, provider) unique); it cannot express "one media item per
// connector INSTANCE + external id", and it carries no FKs back to the captured
// catalog item / normalization that justified the import. Provenance is the point
// here, so V2 E uses a dedicated, fully-constrained table.
//
// It stores identifiers and timestamps only: no secrets, no tokens, no raw API
// payloads and NO file path of any kind.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE media_external_mappings (
                id                                      CHAR(26)    PRIMARY KEY,
                media_item_id                           CHAR(26)    NOT NULL REFERENCES media_items(id) ON DELETE CASCADE,
                connector_instance_id                   CHAR(26)    NOT NULL REFERENCES connector_instances(id) ON DELETE CASCADE,
                connector_library_id                    CHAR(26)    REFERENCES connector_libraries(id) ON DELETE SET NULL,
                connector_catalog_item_id               CHAR(26)    REFERENCES connector_catalog_items(id) ON DELETE SET NULL,
                connector_catalog_item_normalization_id CHAR(26)    REFERENCES connector_catalog_item_normalizations(id) ON DELETE SET NULL,
                external_id                             TEXT        NOT NULL,
                external_parent_id                      TEXT,
                source_type                             TEXT        NOT NULL DEFAULT 'unknown'
                    CHECK (source_type IN ('jellyfin','audiobookshelf','unknown')),
                source_kind                             TEXT,
                imported_at                             TIMESTAMPTZ NOT NULL DEFAULT now(),
                created_at                              TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at                              TIMESTAMPTZ NOT NULL DEFAULT now(),
                -- One internal item per external item, per connector instance. This
                -- is what makes a repeated import a link instead of a duplicate.
                CONSTRAINT media_external_mappings_unique_external UNIQUE (connector_instance_id, external_id)
            )
        SQL);

        DB::statement('CREATE INDEX media_external_mappings_item_idx ON media_external_mappings (media_item_id)');
        DB::statement('CREATE INDEX media_external_mappings_instance_idx ON media_external_mappings (connector_instance_id)');
        DB::statement('CREATE INDEX media_external_mappings_library_idx ON media_external_mappings (connector_library_id)');
        DB::statement('CREATE INDEX media_external_mappings_catalog_item_idx ON media_external_mappings (connector_catalog_item_id)');
        DB::statement('CREATE INDEX media_external_mappings_external_idx ON media_external_mappings (external_id)');
        DB::statement('CREATE INDEX media_external_mappings_source_idx ON media_external_mappings (source_type)');
        // Parent lookup during import: "which internal item owns this external parent?"
        DB::statement('CREATE INDEX media_external_mappings_parent_idx ON media_external_mappings (connector_instance_id, external_parent_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS media_external_mappings CASCADE');
    }
};
