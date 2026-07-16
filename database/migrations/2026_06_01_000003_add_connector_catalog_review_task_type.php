<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// V2 Package A: allow a 'connector_catalog' review task. A read-only snapshot raises
// exactly one open review task per connector instance when it finds an attention
// condition (snapshot failed, truncated, unsupported provider, unhealthy, no
// selected libraries, remote unavailable). The existing partial unique index on
// (task_type, subject_type, subject_id) WHERE status IN ('open','in_review') dedupes
// these, so repeated snapshots never flood the review queue.
return new class extends Migration
{
    private const ORIGINAL = "task_type IN ('disc_episode_mapping','media_match','duplicate_suspect','chapter_proposal','unexpected_media_kind','mass_deletion','connector_conflict','metadata_conflict','connector_sync')";

    private const EXTENDED = "task_type IN ('disc_episode_mapping','media_match','duplicate_suspect','chapter_proposal','unexpected_media_kind','mass_deletion','connector_conflict','metadata_conflict','connector_sync','connector_catalog')";

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
