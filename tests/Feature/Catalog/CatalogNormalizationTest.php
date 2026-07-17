<?php

declare(strict_types=1);

use App\Connectors\Sdk\Models\ConnectorCatalogItemNormalization;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Core\Audit\AuditLog;
use App\Core\Media\MediaEdition;
use App\Core\Media\MediaFile;
use App\Core\Media\MediaItem;
use App\Core\Review\ReviewTask;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    // Normalizing and rendering must never touch the network.
    Http::preventStrayRequests();
});

// seedNormalizationConnector() / seedNormalizationItem() / normalizeConnector()
// live in tests/Pest.php — the project's home for shared harnesses.

/* ---------------------------------------------------------------------------
 | Normalization rules
 * ------------------------------------------------------------------------- */

test('normalization cleans up whitespace and typographic variants in the title', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', "  The   Matrix\u{2019}s   Return \u{2014} Part\u{00A0}One  ");

    normalizeConnector($instance);

    $normalization = ConnectorCatalogItemNormalization::query()->sole();
    // Whitespace collapsed, curly quote and em dash and NBSP unified, trimmed.
    expect($normalization->normalized_title)->toBe("The Matrix's Return - Part One")
        ->and($normalization->status)->toBe('clean');
});

test('normalization derives a sort title by moving a leading article aside', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    seedNormalizationItem($instance, $library, 'jf-2', 'Die Hard');
    // A connector-provided sort title always wins over the derived one.
    seedNormalizationItem($instance, $library, 'jf-3', 'The Abyss', 'movie', ['sort_title' => 'Abyss, The']);

    normalizeConnector($instance);

    $byExternalId = fn (string $id) => ConnectorCatalogItemNormalization::query()
        ->whereHas('item', fn ($q) => $q->where('external_id', $id))->sole();

    expect($byExternalId('jf-1')->normalized_sort_title)->toBe('matrix')
        ->and($byExternalId('jf-2')->normalized_sort_title)->toBe('hard')
        ->and($byExternalId('jf-3')->normalized_sort_title)->toBe('abyss, the');
});

test('normalization maps the media kind and flags an unknown one for review', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix', 'movie');
    seedNormalizationItem($instance, $library, 'jf-2', 'Mystery Thing', 'unknown');

    normalizeConnector($instance);

    $movie = ConnectorCatalogItemNormalization::query()->whereHas('item', fn ($q) => $q->where('external_id', 'jf-1'))->sole();
    $unknown = ConnectorCatalogItemNormalization::query()->whereHas('item', fn ($q) => $q->where('external_id', 'jf-2'))->sole();

    expect($movie->normalized_kind)->toBe('movie')
        ->and($movie->status)->toBe('clean')
        ->and($unknown->normalized_kind)->toBe('unknown')
        ->and($unknown->issues)->toContain('unknown_kind')
        // 100 - 30 (unknown kind) = 70 → warning.
        ->and($unknown->confidence)->toBe(70)
        ->and($unknown->status)->toBe('warning');
});

test('an episode takes its season and episode numbers from the reported index fields', function () {
    [$instance, $library] = seedNormalizationConnector();
    // The series provides the parent title via external_parent_id → external_id.
    seedNormalizationItem($instance, $library, 'series-1', 'Severance', 'series', ['runtime_seconds' => null]);
    seedNormalizationItem($instance, $library, 'ep-1', 'Good News About Hell', 'episode', [
        'external_parent_id' => 'series-1',
        'parent_index_number' => 2,
        'index_number' => 5,
    ]);

    normalizeConnector($instance);

    $episode = ConnectorCatalogItemNormalization::query()->whereHas('item', fn ($q) => $q->where('external_id', 'ep-1'))->sole();

    expect($episode->normalized_kind)->toBe('episode')
        ->and($episode->season_number)->toBe(2)
        ->and($episode->episode_number)->toBe(5)
        ->and($episode->parent_title)->toBe('Severance')
        ->and($episode->status)->toBe('clean');
});

test('an episode missing its season or episode number is flagged', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'ep-1', 'Orphan Episode', 'episode', [
        'parent_index_number' => null,
        'index_number' => null,
    ]);

    normalizeConnector($instance);

    $episode = ConnectorCatalogItemNormalization::query()->sole();

    expect($episode->issues)->toContain('missing_season_number')
        ->and($episode->issues)->toContain('missing_episode_number')
        ->and($episode->season_number)->toBeNull()
        ->and($episode->episode_number)->toBeNull()
        // 100 - 15 - 15 = 70 → warning.
        ->and($episode->confidence)->toBe(70);
});

test('a missing title is flagged and never invented', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', '   ');

    normalizeConnector($instance);

    $normalization = ConnectorCatalogItemNormalization::query()->sole();

    expect($normalization->issues)->toContain('missing_title')
        ->and($normalization->normalized_title)->toBe('(untitled)')
        // 100 - 60 = 40 → needs_review.
        ->and($normalization->confidence)->toBe(40)
        ->and($normalization->status)->toBe('needs_review');
});

test('an implausible year or runtime is flagged and dropped rather than corrected', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'Bad Year', 'movie', ['year' => 1200]);
    seedNormalizationItem($instance, $library, 'jf-2', 'Bad Runtime', 'movie', ['runtime_seconds' => -5]);
    seedNormalizationItem($instance, $library, 'jf-3', 'Absurd Runtime', 'movie', ['runtime_seconds' => 999_999]);

    normalizeConnector($instance);

    $find = fn (string $id) => ConnectorCatalogItemNormalization::query()
        ->whereHas('item', fn ($q) => $q->where('external_id', $id))->sole();

    expect($find('jf-1')->issues)->toContain('invalid_year')
        ->and($find('jf-1')->release_year)->toBeNull()          // dropped, not "fixed"
        ->and($find('jf-2')->issues)->toContain('invalid_runtime')
        ->and($find('jf-2')->runtime_seconds)->toBeNull()
        ->and($find('jf-3')->issues)->toContain('invalid_runtime')
        ->and($find('jf-3')->runtime_seconds)->toBeNull();
});

test('an item with nothing but a title is flagged as weak metadata', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'Just A Title', 'movie', ['year' => null, 'runtime_seconds' => null]);

    normalizeConnector($instance);

    $normalization = ConnectorCatalogItemNormalization::query()->sole();

    expect($normalization->issues)->toContain('weak_metadata')
        ->and($normalization->issues)->toContain('missing_year')
        ->and($normalization->status)->toBe('needs_review');
});

test('a structural container is reported as not-media instead of drowning in warnings', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'My Playlist', 'playlist', ['year' => null, 'runtime_seconds' => null]);
    seedNormalizationItem($instance, $library, 'jf-2', 'Some Folder', 'folder', ['year' => null, 'runtime_seconds' => null]);

    normalizeConnector($instance);

    $rows = ConnectorCatalogItemNormalization::query()->get();

    expect($rows)->toHaveCount(2);
    foreach ($rows as $row) {
        expect($row->status)->toBe('unsupported')
            ->and($row->issues)->toBe([])
            ->and($row->confidence)->toBe(100);
    }
});

test('normalization stores only sanitized issue codes and minimal derived data', function () {
    [$instance, $library] = seedNormalizationConnector('jellyfin', 'NORMALIZE-DO-NOT-LEAK');
    seedNormalizationItem($instance, $library, 'jf-1', 'Weird', 'unknown', ['year' => null, 'runtime_seconds' => null]);

    normalizeConnector($instance);

    $normalization = ConnectorCatalogItemNormalization::query()->sole();

    // Issue codes are a plain code list; normalized_data carries no payload.
    expect($normalization->issues)->each->toBeString();
    expect(array_keys($normalization->normalized_data))
        ->toEqualCanonicalizing(['source_kind', 'issue_count', 'has_parent']);

    $serialized = json_encode($normalization->toArray());
    expect($serialized)->not->toContain('NORMALIZE-DO-NOT-LEAK');
});

test('repeated normalization updates the existing row instead of duplicating it', function () {
    [$instance, $library] = seedNormalizationConnector();
    $item = seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');

    normalizeConnector($instance);
    $first = ConnectorCatalogItemNormalization::query()->sole();

    // The connector reports a better title next time round.
    $item->title = 'The Matrix Reloaded';
    $item->save();

    $this->travel(2)->seconds();
    normalizeConnector($instance);

    expect(ConnectorCatalogItemNormalization::query()->count())->toBe(1);

    $second = ConnectorCatalogItemNormalization::query()->sole();
    expect($second->id)->toBe($first->id)                       // same row, upserted
        ->and($second->normalized_title)->toBe('The Matrix Reloaded')
        ->and($second->normalized_at?->greaterThan($first->normalized_at))->toBeTrue();
});

test('normalization can be scoped to a single library', function () {
    [$instance, $movies] = seedNormalizationConnector();
    $shows = ConnectorLibrary::query()->create([
        'connector_instance_id' => $instance->id,
        'provider_key' => 'jellyfin',
        'external_id' => 'jf-shows',
        'name' => 'Shows',
        'collection_type' => 'tvshows',
        'is_enabled' => true,
        'discovery_status' => 'present',
        'last_seen_at' => now(),
    ]);
    seedNormalizationItem($instance, $movies, 'jf-1', 'The Matrix');
    seedNormalizationItem($instance, $shows, 'jf-2', 'Severance', 'series', ['runtime_seconds' => null]);

    $counts = normalizeConnector($instance, 'jellyfin', $shows);

    expect($counts['normalized'])->toBe(1)
        ->and(ConnectorCatalogItemNormalization::query()->count())->toBe(1)
        ->and(ConnectorCatalogItemNormalization::query()->sole()->normalized_title)->toBe('Severance');
});

test('normalization creates no media items, editions or files', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    seedNormalizationItem($instance, $library, 'jf-2', 'Nothing', 'unknown', ['year' => null, 'runtime_seconds' => null]);

    normalizeConnector($instance);

    expect(MediaItem::query()->count())->toBe(0)
        ->and(MediaEdition::query()->count())->toBe(0)
        ->and(MediaFile::query()->count())->toBe(0);

    Http::assertNothingSent();
});

test('normalization writes a sanitized audit entry without the raw token', function () {
    [$instance, $library] = seedNormalizationConnector('jellyfin', 'AUDIT-NORM-TOKEN');
    seedNormalizationItem($instance, $library, 'jf-1', 'Weird', 'unknown', ['year' => null, 'runtime_seconds' => null]);

    normalizeConnector($instance);

    $entry = AuditLog::query()->where('action', 'catalog.normalization_rebuilt')->sole();

    expect($entry->context['connector'])->toBe('jellyfin')
        ->and($entry->context['scope'])->toBe('connector')
        ->and($entry->changes['normalized'])->toBe(1)
        ->and($entry->context['issue_codes'])->toContain('unknown_kind');

    $serialized = AuditLog::query()->get()
        ->map(fn (AuditLog $log): string => json_encode($log->changes).json_encode($log->context))
        ->implode('');

    expect($serialized)->not->toContain('AUDIT-NORM-TOKEN');
});

/* ---------------------------------------------------------------------------
 | Review tasks
 * ------------------------------------------------------------------------- */

test('normalization issues raise one deduplicated review task and a clean rebuild dismisses it', function () {
    [$instance, $library] = seedNormalizationConnector('jellyfin', 'REVIEW-NORM-TOKEN');
    $item = seedNormalizationItem($instance, $library, 'jf-1', 'Weird', 'unknown', ['year' => null, 'runtime_seconds' => null]);

    normalizeConnector($instance);
    normalizeConnector($instance); // repeated problems must not flood the queue

    $tasks = ReviewTask::query()->where('task_type', 'catalog_normalization')->get();
    expect($tasks)->toHaveCount(1);

    $task = $tasks->sole();
    expect($task->status)->toBe('open')
        ->and(array_column($task->evidence['issues'], 'code'))->toContain('unknown_kind')
        ->and($task->evidence['connector'])->toBe('jellyfin')
        ->and(json_encode($task->evidence))->not->toContain('REVIEW-NORM-TOKEN');

    // Once the data is good, the queue heals itself.
    $item->media_kind = 'movie';
    $item->year = 1999;
    $item->runtime_seconds = 7200;
    $item->save();

    normalizeConnector($instance);

    expect(ReviewTask::query()->where('task_type', 'catalog_normalization')->where('status', 'open')->count())->toBe(0)
        ->and(ReviewTask::query()->where('task_type', 'catalog_normalization')->where('status', 'dismissed')->count())->toBe(1);
});

test('the normalization review task summarises issues by count instead of one task per item', function () {
    [$instance, $library] = seedNormalizationConnector();

    for ($i = 0; $i < 5; $i++) {
        seedNormalizationItem($instance, $library, "jf-{$i}", "Weird {$i}", 'unknown', ['year' => null, 'runtime_seconds' => null]);
    }

    normalizeConnector($instance);

    // Five broken items → exactly ONE actionable task carrying the counts.
    $task = ReviewTask::query()->where('task_type', 'catalog_normalization')->sole();
    $issues = collect($task->evidence['issues'])->keyBy('code');

    expect($issues['unknown_kind']['item_count'])->toBe(5)
        ->and($task->evidence['needs_review_count'])->toBe(5);
});

/* ---------------------------------------------------------------------------
 | Snapshot integration
 * ------------------------------------------------------------------------- */

test('a snapshot normalizes the items it just captured', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();

    Http::fake(['*/Items*' => Http::response([
        'Items' => [
            ['Id' => 'jf-1', 'Name' => '  The   Matrix ', 'Type' => 'Movie', 'ProductionYear' => 1999, 'RunTimeTicks' => 81_600_000_000],
            ['Id' => 'jf-2', 'Name' => 'Mystery', 'Type' => 'Nonsense'],
        ],
        'TotalRecordCount' => 2,
    ], 200)]);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot");

    // Normalization ran as part of the snapshot — no separate step needed.
    expect(ConnectorCatalogItemNormalization::query()->count())->toBe(2);

    $matrix = ConnectorCatalogItemNormalization::query()
        ->whereHas('item', fn ($q) => $q->where('external_id', 'jf-1'))->sole();

    expect($matrix->normalized_title)->toBe('The Matrix')
        ->and($matrix->release_year)->toBe(1999)
        ->and($matrix->status)->toBe('clean');

    expect(MediaItem::query()->count())->toBe(0)
        ->and(MediaEdition::query()->count())->toBe(0)
        ->and(MediaFile::query()->count())->toBe(0);
});

test('a failed snapshot does not normalize and leaves earlier normalization intact', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();

    Http::fakeSequence('*/Items*')
        ->push(['Items' => [['Id' => 'jf-1', 'Name' => 'Kept', 'Type' => 'Movie', 'ProductionYear' => 1999, 'RunTimeTicks' => 81_600_000_000]], 'TotalRecordCount' => 1], 200)
        ->push([], 500);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot");
    expect(ConnectorCatalogItemNormalization::query()->count())->toBe(1);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot")
        ->assertSessionHas('error');

    // Still exactly the one row from the successful run.
    expect(ConnectorCatalogItemNormalization::query()->count())->toBe(1)
        ->and(ConnectorCatalogItemNormalization::query()->sole()->normalized_title)->toBe('Kept');
});
