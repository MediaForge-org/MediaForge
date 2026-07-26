<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V2 Package D: allow a 'media_import_plan' review task. An import dry run raises
// at most ONE open task per connector instance summarising why a later import is
// not safe yet (blocked items, duplicate suspects, weak metadata, a missing parent,
// an unknown kind, a missing title, a truncated plan). The reason codes and their
// counts live in the evidence rather than in one task per broken item, and the
// existing partial unique index on (task_type, subject_type, subject_id) WHERE
// status IN ('open','in_review') dedupes repeated dry runs. A clean dry run
// dismisses the lingering task, so the queue heals itself.
return new class extends Migration
{
    private const ORIGINAL = "task_type IN ('disc_episode_mapping','media_match','duplicate_suspect','chapter_proposal','unexpected_media_kind','mass_deletion','connector_conflict','metadata_conflict','connector_sync','connector_catalog','catalog_normalization')";

    private const EXTENDED = "task_type IN ('disc_episode_mapping','media_match','duplicate_suspect','chapter_proposal','unexpected_media_kind','mass_deletion','connector_conflict','metadata_conflict','connector_sync','connector_catalog','catalog_normalization','media_import_plan')";

    public function up(): void
    {
        DB::statement('ALTER TABLE review_tasks DROP CONSTRAINT IF EXISTS review_tasks_task_type_check');
        DB::statement('ALTER TABLE review_tasks ADD CONSTRAINT review_tasks_task_type_check CHECK ('.self::EXTENDED.')');
    }

    public function down(): void
    {
        // Drop any row using the new type first, otherwise the tighter CHECK fails.
        DB::statement("DELETE FROM review_tasks WHERE task_type = 'media_import_plan'");
        DB::statement('ALTER TABLE review_tasks DROP CONSTRAINT IF EXISTS review_tasks_task_type_check');
        DB::statement('ALTER TABLE review_tasks ADD CONSTRAINT review_tasks_task_type_check CHECK ('.self::ORIGINAL.')');
    }
};
