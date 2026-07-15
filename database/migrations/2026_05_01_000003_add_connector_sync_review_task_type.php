<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V1 Package F: allow a 'connector_sync' review task. A dry run raises exactly one
// open review task per connector instance when it finds an attention condition
// (not tested, unhealthy, nothing discovered/selected, a selected library gone,
// stale discovery). The existing partial unique index on
// (task_type, subject_type, subject_id) WHERE status IN ('open','in_review')
// dedupes these, so repeated dry runs never flood the review queue.
return new class extends Migration
{
    private const ORIGINAL = "task_type IN ('disc_episode_mapping','media_match','duplicate_suspect','chapter_proposal','unexpected_media_kind','mass_deletion','connector_conflict','metadata_conflict')";

    private const EXTENDED = "task_type IN ('disc_episode_mapping','media_match','duplicate_suspect','chapter_proposal','unexpected_media_kind','mass_deletion','connector_conflict','metadata_conflict','connector_sync')";

    public function up(): void
    {
        DB::statement('ALTER TABLE review_tasks DROP CONSTRAINT IF EXISTS review_tasks_task_type_check');
        DB::statement('ALTER TABLE review_tasks ADD CONSTRAINT review_tasks_task_type_check CHECK ('.self::EXTENDED.')');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE review_tasks DROP CONSTRAINT IF EXISTS review_tasks_task_type_check');
        DB::statement('ALTER TABLE review_tasks ADD CONSTRAINT review_tasks_task_type_check CHECK ('.self::ORIGINAL.')');
    }
};
