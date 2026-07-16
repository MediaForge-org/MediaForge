<?php

declare(strict_types=1);

use App\Connectors\Sdk\Actions\RunConnectorCatalogSnapshot;
use App\Connectors\Sdk\Catalog\CatalogSnapshotRequest;
use App\Connectors\Sdk\Catalog\CatalogSnapshotResult;
use App\Connectors\Sdk\Contracts\ConnectorProvider;
use App\Connectors\Sdk\Diagnostics\LibraryDiscoveryRequest;
use App\Connectors\Sdk\Diagnostics\LibraryDiscoveryResult;
use App\Connectors\Sdk\Diagnostics\TestConnectionRequest;
use App\Connectors\Sdk\Diagnostics\TestConnectionResult;
use App\Connectors\Sdk\Models\ConnectorCatalogItem;
use App\Connectors\Sdk\Models\ConnectorCatalogSnapshotRun;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Registry\ConnectorRegistry;
use App\Connectors\Sdk\Secrets\SecretStore;
use App\Core\Audit\AuditLog;
use App\Core\Media\MediaEdition;
use App\Core\Media\MediaFile;
use App\Core\Media\MediaItem;
use App\Core\Review\ReviewTask;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
    // A snapshot must ONLY hit the network inside an explicit snapshot test.
    Http::preventStrayRequests();
});

/**
 * A configured connector with one discovered library — built directly, no HTTP.
 *
 * @return array{0: ConnectorInstance, 1: ConnectorLibrary}
 */
function seedCatalogConnector(string $key = 'jellyfin', string $token = 'CATALOG-TOKEN', string $health = 'healthy', string $libraryType = 'movies'): array
{
    $ref = (string) Str::ulid();
    app(SecretStore::class)->put($ref, $token);

    $instance = ConnectorInstance::query()->create([
        'connector_key' => $key,
        'name' => ucfirst($key),
        'base_url' => 'http://'.$key.'.local:8096',
        'secrets_ref' => $ref,
        'health_status' => $health,
        'libraries_discovered_at' => now(),
    ]);

    $library = ConnectorLibrary::query()->create([
        'connector_instance_id' => $instance->id,
        'provider_key' => $key,
        'external_id' => $key.'-lib',
        'name' => 'Movies',
        'collection_type' => $libraryType,
        'is_enabled' => true,
        'discovery_status' => 'present',
        'last_seen_at' => now(),
    ]);

    return [$instance, $library];
}

/** @param list<array<string, mixed>> $items */
function fakeJellyfinItems(array $items, ?int $total = null): void
{
    Http::fake(['*/Items*' => Http::response([
        'Items' => $items,
        'TotalRecordCount' => $total ?? count($items),
    ], 200)]);
}

/**
 * A page of `$count` distinct Jellyfin items starting at index `$start`.
 *
 * @return list<array<string, mixed>>
 */
function jellyfinPage(int $start, int $count): array
{
    $items = [];

    for ($i = $start; $i < $start + $count; $i++) {
        $items[] = ['Id' => "jf-{$i}", 'Name' => "Item {$i}", 'Type' => 'Movie'];
    }

    return $items;
}

/**
 * A page of `$count` distinct Audiobookshelf items starting at index `$start`.
 *
 * @return list<array<string, mixed>>
 */
function audiobookshelfPage(int $start, int $count): array
{
    $results = [];

    for ($i = $start; $i < $start + $count; $i++) {
        $results[] = ['id' => "abs-{$i}", 'mediaType' => 'book', 'media' => ['metadata' => ['title' => "Book {$i}"]]];
    }

    return $results;
}

test('guests cannot run a catalog snapshot', function () {
    [, $library] = seedCatalogConnector();

    $this->post("/connectors/jellyfin/libraries/{$library->id}/snapshot")
        ->assertRedirect('/login');

    expect(ConnectorCatalogSnapshotRun::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('the snapshot route is POST only and there is no GET snapshot route', function () {
    $route = Route::getRoutes()->getByName('connectors.catalog.snapshot');

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain('POST')
        ->and($route->methods())->not->toContain('GET');
});

test('a snapshot requires a configured connector', function () {
    $user = User::factory()->create();
    // A library that exists but whose connector has no stored secret.
    $instance = ConnectorInstance::query()->create([
        'connector_key' => 'jellyfin',
        'name' => 'Jellyfin',
        'base_url' => 'http://jellyfin.local:8096',
        'secrets_ref' => (string) Str::ulid(),
        'health_status' => 'unknown',
    ]);
    $library = ConnectorLibrary::query()->create([
        'connector_instance_id' => $instance->id,
        'provider_key' => 'jellyfin',
        'external_id' => 'jf-lib',
        'name' => 'Movies',
        'is_enabled' => true,
        'discovery_status' => 'present',
    ]);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot")
        ->assertSessionHas('error');

    expect(ConnectorCatalogSnapshotRun::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('a snapshot requires a discovered library that belongs to the connector', function () {
    $user = User::factory()->create();
    seedCatalogConnector('jellyfin');
    // A library id that belongs to no connector at all.
    $foreignId = (string) Str::ulid();

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$foreignId}/snapshot")
        ->assertNotFound();

    expect(ConnectorCatalogSnapshotRun::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('a snapshot stores a run and upserts external catalog items without leaking the token', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogConnector('jellyfin', 'LEAKY-SNAPSHOT-TOKEN');

    fakeJellyfinItems([
        ['Id' => 'jf-1', 'Name' => 'The Matrix', 'Type' => 'Movie', 'ProductionYear' => 1999, 'RunTimeTicks' => 81_600_000_000, 'SortName' => 'Matrix, The', 'OriginalTitle' => 'The Matrix'],
        ['Id' => 'jf-2', 'Name' => 'Arrival', 'Type' => 'Movie', 'ProductionYear' => 2016],
    ]);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot")
        ->assertSessionHas('success');

    // Token is sent as a header only — never in the query string.
    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/Items')
            && $request->hasHeader('X-Emby-Token', 'LEAKY-SNAPSHOT-TOKEN')
            && !str_contains($request->url(), 'LEAKY-SNAPSHOT-TOKEN');
    });

    $run = ConnectorCatalogSnapshotRun::query()->sole();
    expect($run->status)->toBe('completed')
        ->and($run->items_stored_count)->toBe(2)
        ->and($run->items_seen_count)->toBe(2)
        ->and($run->connector_library_id)->toBe($library->id);

    $items = ConnectorCatalogItem::query()->where('connector_instance_id', $instance->id)->get();
    expect($items)->toHaveCount(2);

    $matrix = $items->firstWhere('external_id', 'jf-1');
    expect($matrix?->title)->toBe('The Matrix')
        ->and($matrix?->media_kind)->toBe('movie')
        ->and($matrix?->year)->toBe(1999)
        ->and($matrix?->runtime_seconds)->toBe(8160)
        ->and($matrix?->sort_title)->toBe('Matrix, The')
        ->and($matrix?->is_present)->toBeTrue()
        ->and($matrix?->missing_since)->toBeNull()
        ->and($matrix?->snapshot_run_id)->toBe($run->id);

    // No secret and no raw payload anywhere in the stored read-model.
    $serialized = json_encode($items->toArray()).json_encode($run->summary);
    expect($serialized)->not->toContain('LEAKY-SNAPSHOT-TOKEN')
        ->and($matrix?->metadata)->toBe([]);
});

test('a repeated snapshot updates items in place and never duplicates them', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogConnector();

    Http::fakeSequence('*/Items*')
        ->push(['Items' => [['Id' => 'jf-1', 'Name' => 'The Matrix', 'Type' => 'Movie']], 'TotalRecordCount' => 1], 200)
        ->push(['Items' => [['Id' => 'jf-1', 'Name' => 'The Matrix Reloaded', 'Type' => 'Movie']], 'TotalRecordCount' => 1], 200);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot");
    $first = ConnectorCatalogItem::query()->sole();
    $firstSeenAt = $first->first_seen_at;

    $this->travel(2)->seconds();
    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot");

    expect(ConnectorCatalogItem::query()->count())->toBe(1)
        ->and(ConnectorCatalogSnapshotRun::query()->count())->toBe(2);

    $updated = ConnectorCatalogItem::query()->sole();
    expect($updated->id)->toBe($first->id) // same row, upserted
        ->and($updated->title)->toBe('The Matrix Reloaded')
        ->and($updated->first_seen_at?->timestamp)->toBe($firstSeenAt?->timestamp) // unchanged
        ->and($updated->last_seen_at?->greaterThan($first->last_seen_at))->toBeTrue();
});

test('an item that vanishes between snapshots is flagged missing instead of deleted', function () {
    $user = User::factory()->create();
    [, $library] = seedCatalogConnector();

    Http::fakeSequence('*/Items*')
        ->push(['Items' => [
            ['Id' => 'jf-1', 'Name' => 'Kept', 'Type' => 'Movie'],
            ['Id' => 'jf-2', 'Name' => 'Vanishes', 'Type' => 'Movie'],
        ], 'TotalRecordCount' => 2], 200)
        ->push(['Items' => [['Id' => 'jf-1', 'Name' => 'Kept', 'Type' => 'Movie']], 'TotalRecordCount' => 1], 200);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot");
    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot");

    // Nothing is deleted — the vanished item is retained and flagged.
    expect(ConnectorCatalogItem::query()->count())->toBe(2);

    $kept = ConnectorCatalogItem::query()->where('external_id', 'jf-1')->sole();
    $gone = ConnectorCatalogItem::query()->where('external_id', 'jf-2')->sole();

    expect($kept->is_present)->toBeTrue()
        ->and($kept->missing_since)->toBeNull()
        ->and($gone->is_present)->toBeFalse()
        ->and($gone->missing_since)->not->toBeNull();
});

test('a failed snapshot records a sanitized error, keeps existing items present and raises a review task', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogConnector('jellyfin', 'FAIL-TOKEN');

    Http::fakeSequence('*/Items*')
        ->push(['Items' => [['Id' => 'jf-1', 'Name' => 'Kept', 'Type' => 'Movie']], 'TotalRecordCount' => 1], 200)
        ->push([], 401);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot");
    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot")
        ->assertSessionHas('error');

    // Select the failed run by status: both runs share a created_at second, so
    // ordering by timestamp alone would be ambiguous here.
    expect(ConnectorCatalogSnapshotRun::query()->count())->toBe(2);

    $failed = ConnectorCatalogSnapshotRun::query()->where('status', 'failed')->sole();
    expect($failed->errors_count)->toBe(1)
        ->and($failed->items_stored_count)->toBe(0)
        ->and($failed->error_message)->toContain('401')
        ->and($failed->error_message)->not->toContain('FAIL-TOKEN');

    // A failed read must NOT wipe or flag previously captured items.
    $kept = ConnectorCatalogItem::query()->where('external_id', 'jf-1')->sole();
    expect($kept->is_present)->toBeTrue()
        ->and($kept->missing_since)->toBeNull();

    $task = ReviewTask::query()->where('task_type', 'connector_catalog')->sole();
    expect(array_column($task->evidence['issues'], 'code'))->toContain('snapshot_failed')
        ->and($task->status)->toBe('open')
        ->and(json_encode($task->evidence))->not->toContain('FAIL-TOKEN');
});

test('a truncated snapshot stores only the bounded subset and raises a warning review task', function () {
    $user = User::factory()->create();
    [, $library] = seedCatalogConnector();

    // One full page whose remote reports far more items than a single page returns.
    $pageSize = RunConnectorCatalogSnapshot::PAGE_SIZE;
    $items = [];
    for ($i = 0; $i < $pageSize; $i++) {
        $items[] = ['Id' => "jf-{$i}", 'Name' => "Item {$i}", 'Type' => 'Movie'];
    }

    // Every page returns the same full page; the remote claims 3x that total, so the
    // read is always short of the total and is marked truncated.
    fakeJellyfinItems($items, total: $pageSize * 3);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot")
        ->assertSessionHas('error'); // completed_with_warnings

    $run = ConnectorCatalogSnapshotRun::query()->sole();
    expect($run->status)->toBe('completed_with_warnings')
        ->and($run->items_stored_count)->toBe($pageSize)
        ->and($run->warnings_count)->toBe(1)
        ->and($run->summary['truncated'])->toBeTrue();

    // Bounded: never stores more than a page of distinct ids even though the remote has 3x.
    expect(ConnectorCatalogItem::query()->count())->toBe($pageSize);

    $task = ReviewTask::query()->where('task_type', 'connector_catalog')->sole();
    expect(array_column($task->evidence['issues'], 'code'))->toContain('snapshot_truncated');
});

test('the snapshot request honours the bounded page size', function () {
    $user = User::factory()->create();
    [, $library] = seedCatalogConnector();

    fakeJellyfinItems([['Id' => 'jf-1', 'Name' => 'One', 'Type' => 'Movie']]);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot");

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'Limit='.RunConnectorCatalogSnapshot::PAGE_SIZE));
});

test('a Jellyfin snapshot pages through the remote and stores items from every page', function () {
    $user = User::factory()->create();
    [, $library] = seedCatalogConnector();
    $pageSize = RunConnectorCatalogSnapshot::PAGE_SIZE;
    $total = $pageSize + 250;

    // A full first page (→ keep paging) followed by a short final page (→ stop).
    Http::fakeSequence('*/Items*')
        ->push(['Items' => jellyfinPage(0, $pageSize), 'TotalRecordCount' => $total], 200)
        ->push(['Items' => jellyfinPage($pageSize, 250), 'TotalRecordCount' => $total], 200);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot")
        ->assertSessionHas('success');

    $run = ConnectorCatalogSnapshotRun::query()->sole();
    expect($run->status)->toBe('completed')
        ->and($run->items_stored_count)->toBe($total)
        ->and($run->summary['truncated'])->toBeFalse();

    // Items from BOTH pages are stored — the read is complete, so not truncated.
    expect(ConnectorCatalogItem::query()->count())->toBe($total)
        ->and(ConnectorCatalogItem::query()->where('external_id', 'jf-0')->exists())->toBeTrue()
        ->and(ConnectorCatalogItem::query()->where('external_id', 'jf-'.($total - 1))->exists())->toBeTrue();

    // The offset advances by a page each read.
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'StartIndex=0'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'StartIndex='.$pageSize));
});

test('a snapshot stops at the hard item cap and marks the run truncated', function () {
    $user = User::factory()->create();
    [, $library] = seedCatalogConnector();
    $pageSize = RunConnectorCatalogSnapshot::PAGE_SIZE;
    $cap = RunConnectorCatalogSnapshot::MAX_ITEMS_PER_SNAPSHOT;
    $expectedPages = intdiv($cap, $pageSize);

    // A remote that would happily keep serving full pages forever — one more page
    // than the cap allows, so an unbounded loop would be caught.
    $sequence = Http::fakeSequence('*/Items*');
    for ($page = 0; $page <= $expectedPages; $page++) {
        $sequence->push(['Items' => jellyfinPage($page * $pageSize, $pageSize), 'TotalRecordCount' => 100_000], 200);
    }

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot")
        ->assertSessionHas('error'); // completed_with_warnings

    $run = ConnectorCatalogSnapshotRun::query()->sole();
    expect($run->status)->toBe('completed_with_warnings')
        ->and($run->items_stored_count)->toBe($cap)
        ->and($run->summary['truncated'])->toBeTrue()
        ->and($run->summary['cap'])->toBe($cap)
        ->and($run->summary['remote_total'])->toBe(100_000);

    // Bounded: never reads more pages than the cap allows, never stores past the cap.
    Http::assertSentCount($expectedPages);
    expect(ConnectorCatalogItem::query()->count())->toBe($cap);

    $task = ReviewTask::query()->where('task_type', 'connector_catalog')->sole();
    expect(array_column($task->evidence['issues'], 'code'))->toContain('snapshot_truncated');
});

test('a repeated paginated snapshot upserts across pages without duplicating items', function () {
    $user = User::factory()->create();
    [, $library] = seedCatalogConnector();
    $pageSize = RunConnectorCatalogSnapshot::PAGE_SIZE;
    $total = $pageSize + 10;

    Http::fakeSequence('*/Items*')
        ->push(['Items' => jellyfinPage(0, $pageSize), 'TotalRecordCount' => $total], 200)
        ->push(['Items' => jellyfinPage($pageSize, 10), 'TotalRecordCount' => $total], 200)
        ->push(['Items' => jellyfinPage(0, $pageSize), 'TotalRecordCount' => $total], 200)
        ->push(['Items' => jellyfinPage($pageSize, 10), 'TotalRecordCount' => $total], 200);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot");
    expect(ConnectorCatalogItem::query()->count())->toBe($total);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot");

    // Same rows, upserted in place — a second paginated run never duplicates them.
    expect(ConnectorCatalogItem::query()->count())->toBe($total)
        ->and(ConnectorCatalogSnapshotRun::query()->count())->toBe(2)
        ->and(ConnectorCatalogItem::query()->where('is_present', false)->count())->toBe(0);
});

test('a failed later page keeps the captured items, marks the run truncated and never flags items missing', function () {
    $user = User::factory()->create();
    [, $library] = seedCatalogConnector();
    $pageSize = RunConnectorCatalogSnapshot::PAGE_SIZE;

    Http::fakeSequence('*/Items*')
        ->push(['Items' => jellyfinPage(0, $pageSize), 'TotalRecordCount' => 100_000], 200)
        ->push([], 500);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot")
        ->assertSessionHas('error');

    // The captured first page is kept; a partial read is a warning, not a hard failure.
    $run = ConnectorCatalogSnapshotRun::query()->sole();
    expect($run->status)->toBe('completed_with_warnings')
        ->and($run->items_stored_count)->toBe($pageSize)
        ->and($run->summary['truncated'])->toBeTrue();

    // An incomplete read must NEVER flag the un-read tail as missing.
    expect(ConnectorCatalogItem::query()->count())->toBe($pageSize)
        ->and(ConnectorCatalogItem::query()->where('is_present', false)->count())->toBe(0);
});

test('an Audiobookshelf snapshot advances the zero-based page index across pages', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogConnector('audiobookshelf', 'ABS-PAGE-TOKEN', 'healthy', 'book');
    $pageSize = RunConnectorCatalogSnapshot::PAGE_SIZE;
    $total = $pageSize + 5;

    Http::fakeSequence('*/api/libraries/*/items*')
        ->push(['results' => audiobookshelfPage(0, $pageSize), 'total' => $total], 200)
        ->push(['results' => audiobookshelfPage($pageSize, 5), 'total' => $total], 200);

    $this->actingAs($user)->post("/connectors/audiobookshelf/libraries/{$library->id}/snapshot")
        ->assertSessionHas('success');

    expect(ConnectorCatalogItem::query()->where('connector_instance_id', $instance->id)->count())->toBe($total);

    // Audiobookshelf pages by a zero-based index; the token stays a header on every page.
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'page=0'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'page=1')
        && $request->hasHeader('Authorization', 'Bearer ABS-PAGE-TOKEN')
        && !str_contains($request->url(), 'ABS-PAGE-TOKEN'));
});

test('repeated snapshot problems do not duplicate the review task and a clean snapshot dismisses it', function () {
    $user = User::factory()->create();
    [, $library] = seedCatalogConnector();

    Http::fakeSequence('*/Items*')
        ->push([], 500)
        ->push([], 500)
        ->push(['Items' => [['Id' => 'jf-1', 'Name' => 'Ok', 'Type' => 'Movie']], 'TotalRecordCount' => 1], 200);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot");
    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot");

    expect(ReviewTask::query()->where('task_type', 'connector_catalog')->where('status', 'open')->count())->toBe(1);

    // A clean snapshot self-heals the queue.
    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot")
        ->assertSessionHas('success');

    expect(ReviewTask::query()->where('task_type', 'connector_catalog')->where('status', 'open')->count())->toBe(0)
        ->and(ReviewTask::query()->where('task_type', 'connector_catalog')->where('status', 'dismissed')->count())->toBe(1);
});

test('a snapshot creates no media items, editions or files and touches no connector libraries', function () {
    $user = User::factory()->create();
    [, $library] = seedCatalogConnector();

    fakeJellyfinItems([
        ['Id' => 'jf-1', 'Name' => 'The Matrix', 'Type' => 'Movie'],
        ['Id' => 'jf-2', 'Name' => 'Arrival', 'Type' => 'Movie'],
    ]);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot");

    expect(MediaItem::query()->count())->toBe(0)
        ->and(MediaEdition::query()->count())->toBe(0)
        ->and(MediaFile::query()->count())->toBe(0)
        ->and(ConnectorLibrary::query()->count())->toBe(1); // no libraries conjured
});

test('the snapshot records a sanitized audit entry that never contains the raw token', function () {
    $user = User::factory()->create();
    [, $library] = seedCatalogConnector('jellyfin', 'AUDIT-SNAPSHOT-TOKEN');

    fakeJellyfinItems([['Id' => 'jf-1', 'Name' => 'The Matrix', 'Type' => 'Movie']]);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot");

    $entry = AuditLog::query()->where('action', 'connector.catalog_snapshot_completed')->sole();
    expect($entry->changes['status'])->toBe('completed')
        ->and($entry->context['connector'])->toBe('jellyfin');

    $serialized = AuditLog::query()->get()
        ->map(fn (AuditLog $log): string => json_encode($log->changes).json_encode($log->context))
        ->implode('');

    expect($serialized)->not->toContain('AUDIT-SNAPSHOT-TOKEN');
});

test('an Audiobookshelf snapshot sends the bearer token as a header and maps items', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogConnector('audiobookshelf', 'ABS-SNAPSHOT-TOKEN', 'healthy', 'book');

    Http::fake(['*/api/libraries/*/items*' => Http::response([
        'results' => [
            ['id' => 'abs-1', 'mediaType' => 'book', 'media' => ['duration' => 3600.5, 'metadata' => ['title' => 'Dune', 'publishedYear' => '1965', 'titleIgnorePrefix' => 'Dune']]],
        ],
        'total' => 1,
    ], 200)]);

    $this->actingAs($user)->post("/connectors/audiobookshelf/libraries/{$library->id}/snapshot")
        ->assertSessionHas('success');

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/api/libraries/')
            && $request->hasHeader('Authorization', 'Bearer ABS-SNAPSHOT-TOKEN')
            && !str_contains($request->url(), 'ABS-SNAPSHOT-TOKEN');
    });

    $item = ConnectorCatalogItem::query()->where('connector_instance_id', $instance->id)->sole();
    expect($item->title)->toBe('Dune')
        ->and($item->media_kind)->toBe('audiobook')
        ->and($item->year)->toBe(1965)
        ->and($item->runtime_seconds)->toBe(3601); // rounded from 3600.5
});

/** A provider that cannot snapshot yet — models the capability fallback. */
final class UnsupportedSnapshotProvider implements ConnectorProvider
{
    public function key(): string
    {
        return 'jellyfin';
    }

    public function label(): string
    {
        return 'Jellyfin';
    }

    public function testConnection(TestConnectionRequest $request): TestConnectionResult
    {
        return TestConnectionResult::healthy('ok', 200);
    }

    public function discoverLibraries(LibraryDiscoveryRequest $request): LibraryDiscoveryResult
    {
        return LibraryDiscoveryResult::success([], 'ok');
    }

    public function supportsCatalogSnapshot(): bool
    {
        return false;
    }

    public function snapshotLibraryItems(CatalogSnapshotRequest $request): CatalogSnapshotResult
    {
        return CatalogSnapshotResult::unsupported('Not supported.');
    }
}

test('a provider without snapshot support is handled explicitly and never calls the network', function () {
    $user = User::factory()->create();
    [, $library] = seedCatalogConnector();

    // Swap the registered provider for one that reports no snapshot capability.
    app(ConnectorRegistry::class)->register(new UnsupportedSnapshotProvider);

    $this->actingAs($user)->post("/connectors/jellyfin/libraries/{$library->id}/snapshot")
        ->assertSessionHas('error');

    Http::assertNothingSent();

    $run = ConnectorCatalogSnapshotRun::query()->sole();
    expect($run->status)->toBe('completed_with_warnings')
        ->and($run->items_stored_count)->toBe(0)
        ->and($run->summary['outcome'])->toBe('unsupported');

    expect(ConnectorCatalogItem::query()->count())->toBe(0);

    $task = ReviewTask::query()->where('task_type', 'connector_catalog')->sole();
    expect(array_column($task->evidence['issues'], 'code'))->toContain('snapshot_unsupported');
});
