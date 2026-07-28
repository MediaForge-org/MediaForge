<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V2 Package E: allow a 'media_import_execution' review task. An internal import
// raises at most ONE open task per plan, summarising what it refused to import and
// why (a missing or ambiguous parent, lines still needing review, duplicates it
// declined to merge, an existing mapping that conflicted, failed lines). The reason
// codes and their counts live in the evidence rather than in one task per item, and
// the existing partial unique index on (task_type, subject_type, subject_id) WHERE
// status IN ('open','in_review') dedupes it. A later clean execution of the same
// plan supersedes the previous task, so the queue heals itself.
return new class extends Migration
{
    private const ORIGINAL = "task_type IN ('disc_episode_mapping','media_match','duplicate_suspect','chapter_proposal','unexpected_media_kind','mass_deletion','connector_conflict','metadata_conflict','connector_sync','connector_catalog','catalog_normalization','media_import_plan')";

    private const EXTENDED = "task_type IN ('disc_episode_mapping','media_match','duplicate_suspect','chapter_proposal','unexpected_media_kind','mass_deletion','connector_conflict','metadata_conflict','connector_sync','connector_catalog','catalog_normalization','media_import_plan','media_import_execution')";

    public function up(): void
    {
        DB::statement('ALTER TABLE review_tasks DROP CONSTRAINT IF EXISTS review_tasks_task_type_check');
        DB::statement('ALTER TABLE review_tasks ADD CONSTRAINT review_tasks_task_type_check CHECK ('.self::EXTENDED.')');
    }

    public function down(): void
    {
        // Drop any row using the new type first, otherwise the tighter CHECK fails.
        DB::statement("DELETE FROM review_tasks WHERE task_type = 'media_import_execution'");
        DB::statement('ALTER TABLE review_tasks DROP CONSTRAINT IF EXISTS review_tasks_task_type_check');
        DB::statement('ALTER TABLE review_tasks ADD CONSTRAINT review_tasks_task_type_check CHECK ('.self::ORIGINAL.')');
    }
};
