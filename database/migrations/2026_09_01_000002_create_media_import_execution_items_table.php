<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V2 Package E: what the internal import did with ONE plan line.
//
// Every plan line an execution looked at gets exactly one row here — created,
// linked to an already-imported item, skipped (with a reason), or failed. This is
// the provenance trail: it explains why something was NOT imported just as clearly
// as why it was. `reason_codes` holds stable sanitized enum codes only, never a
// secret, a raw API payload, a stack trace or a local path.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE media_import_execution_items (
                id                        CHAR(26)    PRIMARY KEY,
                media_import_execution_id CHAR(26)    NOT NULL REFERENCES media_import_executions(id) ON DELETE CASCADE,
                media_import_plan_item_id CHAR(26)    REFERENCES media_import_plan_items(id) ON DELETE SET NULL,
                media_item_id             CHAR(26)    REFERENCES media_items(id) ON DELETE SET NULL,
                connector_catalog_item_id CHAR(26)    REFERENCES connector_catalog_items(id) ON DELETE SET NULL,
                title                     TEXT        NOT NULL DEFAULT '',
                media_kind                TEXT        NOT NULL DEFAULT 'unknown',
                action                    TEXT        NOT NULL
                    CHECK (action IN ('created','linked_existing','skipped_not_ready','skipped_unsupported','skipped_duplicate','failed')),
                status                    TEXT        NOT NULL
                    CHECK (status IN ('completed','skipped','failed')),
                reason_codes              JSONB       NOT NULL DEFAULT '[]',
                created_at                TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at                TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX media_import_execution_items_exec_idx ON media_import_execution_items (media_import_execution_id, status, action)');
        DB::statement('CREATE INDEX media_import_execution_items_order_idx ON media_import_execution_items (media_import_execution_id, id)');
        DB::statement('CREATE INDEX media_import_execution_items_plan_item_idx ON media_import_execution_items (media_import_plan_item_id)');
        DB::statement('CREATE INDEX media_import_execution_items_media_item_idx ON media_import_execution_items (media_item_id)');
        DB::statement('CREATE INDEX media_import_execution_items_catalog_item_idx ON media_import_execution_items (connector_catalog_item_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS media_import_execution_items CASCADE');
    }
};
