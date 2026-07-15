<?php

declare(strict_types=1);

use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Secrets\SecretStore;
use App\Core\Audit\AuditLog;
use App\Core\Review\ReviewTask;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
});

/** A configured, unattended Jellyfin (no selected library) — no HTTP involved. */
function seedUnattendedJellyfin(string $token = 'RESOLVE-TOKEN'): ConnectorInstance
{
    $ref = (string) Str::ulid();
    app(SecretStore::class)->put($ref, $token);

    $instance = ConnectorInstance::query()->create([
        'connector_key' => 'jellyfin',
        'name' => 'Jellyfin',
        'base_url' => 'http://jellyfin.local:8096',
        'secrets_ref' => $ref,
        'health_status' => 'healthy',
        'libraries_discovered_at' => now(),
    ]);

    ConnectorLibrary::query()->create([
        'connector_instance_id' => $instance->id,
        'provider_key' => 'jellyfin',
        'external_id' => 'jf-movies',
        'name' => 'Movies',
        'collection_type' => 'movies',
        'is_enabled' => false,
        'discovery_status' => 'present',
        'last_seen_at' => now(),
    ]);

    return $instance;
}

test('an authenticated user can dismiss an open connector_sync review task', function () {
    $user = User::factory()->create();
    seedUnattendedJellyfin();
    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');
    $task = ReviewTask::query()->sole();

    $this->actingAs($user)->post("/review/tasks/{$task->id}/dismiss")
        ->assertSessionHas('success');

    $task->refresh();
    expect($task->status)->toBe('dismissed')
        ->and($task->resolution)->toBe(['reason' => 'dismissed_by_user'])
        ->and($task->resolved_by)->toBe($user->id)
        ->and($task->resolved_at)->not->toBeNull();
});

test('dismissing a review task is audited', function () {
    $user = User::factory()->create();
    seedUnattendedJellyfin();
    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');
    $task = ReviewTask::query()->sole();

    // Not assertActionIsAudited(): the preceding dry run already wrote a
    // 'connector.dry_run_completed' entry in the same test-clock second, so
    // ordering by created_at alone can tie. Assert the entry by action instead.
    $this->actingAs($user)->post("/review/tasks/{$task->id}/dismiss");

    $entry = AuditLog::query()->where('action', 'review.dismissed')->sole();
    expect($entry->changes['status'])->toBe('dismissed')
        ->and($entry->context['subject_id'])->toBe($task->subject_id)
        ->and($entry->subject_id)->toBe($task->id);
});

test('repeated dismiss of the same task is safe and idempotent', function () {
    $user = User::factory()->create();
    seedUnattendedJellyfin();
    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');
    $task = ReviewTask::query()->sole();

    $this->actingAs($user)->post("/review/tasks/{$task->id}/dismiss")->assertSessionHas('success');
    $this->actingAs($user)->post("/review/tasks/{$task->id}/dismiss")->assertSessionHas('success');

    expect(ReviewTask::query()->count())->toBe(1)
        ->and($task->fresh()->status)->toBe('dismissed');
});

test('a dismissed review task can be reopened', function () {
    $user = User::factory()->create();
    seedUnattendedJellyfin();
    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');
    $task = ReviewTask::query()->sole();

    $this->actingAs($user)->post("/review/tasks/{$task->id}/dismiss");
    $this->actingAs($user)->post("/review/tasks/{$task->id}/reopen")
        ->assertSessionHas('success');

    $task->refresh();
    expect($task->status)->toBe('open')
        ->and($task->resolution)->toBeNull()
        ->and($task->resolved_by)->toBeNull()
        ->and($task->resolved_at)->toBeNull();
});

test('reopening a review task is audited', function () {
    $user = User::factory()->create();
    seedUnattendedJellyfin();
    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');
    $task = ReviewTask::query()->sole();
    $this->actingAs($user)->post("/review/tasks/{$task->id}/dismiss");

    $this->actingAs($user)->post("/review/tasks/{$task->id}/reopen");

    $entry = AuditLog::query()->where('action', 'review.reopened')->sole();
    expect($entry->changes['status'])->toBe('open')
        ->and($entry->context['subject_id'])->toBe($task->subject_id)
        ->and($entry->subject_id)->toBe($task->id);
});

test('guests cannot dismiss or reopen a review task', function () {
    $task = ReviewTask::query()->create([
        'task_type' => 'connector_sync',
        'subject_type' => 'connector_instance',
        'subject_id' => (string) Str::ulid(),
        'status' => 'open',
        'priority' => 'normal',
        'evidence' => ['connector' => 'jellyfin', 'issues' => []],
        'created_by' => 'test',
    ]);

    $this->post("/review/tasks/{$task->id}/dismiss")->assertRedirect('/login');
    $this->post("/review/tasks/{$task->id}/reopen")->assertRedirect('/login');

    expect($task->fresh()->status)->toBe('open');
});

test('dismiss and reopen are POST-only routes, never GET', function () {
    $dismiss = Route::getRoutes()->getByName('review.tasks.dismiss');
    $reopen = Route::getRoutes()->getByName('review.tasks.reopen');

    expect($dismiss)->not->toBeNull()
        ->and($dismiss->methods())->toBe(['POST'])
        ->and($reopen)->not->toBeNull()
        ->and($reopen->methods())->toBe(['POST']);
});

test('a review task outside the connector_sync scope cannot be managed from the review center yet', function () {
    $user = User::factory()->create();
    $task = ReviewTask::query()->create([
        'task_type' => 'media_match',
        'subject_type' => 'media_item',
        'subject_id' => (string) Str::ulid(),
        'status' => 'open',
        'priority' => 'normal',
        'evidence' => [],
        'created_by' => 'test',
    ]);

    $this->actingAs($user)->post("/review/tasks/{$task->id}/dismiss")->assertNotFound();
    $this->actingAs($user)->post("/review/tasks/{$task->id}/reopen")->assertNotFound();

    expect($task->fresh()->status)->toBe('open');
});

test('the raw connector token is never exposed via dismiss/reopen response or audit', function () {
    $user = User::factory()->create();
    seedUnattendedJellyfin('RESOLVE-DO-NOT-LEAK');
    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');
    $task = ReviewTask::query()->sole();

    $response = $this->actingAs($user)->post("/review/tasks/{$task->id}/dismiss");
    $response->assertDontSee('RESOLVE-DO-NOT-LEAK', false);

    $serialized = AuditLog::query()->get()
        ->map(fn (AuditLog $log): string => json_encode($log->changes).json_encode($log->context))
        ->implode('');

    expect($serialized)->not->toContain('RESOLVE-DO-NOT-LEAK');
});
