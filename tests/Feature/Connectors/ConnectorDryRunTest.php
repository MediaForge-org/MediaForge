<?php

declare(strict_types=1);

use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Models\ConnectorSyncRun;
use App\Connectors\Sdk\Models\ConnectorSyncRunLibrary;
use App\Connectors\Sdk\Secrets\SecretStore;
use App\Core\Audit\AuditLog;
use App\Core\Media\MediaEdition;
use App\Core\Media\MediaFile;
use App\Core\Media\MediaItem;
use App\Core\Review\ReviewTask;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    // A dry run must NEVER hit the network. Any stray request fails the test.
    Http::preventStrayRequests();
});

/**
 * Build a fully configured connector directly (no HTTP), with a stored secret and
 * an optional set of libraries: [external_id, name, type, enabled, status].
 *
 * @param  list<array{0: string, 1: string, 2: string, 3: bool, 4?: string}>  $libraries
 */
function seedDryRunConnector(string $key = 'jellyfin', string $health = 'healthy', array $libraries = [], string $token = 'DRY-TOKEN'): ConnectorInstance
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

    foreach ($libraries as $library) {
        ConnectorLibrary::query()->create([
            'connector_instance_id' => $instance->id,
            'provider_key' => $key,
            'external_id' => $library[0],
            'name' => $library[1],
            'collection_type' => $library[2],
            'is_enabled' => $library[3],
            'discovery_status' => $library[4] ?? 'present',
            'last_seen_at' => now(),
        ]);
    }

    return $instance;
}

test('guests cannot run a connector dry run', function () {
    $this->post('/connectors/jellyfin/sync/dry-run')
        ->assertRedirect('/login');
});

test('a dry run requires a configured connector', function () {
    $this->actingAs(User::factory()->create())
        ->post('/connectors/jellyfin/sync/dry-run')
        ->assertSessionHas('error');

    expect(ConnectorSyncRun::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('a dry run over a healthy connector with a selected library completes and is ready for future sync', function () {
    $user = User::factory()->create();
    $instance = seedDryRunConnector('jellyfin', 'healthy', [
        ['jf-movies', 'Movies', 'movies', true],
        ['jf-shows', 'TV Shows', 'tvshows', false],
    ]);

    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run')
        ->assertSessionHas('success');

    Http::assertNothingSent();

    $run = ConnectorSyncRun::query()->where('connector_instance_id', $instance->id)->sole();
    expect($run->status)->toBe('completed')
        ->and($run->mode)->toBe('dry_run')
        ->and($run->summary['selected_count'])->toBe(1)
        ->and($run->summary['ready_for_future_sync'])->toBeTrue();

    $rows = ConnectorSyncRunLibrary::query()->where('connector_sync_run_id', $run->id)->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->firstWhere('external_id', 'jf-movies')?->planned_action)->toBe('future_sync_candidate')
        ->and($rows->firstWhere('external_id', 'jf-movies')?->status)->toBe('ready')
        ->and($rows->firstWhere('external_id', 'jf-shows')?->planned_action)->toBe('skipped_not_selected');

    // A clean run raises no review task.
    expect(ReviewTask::query()->where('task_type', 'connector_sync')->count())->toBe(0);
});

test('a dry run with no selected libraries completes with warnings and raises a review task', function () {
    $user = User::factory()->create();
    seedDryRunConnector('jellyfin', 'healthy', [
        ['jf-movies', 'Movies', 'movies', false],
    ]);

    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');

    $run = ConnectorSyncRun::query()->sole();
    expect($run->status)->toBe('completed_with_warnings');

    $task = ReviewTask::query()->where('task_type', 'connector_sync')->sole();
    $codes = array_column($task->evidence['issues'], 'code');
    expect($codes)->toContain('no_selected_libraries')
        ->and($task->status)->toBe('open');
});

test('a dry run flags a selected library that is missing from the latest discovery', function () {
    $user = User::factory()->create();
    seedDryRunConnector('jellyfin', 'healthy', [
        ['jf-movies', 'Movies', 'movies', true, 'missing'],
    ]);

    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');

    $run = ConnectorSyncRun::query()->sole();
    expect($run->status)->toBe('completed_with_warnings');

    $row = ConnectorSyncRunLibrary::query()->where('external_id', 'jf-movies')->sole();
    expect($row->status)->toBe('warning')
        ->and($row->planned_action)->toBe('skipped_missing');

    $task = ReviewTask::query()->where('task_type', 'connector_sync')->sole();
    expect(array_column($task->evidence['issues'], 'code'))->toContain('selected_library_missing');
});

test('a dry run creates no media items, editions or files', function () {
    $user = User::factory()->create();
    seedDryRunConnector('jellyfin', 'healthy', [
        ['jf-movies', 'Movies', 'movies', true],
    ]);

    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');

    expect(MediaItem::query()->count())->toBe(0)
        ->and(MediaEdition::query()->count())->toBe(0)
        ->and(MediaFile::query()->count())->toBe(0)
        ->and(ConnectorLibrary::query()->count())->toBe(1); // no libraries conjured
});

test('repeated dry runs do not duplicate the review task and a later clean run dismisses it', function () {
    $user = User::factory()->create();
    // Untested connector with no selected libraries → attention.
    $instance = seedDryRunConnector('jellyfin', 'unknown', [
        ['jf-movies', 'Movies', 'movies', false],
    ]);

    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');
    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');
    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');

    // Three runs recorded, but only ONE open review task.
    expect(ConnectorSyncRun::query()->count())->toBe(3)
        ->and(ReviewTask::query()->where('task_type', 'connector_sync')->where('status', 'open')->count())->toBe(1);

    // Fix the conditions, then a clean run self-heals the queue.
    $instance->update(['health_status' => 'healthy']);
    ConnectorLibrary::query()->where('connector_instance_id', $instance->id)->update(['is_enabled' => true]);

    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run')->assertSessionHas('success');

    expect(ReviewTask::query()->where('task_type', 'connector_sync')->where('status', 'open')->count())->toBe(0)
        ->and(ReviewTask::query()->where('task_type', 'connector_sync')->where('status', 'dismissed')->count())->toBe(1);
});

test('the dry run never exposes the raw token in the run, audit or response', function () {
    $user = User::factory()->create();
    seedDryRunConnector('jellyfin', 'unknown', [
        ['jf-movies', 'Movies', 'movies', false],
    ], token: 'LEAKY-DRY-TOKEN');

    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');

    $run = ConnectorSyncRun::query()->sole();
    expect(json_encode($run->summary))->not->toContain('LEAKY-DRY-TOKEN')
        ->and($run->created_by)->not->toContain('LEAKY-DRY-TOKEN');

    $serialized = AuditLog::query()->get()
        ->map(fn (AuditLog $log): string => json_encode($log->changes).json_encode($log->context))
        ->implode('');
    $reviews = ReviewTask::query()->get()->map(fn (ReviewTask $t): string => json_encode($t->evidence))->implode('');

    expect($serialized)->not->toContain('LEAKY-DRY-TOKEN')
        ->and($reviews)->not->toContain('LEAKY-DRY-TOKEN');
});

test('the dry run records a sanitized audit entry', function () {
    $user = User::factory()->create();
    seedDryRunConnector('jellyfin', 'healthy', [
        ['jf-movies', 'Movies', 'movies', true],
    ]);

    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');

    $entry = AuditLog::query()->where('action', 'connector.dry_run_completed')->sole();
    expect($entry->changes['status'])->toBe('completed')
        ->and($entry->context['connector'])->toBe('jellyfin');
});

test('the connector detail page exposes the sync foundation section and last run without the secret', function () {
    $user = User::factory()->create();
    seedDryRunConnector('jellyfin', 'healthy', [
        ['jf-movies', 'Movies', 'movies', true],
    ], token: 'SHOW-TOKEN');

    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');

    $this->actingAs($user)->get('/connectors/jellyfin')
        ->assertOk()
        ->assertDontSee('SHOW-TOKEN', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Connectors/Show')
            ->where('connector.sync.status', 'last_dry_run_completed')
            ->where('connector.sync.selected_count', 1)
            ->where('connector.sync.last_run.status', 'completed')
            ->has('connector.sync.last_run.libraries', 1)
            ->where('connector.sync.last_run.libraries.0.planned_action', 'future_sync_candidate'));
});

test('the connectors overview exposes sync foundation status per connector', function () {
    $user = User::factory()->create();
    seedDryRunConnector('jellyfin', 'healthy', [['jf-movies', 'Movies', 'movies', true]]);

    $this->actingAs($user)->get('/connectors')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Connectors/Index')
            ->where('connectors.0.key', 'jellyfin')
            ->where('connectors.0.sync.status', 'ready_for_dry_run')
            ->where('connectors.0.sync.selected_count', 1));
});
