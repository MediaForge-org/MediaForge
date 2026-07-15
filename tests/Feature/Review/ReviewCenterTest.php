<?php

declare(strict_types=1);

use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Secrets\SecretStore;
use App\Core\Review\ReviewTask;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    // Rendering the review center must never touch the network.
    Http::preventStrayRequests();
});

/**
 * A configured connector, optionally with libraries — no HTTP involved.
 *
 * @param  list<array{0: string, 1: string, 2: string, 3: bool, 4?: string}>  $libraries
 */
function seedReviewConnector(string $key = 'jellyfin', string $health = 'healthy', array $libraries = [], string $token = 'REVIEW-TOKEN'): ConnectorInstance
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

test('guests are redirected from the review center to login', function () {
    $this->get('/review')
        ->assertRedirect('/login')
        ->assertHeader('Location', '/login');
});

test('authenticated users can view the review center', function () {
    $user = User::factory()->create();
    seedReviewConnector('jellyfin', 'healthy', [['jf-movies', 'Movies', 'movies', true]]);

    $this->actingAs($user)->get('/review')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Review/Index')
            ->has('connectors', 2)
            ->has('openTasks')
            ->has('resolvedTasks')
            ->has('summary.status')
            ->has('summary.open_task_count'));

    Http::assertNothingSent();
});

test('the review center shows an empty state when there are no open tasks', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/review')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Review/Index')
            ->has('openTasks', 0)
            ->where('summary.status', 'all_clear')
            ->where('summary.open_task_count', 0));
});

test('an open connector_sync task is listed with sanitized evidence and connector context', function () {
    $user = User::factory()->create();
    seedReviewConnector('jellyfin', 'healthy', [
        ['jf-movies', 'Movies', 'movies', false], // not selected -> attention
    ]);

    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');

    $this->actingAs($user)->get('/review')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Review/Index')
            ->has('openTasks', 1)
            ->where('openTasks.0.task_type', 'connector_sync')
            ->where('openTasks.0.connector.key', 'jellyfin')
            ->where('openTasks.0.connector.label', 'Jellyfin')
            ->where('openTasks.0.status', 'open')
            ->has('openTasks.0.issues.0.code')
            ->has('openTasks.0.issues.0.message')
            ->has('openTasks.0.issues.0.action')
            ->where('openTasks.0.can_manage', true)
            ->where('summary.open_task_count', 1)
            ->where('summary.status', 'attention_required'));
});

test('a healthy configured connector with a clean dry run reports an all-clear review center', function () {
    $user = User::factory()->create();
    seedReviewConnector('jellyfin', 'healthy', [
        ['jf-movies', 'Movies', 'movies', true],
    ]);

    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');

    $this->actingAs($user)->get('/review')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Review/Index')
            ->has('openTasks', 0)
            ->where('summary.status', 'all_clear'));
});

test('the review center never renders a stored secret', function () {
    $user = User::factory()->create();
    seedReviewConnector('jellyfin', 'healthy', [['jf-movies', 'Movies', 'movies', false]], token: 'REVIEW-DO-NOT-LEAK');

    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');

    $this->actingAs($user)->get('/review')
        ->assertOk()
        ->assertDontSee('REVIEW-DO-NOT-LEAK', false);
});

test('review center data comes from stored state with no network calls during render', function () {
    $user = User::factory()->create();
    seedReviewConnector('jellyfin', 'healthy', [['jf-movies', 'Movies', 'movies', true]]);

    $this->actingAs($user)->get('/review')->assertOk();

    Http::assertNothingSent();
});

test('the review center bounds the open task list to a fixed limit', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 150; $i++) {
        ReviewTask::query()->create([
            'task_type' => 'connector_sync',
            'subject_type' => 'connector_instance',
            'subject_id' => (string) Str::ulid(),
            'status' => 'open',
            'priority' => 'normal',
            'evidence' => ['connector' => 'jellyfin', 'issues' => []],
            'created_by' => 'test',
        ]);
    }

    expect(ReviewTask::query()->count())->toBe(150);

    $this->actingAs($user)->get('/review')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Review/Index')
            ->has('openTasks', 100));
});

test('the dashboard exposes a review summary from stored state only', function () {
    $user = User::factory()->create();
    seedReviewConnector('jellyfin', 'healthy', [['jf-movies', 'Movies', 'movies', false]]);
    $this->actingAs($user)->post('/connectors/jellyfin/sync/dry-run');

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('reviewSummary.open_task_count', 1)
            ->where('reviewSummary.status', 'attention_required'));

    Http::assertNothingSent();
});

test('the sidebar links to the review center and no forbidden routes', function () {
    $source = file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.tsx'));

    expect($source)->toContain('/review')
        ->toContain('Review Tasks')
        ->not->toContain('/admin')
        ->not->toContain('/profile')
        ->not->toContain('href="/libraries"')
        ->not->toContain('/downloads');
});

test('the review center page links only to existing routes', function () {
    $source = file_get_contents(resource_path('js/Pages/Review/Index.tsx'));

    expect($source)->toContain('/connectors/')
        ->toContain('/sync')
        ->toContain('/review/tasks/')
        ->not->toContain('/admin')
        ->not->toContain('/profile')
        ->not->toContain('href="/libraries"')
        ->not->toContain('/downloads');
});
