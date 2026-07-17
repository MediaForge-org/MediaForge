<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V2 Package C: allow a 'catalog_normalization' review task. Normalizing the
// captured catalog raises exactly ONE open task per connector instance summarising
// the data-quality issues it found (unknown kind, missing title/episode/season,
// weak metadata, duplicate suspects) — the issue codes live in the task evidence
// rather than in a task type per problem. The existing partial unique index on
// (task_type, subject_type, subject_id) WHERE status IN ('open','in_review') dedupes
// it, so repeated normalization runs never flood the queue. It is a distinct type
// from 'connector_catalog' so a snapshot task and a normalization task can coexist.
return new class extends Migration
{
    private const ORIGINAL = "task_type IN ('disc_episode_mapping','media_match','duplicate_suspect','chapter_proposal','unexpected_media_kind','mass_deletion','connector_conflict','metadata_conflict','connector_sync','connector_catalog')";

    private const EXTENDED = "task_type IN ('disc_episode_mapping','media_match','duplicate_suspect','chapter_proposal','unexpected_media_kind','mass_deletion','connector_conflict','metadata_conflict','connector_sync','connector_catalog','catalog_normalization')";

    public function up(): void
    {
        DB::statement('ALTER TABLE review_tasks DROP CONSTRAINT IF EXISTS review_tasks_task_type_check');
        DB::statement('ALTER TABLE review_tasks ADD CONSTRAINT review_tasks_task_type_check CHECK ('.self::EXTENDED.')');
    }

    public function down(): void
    {
        // Drop any row using the new type first, otherwise the tighter CHECK fails.
        DB::statement("DELETE FROM review_tasks WHERE task_type = 'catalog_normalization'");
        DB::statement('ALTER TABLE review_tasks DROP CONSTRAINT IF EXISTS review_tasks_task_type_check');
        DB::statement('ALTER TABLE review_tasks ADD CONSTRAINT review_tasks_task_type_check CHECK ('.self::ORIGINAL.')');
    }
};
