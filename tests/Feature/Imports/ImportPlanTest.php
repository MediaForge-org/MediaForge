<?php

declare(strict_types=1);

use App\Connectors\Sdk\Actions\CreateMediaImportPlan;
use App\Connectors\Sdk\Import\ImportPlanScope;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Models\MediaImportPlan;
use App\Connectors\Sdk\Models\MediaImportPlanItem;
use App\Core\Audit\AuditLog;
use App\Core\Media\MediaEdition;
use App\Core\Media\MediaFile;
use App\Core\Media\MediaItem;
use App\Core\Review\ReviewTask;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    // Planning must never touch the network.
    Http::preventStrayRequests();
});

// seedNormalizationConnector() / seedNormalizationItem() / normalizeConnector() /
// createImportPlan() / planItemFor() live in tests/Pest.php.

/* ---------------------------------------------------------------------------
 | Plan creation
 * ------------------------------------------------------------------------- */

test('a dry run creates a plan from the normalized catalog items', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    seedNormalizationItem($instance, $library, 'jf-2', 'Arrival', 'movie', ['year' => 2016]);
    normalizeConnector($instance);

    $plan = createImportPlan();

    expect($plan->scope_type)->toBe('all')
        ->and($plan->source_item_count)->toBe(2)
        ->and($plan->planned_item_count)->toBe(2)
        ->and($plan->ready_count)->toBe(2)
        ->and($plan->status)->toBe('ready')
        ->and(MediaImportPlanItem::query()->where('media_import_plan_id', $plan->id)->count())->toBe(2);

    Http::assertNothingSent();
});

test('a clean movie becomes a ready create_media plan item', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix', 'movie', ['year' => 1999]);
    normalizeConnector($instance);

    $item = planItemFor(createImportPlan(), 'jf-1');

    expect($item)->not->toBeNull()
        ->and($item->planned_kind)->toBe('movie')
        ->and($item->planned_action)->toBe('create_media')
        ->and($item->status)->toBe('ready')
        ->and($item->target_title)->toBe('The Matrix')
        ->and($item->target_year)->toBe(1999)
        ->and($item->target_key)->not->toBeNull()
        ->and($item->reasons)->toContain('ready_to_import');
});

test('a movie without a release year is planned as a warning, not silently invented', function () {
    [$instance, $library] = seedNormalizationConnector();
    // A runtime keeps it out of "weak metadata" so the missing year is the topic.
    seedNormalizationItem($instance, $library, 'jf-1', 'Yearless', 'movie', ['year' => null]);
    normalizeConnector($instance);

    $item = planItemFor(createImportPlan(), 'jf-1');

    expect($item->status)->toBe('warning')
        ->and($item->planned_action)->toBe('create_media')
        ->and($item->target_year)->toBeNull()
        ->and($item->reasons)->toContain('missing_year');
});

test('a clean series becomes a create_container plan item', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'series-1', 'Severance', 'series', ['runtime_seconds' => null]);
    normalizeConnector($instance);

    $item = planItemFor(createImportPlan(), 'series-1');

    expect($item->planned_kind)->toBe('series')
        ->and($item->planned_action)->toBe('create_container')
        ->and($item->status)->toBe('ready');
});

test('a clean episode becomes a ready attach_to_parent plan item', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'series-1', 'Severance', 'series', ['runtime_seconds' => null]);
    seedNormalizationItem($instance, $library, 'ep-1', 'Good News About Hell', 'episode', [
        'external_parent_id' => 'series-1',
        'parent_index_number' => 2,
        'index_number' => 5,
    ]);
    normalizeConnector($instance);

    $item = planItemFor(createImportPlan(), 'ep-1');

    expect($item->planned_kind)->toBe('episode')
        ->and($item->planned_action)->toBe('attach_to_parent')
        ->and($item->status)->toBe('ready')
        ->and($item->target_season_number)->toBe(2)
        ->and($item->target_episode_number)->toBe(5)
        // The parent key points at the season container it would attach to.
        ->and($item->target_parent_key)->not->toBeNull();
});

test('an episode missing its season or episode number needs review', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'series-1', 'Severance', 'series', ['runtime_seconds' => null]);
    seedNormalizationItem($instance, $library, 'ep-1', 'Unnumbered', 'episode', [
        'external_parent_id' => 'series-1',
        'parent_index_number' => null,
        'index_number' => null,
    ]);
    normalizeConnector($instance);

    $item = planItemFor(createImportPlan(), 'ep-1');

    expect($item->status)->toBe('needs_review')
        ->and($item->planned_action)->toBe('needs_review')
        ->and($item->reasons)->toContain('missing_season_number')
        ->and($item->reasons)->toContain('missing_episode_number')
        // Nothing is guessed: no target identity is invented for it.
        ->and($item->target_key)->toBeNull();
});

test('an episode with no identifiable parent is blocked', function () {
    [$instance, $library] = seedNormalizationConnector();
    // No series row exists, so no parent title can be resolved.
    seedNormalizationItem($instance, $library, 'ep-1', 'Orphan', 'episode', [
        'parent_index_number' => 1,
        'index_number' => 3,
    ]);
    normalizeConnector($instance);

    $plan = createImportPlan();
    $item = planItemFor($plan, 'ep-1');

    expect($item->status)->toBe('blocked')
        ->and($item->planned_action)->toBe('blocked')
        ->and($item->reasons)->toContain('missing_parent')
        ->and($item->reasons)->toContain('unsafe_to_import')
        ->and($plan->status)->toBe('blocked')
        ->and($plan->blocked_count)->toBe(1);
});

test('a season becomes a container when its series and number are known', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'series-1', 'Severance', 'series', ['runtime_seconds' => null]);
    seedNormalizationItem($instance, $library, 'season-1', 'Season 2', 'season', [
        'external_parent_id' => 'series-1',
        'index_number' => 2,
        'runtime_seconds' => null,
    ]);
    normalizeConnector($instance);

    $item = planItemFor(createImportPlan(), 'season-1');

    expect($item->planned_kind)->toBe('season')
        ->and($item->planned_action)->toBe('create_container')
        ->and($item->status)->toBe('ready')
        ->and($item->target_season_number)->toBe(2)
        ->and($item->target_parent_key)->not->toBeNull();
});

test('an item with no usable title is blocked and never given an invented target', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', '   ');
    normalizeConnector($instance);

    $item = planItemFor(createImportPlan(), 'jf-1');

    expect($item->status)->toBe('blocked')
        ->and($item->planned_action)->toBe('blocked')
        ->and($item->reasons)->toContain('missing_title')
        ->and($item->target_key)->toBeNull();
});

test('an unknown media kind needs review instead of being guessed at', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'Mystery Thing', 'unknown');
    normalizeConnector($instance);

    $plan = createImportPlan();
    $item = planItemFor($plan, 'jf-1');

    expect($item->status)->toBe('needs_review')
        ->and($item->planned_action)->toBe('needs_review')
        ->and($item->reasons)->toContain('unknown_kind')
        ->and($plan->status)->toBe('warnings')
        ->and($plan->review_count)->toBe(1);
});

test('weak metadata needs review rather than becoming a hopeful import', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'Just A Title', 'movie', ['year' => null, 'runtime_seconds' => null]);
    normalizeConnector($instance);

    $item = planItemFor(createImportPlan(), 'jf-1');

    expect($item->status)->toBe('needs_review')
        ->and($item->planned_action)->toBe('needs_review')
        ->and($item->reasons)->toContain('weak_metadata');
});

test('folders and playlists are skipped as unsupported, not treated as errors', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'My Playlist', 'playlist', ['year' => null, 'runtime_seconds' => null]);
    seedNormalizationItem($instance, $library, 'jf-2', 'Some Folder', 'folder', ['year' => null, 'runtime_seconds' => null]);
    normalizeConnector($instance);

    $plan = createImportPlan();

    expect($plan->skipped_count)->toBe(2)
        ->and($plan->unsupported_count)->toBe(2)
        ->and($plan->blocked_count)->toBe(0)
        // Skipped items are counted, never an error: the plan is still "ready".
        ->and($plan->status)->toBe('ready');

    foreach (['jf-1', 'jf-2'] as $externalId) {
        $item = planItemFor($plan, $externalId);
        expect($item->status)->toBe('skipped')
            ->and($item->planned_action)->toBe('skip_unsupported')
            ->and($item->reasons)->toContain('unsupported_kind');
    }
});

test('a duplicate suspect never becomes ready and is never merged', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    seedNormalizationItem($instance, $library, 'jf-2', 'The Matrix'); // same identity
    seedNormalizationItem($instance, $library, 'jf-3', 'Arrival', 'movie', ['year' => 2016]);
    normalizeConnector($instance);

    $plan = createImportPlan();

    expect($plan->duplicate_count)->toBe(2)
        ->and($plan->ready_count)->toBe(1)   // only the unique item stays ready
        ->and($plan->review_count)->toBe(2)
        ->and($plan->status)->toBe('warnings');

    foreach (['jf-1', 'jf-2'] as $externalId) {
        $item = planItemFor($plan, $externalId);
        expect($item->status)->toBe('needs_review')
            ->and($item->planned_action)->toBe('skip_duplicate')
            ->and($item->reasons)->toContain('duplicate_suspect')
            ->and($item->reasons)->not->toContain('ready_to_import');
    }

    // Both suspects survive as separate plan lines — nothing was merged away.
    expect(MediaImportPlanItem::query()->where('media_import_plan_id', $plan->id)->count())->toBe(3);
});

test('an item captured before normalization is blocked rather than planned blindly', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'Never Normalized');
    // Deliberately NOT normalized.

    $item = planItemFor(createImportPlan(), 'jf-1');

    expect($item->status)->toBe('blocked')
        ->and($item->reasons)->toContain('not_normalized')
        ->and($item->reasons)->toContain('unsafe_to_import');
});

test('a vanished item is never planned for import', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'Kept');
    seedNormalizationItem($instance, $library, 'jf-2', 'Vanished', 'movie', ['is_present' => false, 'missing_since' => now()]);
    normalizeConnector($instance);

    $plan = createImportPlan();

    expect($plan->source_item_count)->toBe(1)
        ->and($plan->planned_item_count)->toBe(1)
        ->and(planItemFor($plan, 'jf-2'))->toBeNull();
});

test('an empty catalog produces an empty plan instead of failing', function () {
    seedNormalizationConnector();

    $plan = createImportPlan();

    expect($plan->status)->toBe('empty')
        ->and($plan->source_item_count)->toBe(0)
        ->and($plan->planned_item_count)->toBe(0);
});

/* ---------------------------------------------------------------------------
 | Scope
 * ------------------------------------------------------------------------- */

test('a dry run can be scoped to one connector or one library', function () {
    [$jellyfin, $movies] = seedNormalizationConnector('jellyfin');
    $shows = ConnectorLibrary::query()->create([
        'connector_instance_id' => $jellyfin->id,
        'provider_key' => 'jellyfin',
        'external_id' => 'jf-shows',
        'name' => 'Shows',
        'collection_type' => 'tvshows',
        'is_enabled' => true,
        'discovery_status' => 'present',
        'last_seen_at' => now(),
    ]);
    [$abs, $absLibrary] = seedNormalizationConnector('audiobookshelf', 'ABS-TOKEN');

    seedNormalizationItem($jellyfin, $movies, 'jf-1', 'The Matrix');
    seedNormalizationItem($jellyfin, $shows, 'jf-2', 'Severance', 'series', ['runtime_seconds' => null]);
    seedNormalizationItem($abs, $absLibrary, 'abs-1', 'Dune', 'audiobook');
    normalizeConnector($jellyfin);
    normalizeConnector($abs, 'audiobookshelf');

    expect(createImportPlan()->planned_item_count)->toBe(3);

    $connectorPlan = createImportPlan(ImportPlanScope::Connector, $jellyfin);
    expect($connectorPlan->planned_item_count)->toBe(2)
        ->and($connectorPlan->connector_instance_id)->toBe($jellyfin->id);

    $libraryPlan = createImportPlan(ImportPlanScope::Library, $jellyfin, $shows);
    expect($libraryPlan->planned_item_count)->toBe(1)
        ->and($libraryPlan->connector_library_id)->toBe($shows->id)
        ->and(planItemFor($libraryPlan, 'jf-2'))->not->toBeNull();
});

/* ---------------------------------------------------------------------------
 | Determinism and bounds
 * ------------------------------------------------------------------------- */

test('the same stored input produces the same plan twice', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    seedNormalizationItem($instance, $library, 'jf-2', 'Mystery', 'unknown');
    seedNormalizationItem($instance, $library, 'jf-3', 'Some Folder', 'folder', ['year' => null, 'runtime_seconds' => null]);
    normalizeConnector($instance);

    $fingerprint = function (): array {
        $plan = createImportPlan();

        return MediaImportPlanItem::query()
            ->where('media_import_plan_id', $plan->id)
            ->orderBy('id')
            ->get()
            ->map(fn (MediaImportPlanItem $item): string => implode('|', [
                $item->connector_catalog_item_id,
                $item->planned_kind,
                $item->planned_action,
                $item->status,
                (string) $item->target_key,
                (string) $item->confidence,
                implode(',', $item->reasons),
            ]))
            ->sort()
            ->values()
            ->all();
    };

    expect($fingerprint())->toBe($fingerprint());
});

test('a repeated dry run creates a new plan and still imports nothing', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    normalizeConnector($instance);

    $first = createImportPlan();
    $second = createImportPlan();

    expect(MediaImportPlan::query()->count())->toBe(2)
        ->and($second->id)->not->toBe($first->id)
        ->and(MediaItem::query()->count())->toBe(0)
        ->and(MediaEdition::query()->count())->toBe(0)
        ->and(MediaFile::query()->count())->toBe(0);
});

test('the cap is recorded on every plan so the bound is visible, not implicit', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    normalizeConnector($instance);

    $plan = createImportPlan();

    expect($plan->summary['cap'])->toBe(CreateMediaImportPlan::MAX_ITEMS_PER_PLAN)
        ->and($plan->summary['truncated'])->toBeFalse();
});

test('a catalog larger than the cap is planned as a bounded, truncated subset', function () {
    [$instance, $library] = seedNormalizationConnector();
    $cap = CreateMediaImportPlan::MAX_ITEMS_PER_PLAN;

    // Built with bulk inserts: the point is the bound, not the seeding path.
    seedBulkNormalizedItems($instance, $library, $cap + 10);

    $plan = createImportPlan();

    expect($plan->source_item_count)->toBe($cap + 10)
        ->and($plan->planned_item_count)->toBe($cap)
        ->and($plan->summary['truncated'])->toBeTrue()
        // A truncated plan is never reported as plainly "ready".
        ->and($plan->status)->toBe('warnings')
        ->and(MediaImportPlanItem::query()->where('media_import_plan_id', $plan->id)->count())->toBe($cap);

    $task = ReviewTask::query()->where('task_type', 'media_import_plan')->where('status', 'open')->sole();
    expect(array_column($task->evidence['issues'], 'code'))->toContain('truncated_plan');
})->group('slow');

/* ---------------------------------------------------------------------------
 | Review tasks and audit
 * ------------------------------------------------------------------------- */

test('a problematic plan raises exactly one deduplicated review task', function () {
    [$instance, $library] = seedNormalizationConnector();

    for ($i = 0; $i < 5; $i++) {
        seedNormalizationItem($instance, $library, "jf-{$i}", "Weird {$i}", 'unknown');
    }
    normalizeConnector($instance);

    $plan = createImportPlan();

    $tasks = ReviewTask::query()->where('task_type', 'media_import_plan')->where('status', 'open')->get();
    expect($tasks)->toHaveCount(1);

    $task = $tasks->sole();
    $codes = array_column($task->evidence['issues'], 'code');

    expect($task->subject_id)->toBe($plan->id)
        ->and($codes)->toContain('unknown_kind')
        // Five broken items → ONE task carrying the counts, not five tasks.
        ->and(collect($task->evidence['issues'])->firstWhere('code', 'unknown_kind')['item_count'])->toBe(5)
        ->and($task->evidence['counts']['needs_review'])->toBe(5);
});

test('a re-run supersedes the previous review task for the same scope', function () {
    [$instance, $library] = seedNormalizationConnector();
    $item = seedNormalizationItem($instance, $library, 'jf-1', 'Weird', 'unknown');
    normalizeConnector($instance);

    createImportPlan();
    createImportPlan();

    // Repeated dry runs never flood the queue.
    expect(ReviewTask::query()->where('task_type', 'media_import_plan')->where('status', 'open')->count())->toBe(1)
        ->and(ReviewTask::query()->where('task_type', 'media_import_plan')->where('status', 'dismissed')->count())->toBe(1);

    // Once the data is good, the queue heals itself.
    $item->media_kind = 'movie';
    $item->save();
    normalizeConnector($instance);
    createImportPlan();

    expect(ReviewTask::query()->where('task_type', 'media_import_plan')->where('status', 'open')->count())->toBe(0);
});

test('a clean plan raises no review task at all', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    normalizeConnector($instance);

    createImportPlan();

    expect(ReviewTask::query()->where('task_type', 'media_import_plan')->count())->toBe(0);
});

test('a dry run writes one sanitized audit entry', function () {
    [$instance, $library] = seedNormalizationConnector('jellyfin', 'AUDIT-PLAN-TOKEN');
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    normalizeConnector($instance);

    createImportPlan(ImportPlanScope::Connector, $instance);

    $entry = AuditLog::query()->where('action', 'media_import_plan.created')->sole();

    expect($entry->context['scope'])->toBe('connector')
        ->and($entry->context['connector'])->toBe('jellyfin')
        ->and($entry->context['status'])->toBe('ready')
        ->and($entry->changes['planned_items'])->toBe(1);

    $serialized = AuditLog::query()->get()
        ->map(fn (AuditLog $log): string => json_encode($log->changes).json_encode($log->context))
        ->implode('');

    expect($serialized)->not->toContain('AUDIT-PLAN-TOKEN');
});
