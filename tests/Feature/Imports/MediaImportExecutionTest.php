<?php

declare(strict_types=1);

use App\Connectors\Sdk\Import\ImportPlanScope;
use App\Connectors\Sdk\Models\MediaExternalMapping;
use App\Connectors\Sdk\Models\MediaImportExecution;
use App\Connectors\Sdk\Models\MediaImportExecutionItem;
use App\Core\Audit\AuditLog;
use App\Core\Media\MediaItem;
use App\Core\Review\ReviewTask;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    // The internal import must never touch the network.
    Http::preventStrayRequests();
});

// seedImportableCatalog() / createImportPlan() / executeImportPlan() /
// importedMediaItemFor() / executionItemFor() live in tests/Pest.php.

/* ---------------------------------------------------------------------------
 | What gets imported
 * ------------------------------------------------------------------------- */

test('a ready movie becomes a media item with an external mapping', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'movie-1', 'The Matrix', 'movie', ['year' => 1999]);
    normalizeConnector($instance);

    $execution = executeImportPlan(createImportPlan());

    $item = importedMediaItemFor($instance, 'movie-1');

    expect($item)->not->toBeNull()
        ->and($item->media_type)->toBe('movie')
        ->and($item->title)->toBe('The Matrix')
        ->and($item->year)->toBe(1999)
        ->and($item->parent_id)->toBeNull()
        ->and($item->source)->toBe('connector_import')
        ->and($item->created_by_import_execution_id)->toBe($execution->id)
        // Runtime is carried over from the normalized reading, in milliseconds.
        ->and($item->runtime_ms)->toBe(7_200_000);

    $mapping = MediaExternalMapping::query()->sole();
    expect($mapping->external_id)->toBe('movie-1')
        ->and($mapping->connector_instance_id)->toBe($instance->id)
        ->and($mapping->connector_library_id)->toBe($library->id)
        ->and($mapping->source_type)->toBe('jellyfin')
        ->and($mapping->media_item_id)->toBe($item->id);

    expect($execution->status)->toBe('completed')
        ->and($execution->imported_count)->toBe(1);

    Http::assertNothingSent();
});

test('a ready series, season and episode become a real parent chain', function () {
    [$instance] = seedImportableCatalog();

    executeImportPlan(createImportPlan());

    $series = importedMediaItemFor($instance, 'series-1');
    $season = importedMediaItemFor($instance, 'season-1');
    $episode = importedMediaItemFor($instance, 'ep-1');

    // The connector vocabulary ("series") maps onto the foundation's ("show").
    expect($series->media_type)->toBe('show')
        ->and($series->parent_id)->toBeNull()
        ->and($series->title)->toBe('Severance')
        ->and($series->year)->toBe(2022);

    expect($season->media_type)->toBe('season')
        ->and($season->parent_id)->toBe($series->id)
        ->and($season->season_number)->toBe(2);

    expect($episode->media_type)->toBe('episode')
        ->and($episode->parent_id)->toBe($season->id)
        ->and($episode->season_number)->toBe(2)
        ->and($episode->episode_number)->toBe(5)
        ->and($episode->title)->toBe('Good News About Hell');

    // Walking back up the chain works, which is the whole point.
    expect($episode->parent->parent->id)->toBe($series->id);
});

test('a ready audiobook and book become media items', function () {
    [$instance, $library] = seedNormalizationConnector('audiobookshelf', 'ABS-TOKEN');
    seedNormalizationItem($instance, $library, 'abs-1', 'Dune', 'audiobook', ['year' => 1965]);
    seedNormalizationItem($instance, $library, 'abs-2', 'Neuromancer', 'book', ['year' => 1984]);
    normalizeConnector($instance, 'audiobookshelf');

    executeImportPlan(createImportPlan());

    $audiobook = importedMediaItemFor($instance, 'abs-1');
    $book = importedMediaItemFor($instance, 'abs-2');

    expect($audiobook->media_type)->toBe('audiobook')
        ->and($audiobook->title)->toBe('Dune')
        // The foundation catalog calls a book an "ebook".
        ->and($book->media_type)->toBe('ebook')
        ->and($book->title)->toBe('Neuromancer');

    expect(MediaExternalMapping::query()->where('source_type', 'audiobookshelf')->count())->toBe(2);
});

test('the execution records one line per plan item with its action and status', function () {
    [$instance] = seedImportableCatalog();

    $execution = executeImportPlan(createImportPlan());

    expect(MediaImportExecutionItem::query()->where('media_import_execution_id', $execution->id)->count())->toBe(4);

    $movie = executionItemFor($execution, 'movie-1');
    expect($movie->action)->toBe('created')
        ->and($movie->status)->toBe('completed')
        ->and($movie->reason_codes)->toContain('imported_from_plan')
        ->and($movie->media_item_id)->toBe(importedMediaItemFor($instance, 'movie-1')->id)
        ->and($movie->title)->toBe('The Matrix');
});

/* ---------------------------------------------------------------------------
 | What is refused
 * ------------------------------------------------------------------------- */

test('a needs-review plan item is skipped, never imported', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'Mystery Thing', 'unknown');
    normalizeConnector($instance);

    $execution = executeImportPlan(createImportPlan());

    expect(MediaItem::query()->count())->toBe(0)
        ->and($execution->status)->toBe('empty')
        ->and($execution->imported_count)->toBe(0)
        ->and($execution->skipped_count)->toBe(1);

    $line = executionItemFor($execution, 'jf-1');
    expect($line->action)->toBe('skipped_not_ready')
        ->and($line->status)->toBe('skipped')
        ->and($line->reason_codes)->toContain('needs_review_first');
});

test('a blocked plan item is skipped, never imported', function () {
    [$instance, $library] = seedNormalizationConnector();
    // No usable title → V2 D blocks it.
    seedNormalizationItem($instance, $library, 'jf-1', '   ');
    normalizeConnector($instance);

    $execution = executeImportPlan(createImportPlan());

    expect(MediaItem::query()->count())->toBe(0);

    $line = executionItemFor($execution, 'jf-1');
    expect($line->action)->toBe('skipped_not_ready')
        ->and($line->reason_codes)->toContain('plan_item_not_ready');
});

test('an unsupported item stays skipped as unsupported', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'A Folder', 'folder', ['year' => null, 'runtime_seconds' => null]);
    seedNormalizationItem($instance, $library, 'jf-2', 'A Playlist', 'playlist', ['year' => null, 'runtime_seconds' => null]);
    normalizeConnector($instance);

    $execution = executeImportPlan(createImportPlan());

    expect(MediaItem::query()->count())->toBe(0)
        ->and($execution->skipped_count)->toBe(2);

    foreach (['jf-1', 'jf-2'] as $externalId) {
        $line = executionItemFor($execution, $externalId);
        expect($line->action)->toBe('skipped_unsupported')
            ->and($line->reason_codes)->toContain('unsupported_kind');
    }
});

test('a duplicated item imports once and the extra copy is skipped, never merged', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    seedNormalizationItem($instance, $library, 'jf-2', 'The Matrix');
    normalizeConnector($instance);

    $execution = executeImportPlan(createImportPlan());

    // Two captures of one film produce exactly ONE film — not zero, and not two.
    expect(MediaItem::query()->count())->toBe(1)
        ->and($execution->imported_count)->toBe(1)
        ->and($execution->skipped_count)->toBe(1);

    expect(executionItemFor($execution, 'jf-1')->action)->toBe('created');

    $extra = executionItemFor($execution, 'jf-2');
    expect($extra->action)->toBe('skipped_duplicate')
        ->and($extra->reason_codes)->toContain('duplicate_not_imported')
        // The extra copy was never given a media item of its own.
        ->and($extra->media_item_id)->toBeNull();

    // Nothing was merged: the skipped copy is still its own external item with no
    // mapping, so a human can still decide differently later.
    expect(MediaExternalMapping::query()->count())->toBe(1);
});

test('weak metadata is never imported', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'Just A Title', 'movie', ['year' => null, 'runtime_seconds' => null]);
    normalizeConnector($instance);

    executeImportPlan(createImportPlan());

    expect(MediaItem::query()->count())->toBe(0);
});

test('podcast and music are out of scope and are not imported', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'A Podcast', 'podcast');
    seedNormalizationItem($instance, $library, 'jf-2', 'A Track', 'music');
    normalizeConnector($instance);

    $execution = executeImportPlan(createImportPlan());

    expect(MediaItem::query()->count())->toBe(0)
        ->and($execution->skipped_count)->toBe(2);
});

test('a plan with no ready items creates nothing and reports empty', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'Mystery', 'unknown');
    normalizeConnector($instance);

    $execution = executeImportPlan(createImportPlan());

    expect($execution->status)->toBe('empty')
        ->and($execution->imported_count)->toBe(0)
        ->and(MediaItem::query()->count())->toBe(0)
        ->and(MediaExternalMapping::query()->count())->toBe(0);
});

test('an entirely empty plan imports nothing', function () {
    seedNormalizationConnector();

    $execution = executeImportPlan(createImportPlan());

    expect($execution->status)->toBe('empty')
        ->and(MediaItem::query()->count())->toBe(0)
        ->and(MediaImportExecutionItem::query()->count())->toBe(0);
});

/* ---------------------------------------------------------------------------
 | Parent safety
 * ------------------------------------------------------------------------- */

test('an episode whose season was never imported is skipped, not attached to a guess', function () {
    [$instance, $library] = seedNormalizationConnector();
    // A series and an episode, but NO season row: the episode's parent chain is
    // incomplete, so there is nothing of the right shape to attach to.
    seedNormalizationItem($instance, $library, 'series-1', 'Severance', 'series', ['runtime_seconds' => null]);
    seedNormalizationItem($instance, $library, 'ep-1', 'Orphan', 'episode', [
        'external_parent_id' => 'series-1',
        'parent_index_number' => 1,
        'index_number' => 1,
    ]);
    normalizeConnector($instance);

    $execution = executeImportPlan(createImportPlan());

    // The series was imported; the episode was refused rather than hung off it.
    expect(importedMediaItemFor($instance, 'series-1'))->not->toBeNull()
        ->and(importedMediaItemFor($instance, 'ep-1'))->toBeNull();

    $line = executionItemFor($execution, 'ep-1');
    expect($line->action)->toBe('skipped_not_ready')
        ->and($line->reason_codes)->toContain('missing_parent');

    // Nothing broken was created: every episode in the catalog has a real parent.
    expect(MediaItem::query()->where('media_type', 'episode')->count())->toBe(0);
});

test('containers are imported before the items that hang under them', function () {
    [$instance] = seedImportableCatalog();

    executeImportPlan(createImportPlan());

    $series = importedMediaItemFor($instance, 'series-1');
    $season = importedMediaItemFor($instance, 'season-1');
    $episode = importedMediaItemFor($instance, 'ep-1');

    // ULIDs are monotonic, so creation order is visible in the ids themselves.
    expect(strcmp($series->id, $season->id))->toBeLessThan(0)
        ->and(strcmp($season->id, $episode->id))->toBeLessThan(0);
});

test('an episode reported under the series still attaches to its season', function () {
    // The shape Jellyfin actually produces: the episode names the SERIES as its
    // external parent, while the plan's parent key names the SEASON. Those are not
    // two competing parents — only one of them is a season — so the episode must
    // attach to the season rather than being refused as ambiguous.
    [$instance, $library] = seedNormalizationConnector();
    // The series needs its year to reach `ready`; without one V2 D only warns,
    // and an unimported series would hide the parent bug behind a cascade.
    seedNormalizationItem($instance, $library, 'series-1', 'Severance', 'series', [
        'year' => 2022, 'runtime_seconds' => null,
    ]);
    seedNormalizationItem($instance, $library, 'season-1', 'Severance', 'season', [
        'external_parent_id' => 'series-1', 'index_number' => 2, 'year' => null, 'runtime_seconds' => null,
    ]);
    seedNormalizationItem($instance, $library, 'ep-1', 'Good News About Hell', 'episode', [
        // Points at the SERIES, not the season.
        'external_parent_id' => 'series-1',
        'parent_index_number' => 2,
        'index_number' => 5,
    ]);
    normalizeConnector($instance);

    $execution = executeImportPlan(createImportPlan());

    $season = importedMediaItemFor($instance, 'season-1');
    $episode = importedMediaItemFor($instance, 'ep-1');

    expect($episode)->not->toBeNull()
        ->and($episode->parent_id)->toBe($season->id)
        ->and($season->media_type)->toBe('season');

    $line = executionItemFor($execution, 'ep-1');
    expect($line->action)->toBe('created')
        ->and($line->reason_codes)->not->toContain('ambiguous_parent');
});

test('a genuinely ambiguous parent is refused rather than guessed', function () {
    // Two DIFFERENT seasons both qualify as this episode's parent, so there is no
    // right answer to pick. Engineering the collision takes a season titled like a
    // series — which is exactly the kind of messy real-world naming that makes the
    // guard worth having.
    [$instance, $library] = seedNormalizationConnector();

    // Run 1 imports a season whose own TITLE is "Alpha".
    seedNormalizationItem($instance, $library, 'series-1', 'Severance', 'series', [
        'year' => 2022, 'runtime_seconds' => null,
    ]);
    seedNormalizationItem($instance, $library, 'season-x', 'Alpha', 'season', [
        'external_parent_id' => 'series-1', 'index_number' => 7, 'year' => null, 'runtime_seconds' => null,
    ]);
    normalizeConnector($instance);
    executeImportPlan(createImportPlan());

    $seasonX = importedMediaItemFor($instance, 'season-x');
    expect($seasonX)->not->toBeNull();

    // Run 2 adds a real series called "Alpha" with its own season 2 …
    seedNormalizationItem($instance, $library, 'series-alpha', 'Alpha', 'series', [
        'year' => 2020, 'runtime_seconds' => null,
    ]);
    seedNormalizationItem($instance, $library, 'season-alpha', 'Season 2', 'season', [
        'external_parent_id' => 'series-alpha', 'index_number' => 2, 'year' => null, 'runtime_seconds' => null,
    ]);
    // … and an episode whose external parent is the season TITLED "Alpha", while its
    // parent key resolves to season 2 OF the series "Alpha". Two valid seasons.
    seedNormalizationItem($instance, $library, 'ep-1', 'Collision', 'episode', [
        'external_parent_id' => 'season-x', 'parent_index_number' => 2, 'index_number' => 1,
    ]);
    normalizeConnector($instance);

    $execution = executeImportPlan(createImportPlan());

    $seasonAlpha = importedMediaItemFor($instance, 'season-alpha');
    expect($seasonAlpha)->not->toBeNull()
        ->and($seasonAlpha->id)->not->toBe($seasonX->id);

    // The episode was refused; neither candidate was silently picked.
    expect(importedMediaItemFor($instance, 'ep-1'))->toBeNull();

    $line = executionItemFor($execution, 'ep-1');
    expect($line->action)->toBe('skipped_not_ready')
        ->and($line->reason_codes)->toContain('ambiguous_parent');

    expect(MediaItem::query()->where('media_type', 'episode')->count())->toBe(0);
});

test('an episode attaches to a season imported by an earlier execution', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'series-1', 'Severance', 'series', ['year' => 2022, 'runtime_seconds' => null]);
    seedNormalizationItem($instance, $library, 'season-1', 'Season 1', 'season', [
        'external_parent_id' => 'series-1', 'index_number' => 1, 'year' => null, 'runtime_seconds' => null,
    ]);
    normalizeConnector($instance);
    executeImportPlan(createImportPlan());

    $season = importedMediaItemFor($instance, 'season-1');
    expect($season)->not->toBeNull();

    // The episode arrives in a LATER snapshot, planned and imported separately.
    seedNormalizationItem($instance, $library, 'ep-1', 'Late Episode', 'episode', [
        'external_parent_id' => 'season-1', 'parent_index_number' => 1, 'index_number' => 3,
    ]);
    normalizeConnector($instance);
    executeImportPlan(createImportPlan());

    $episode = importedMediaItemFor($instance, 'ep-1');
    expect($episode)->not->toBeNull()
        ->and($episode->parent_id)->toBe($season->id);
});

/* ---------------------------------------------------------------------------
 | Idempotency
 * ------------------------------------------------------------------------- */

test('running the same plan twice creates no duplicate media items', function () {
    [$instance] = seedImportableCatalog();
    $plan = createImportPlan();

    $first = executeImportPlan($plan);
    $countAfterFirst = MediaItem::query()->count();

    $second = executeImportPlan($plan);

    expect(MediaItem::query()->count())->toBe($countAfterFirst)
        ->and(MediaExternalMapping::query()->count())->toBe($countAfterFirst)
        ->and($first->imported_count)->toBe(4)
        ->and($second->imported_count)->toBe(0)
        ->and($second->already_existing_count)->toBe(4)
        ->and($second->status)->toBe('completed_with_warnings');

    // The second run says "already imported" for every line.
    foreach (['series-1', 'season-1', 'ep-1', 'movie-1'] as $externalId) {
        $line = executionItemFor($second, $externalId);
        expect($line->action)->toBe('linked_existing')
            ->and($line->reason_codes)->toContain('already_imported');
    }

    // Two executions were recorded — the runs are history, the media is not doubled.
    expect(MediaImportExecution::query()->count())->toBe(2);
});

test('a second plan over the same catalog links the existing items instead of duplicating', function () {
    [$instance] = seedImportableCatalog();

    executeImportPlan(createImportPlan());
    $itemsAfterFirst = MediaItem::query()->count();

    // A brand new dry run over unchanged data, then imported again.
    $second = executeImportPlan(createImportPlan());

    expect(MediaItem::query()->count())->toBe($itemsAfterFirst)
        ->and($second->already_existing_count)->toBe(4)
        ->and($second->imported_count)->toBe(0);
});

test('an existing media item is linked, never overwritten', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'movie-1', 'The Matrix', 'movie', ['year' => 1999]);
    normalizeConnector($instance);

    executeImportPlan(createImportPlan());

    // A human renames the imported item.
    $item = importedMediaItemFor($instance, 'movie-1');
    $item->title = 'The Matrix (Director\'s Cut)';
    $item->save();

    executeImportPlan(createImportPlan());

    // The import linked it and left the human's edit alone.
    expect(importedMediaItemFor($instance, 'movie-1')->title)->toBe('The Matrix (Director\'s Cut)')
        ->and(MediaItem::query()->count())->toBe(1);
});

test('the same external id cannot be mapped twice', function () {
    [$instance] = seedImportableCatalog();
    executeImportPlan(createImportPlan());

    $mapping = MediaExternalMapping::query()->where('external_id', 'movie-1')->sole();

    expect(fn () => MediaExternalMapping::query()->create([
        'media_item_id' => $mapping->media_item_id,
        'connector_instance_id' => $instance->id,
        'external_id' => 'movie-1',
        'source_type' => 'jellyfin',
        'imported_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

/* ---------------------------------------------------------------------------
 | Determinism, counts and provenance
 * ------------------------------------------------------------------------- */

test('the same plan produces the same logical import twice over', function () {
    [$instance] = seedImportableCatalog();

    $fingerprint = function (): array {
        return MediaItem::query()
            ->orderBy('media_type')
            ->orderBy('title')
            ->get()
            ->map(fn (MediaItem $item): string => implode('|', [
                $item->media_type,
                $item->title,
                (string) $item->year,
                (string) $item->season_number,
                (string) $item->episode_number,
                $item->parent_id === null ? 'root' : 'child',
            ]))
            ->sort()
            ->values()
            ->all();
    };

    executeImportPlan(createImportPlan());
    $first = $fingerprint();

    // A repeated run must not change the shape of the catalog at all.
    executeImportPlan(createImportPlan());

    expect($fingerprint())->toBe($first);
});

test('the execution counts imported, skipped, already existing and failed correctly', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'movie-1', 'The Matrix', 'movie', ['year' => 1999]);
    seedNormalizationItem($instance, $library, 'jf-2', 'Mystery', 'unknown');
    seedNormalizationItem($instance, $library, 'jf-3', 'A Folder', 'folder', ['year' => null, 'runtime_seconds' => null]);
    normalizeConnector($instance);

    $plan = createImportPlan();
    $execution = executeImportPlan($plan);

    expect($execution->imported_count)->toBe(1)
        ->and($execution->skipped_count)->toBe(2)
        ->and($execution->already_existing_count)->toBe(0)
        ->and($execution->failed_count)->toBe(0)
        ->and($execution->status)->toBe('completed_with_warnings')
        ->and($execution->summary['candidate_count'])->toBe(1)
        ->and($execution->media_import_plan_id)->toBe($plan->id);
});

test('an execution records who ran it', function () {
    [$instance] = seedImportableCatalog();

    $execution = executeImportPlan(createImportPlan(), 'user:01TESTUSER');

    expect($execution->created_by)->toBe('user:01TESTUSER');
});

test('a scoped plan imports only that scope', function () {
    [$jellyfin, $movies] = seedNormalizationConnector('jellyfin');
    $shows = seedNormalizationLibrary($jellyfin, 'jf-shows', 'Shows');
    seedNormalizationItem($jellyfin, $movies, 'movie-1', 'The Matrix', 'movie', ['year' => 1999]);
    seedNormalizationItem($jellyfin, $shows, 'movie-2', 'Arrival', 'movie', ['year' => 2016]);
    normalizeConnector($jellyfin);

    executeImportPlan(createImportPlan(ImportPlanScope::Library, $jellyfin, $shows));

    expect(MediaItem::query()->count())->toBe(1)
        ->and(importedMediaItemFor($jellyfin, 'movie-2'))->not->toBeNull()
        ->and(importedMediaItemFor($jellyfin, 'movie-1'))->toBeNull();
});

/* ---------------------------------------------------------------------------
 | Review tasks and audit
 * ------------------------------------------------------------------------- */

test('an import that refused lines raises one deduplicated review task', function () {
    [$instance, $library] = seedNormalizationConnector();

    for ($i = 0; $i < 4; $i++) {
        seedNormalizationItem($instance, $library, "jf-{$i}", "Mystery {$i}", 'unknown');
    }
    normalizeConnector($instance);

    $execution = executeImportPlan(createImportPlan());

    $tasks = ReviewTask::query()->where('task_type', 'media_import_execution')->where('status', 'open')->get();
    expect($tasks)->toHaveCount(1);

    $task = $tasks->sole();
    $codes = array_column($task->evidence['issues'], 'code');

    expect($task->subject_id)->toBe($execution->id)
        ->and($codes)->toContain('needs_review_first')
        // Four refused lines → ONE task carrying the count, not four tasks.
        ->and(collect($task->evidence['issues'])->firstWhere('code', 'needs_review_first')['item_count'])->toBe(4)
        ->and($task->evidence['counts']['skipped'])->toBe(4);
});

test('a repeated import supersedes the previous review task for the same plan', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'Mystery', 'unknown');
    normalizeConnector($instance);

    $plan = createImportPlan();
    executeImportPlan($plan);
    executeImportPlan($plan);

    expect(ReviewTask::query()->where('task_type', 'media_import_execution')->where('status', 'open')->count())->toBe(1)
        ->and(ReviewTask::query()->where('task_type', 'media_import_execution')->where('status', 'dismissed')->count())->toBe(1);
});

test('a clean import raises no review task at all', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'movie-1', 'The Matrix', 'movie', ['year' => 1999]);
    normalizeConnector($instance);

    executeImportPlan(createImportPlan());

    expect(ReviewTask::query()->where('task_type', 'media_import_execution')->count())->toBe(0);
});

test('an import writes one sanitized audit entry', function () {
    [$instance] = seedImportableCatalog('AUDIT-IMPORT-TOKEN');

    $plan = createImportPlan();
    $execution = executeImportPlan($plan);

    $entry = AuditLog::query()->where('action', 'media_import.execution_completed')->sole();

    expect($entry->context['plan_id'])->toBe($plan->id)
        ->and($entry->context['execution_id'])->toBe($execution->id)
        ->and($entry->context['status'])->toBe('completed')
        ->and($entry->changes['imported'])->toBe(4);

    $serialized = AuditLog::query()->get()
        ->map(fn (AuditLog $log): string => json_encode($log->changes).json_encode($log->context))
        ->implode('');

    expect($serialized)->not->toContain('AUDIT-IMPORT-TOKEN');
});

test('an import with nothing to do audits itself as empty', function () {
    seedNormalizationConnector();

    executeImportPlan(createImportPlan());

    expect(AuditLog::query()->where('action', 'media_import.execution_empty')->count())->toBe(1);
});
