<?php

declare(strict_types=1);

use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Secrets\SecretStore;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    // Rendering the sync/dashboard pages must never touch the network.
    Http::preventStrayRequests();
});

/** A configured connector with one selected library — no HTTP involved. */
function seedSyncConnector(string $key = 'jellyfin', string $token = 'SYNC-TOKEN'): ConnectorInstance
{
    $ref = (string) Str::ulid();
    app(SecretStore::class)->put($ref, $token);

    $instance = ConnectorInstance::query()->create([
        'connector_key' => $key,
        'name' => ucfirst($key),
        'base_url' => 'http://'.$key.'.local:8096',
        'secrets_ref' => $ref,
        'health_status' => 'healthy',
        'libraries_discovered_at' => now(),
    ]);

    ConnectorLibrary::query()->create([
        'connector_instance_id' => $instance->id,
        'provider_key' => $key,
        'external_id' => $key.'-lib',
        'name' => 'Primary',
        'collection_type' => 'movies',
        'is_enabled' => true,
        'discovery_status' => 'present',
        'last_seen_at' => now(),
    ]);

    return $instance;
}

test('guests are redirected from the sync overview to login', function () {
    $this->get('/sync')
        ->assertRedirect('/login')
        ->assertHeader('Location', '/login');
});

test('authenticated users can view the sync foundation overview', function () {
    $user = User::factory()->create();
    seedSyncConnector();

    $this->actingAs($user)->get('/sync')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sync/Index')
            ->has('connectors', 2)
            ->where('connectors.0.key', 'jellyfin')
            ->where('connectors.0.sync.status', 'ready_for_dry_run')
            ->has('reviewTasks'));

    Http::assertNothingSent();
});

test('the sync overview never renders a stored secret', function () {
    $user = User::factory()->create();
    seedSyncConnector('jellyfin', 'DO-NOT-RENDER-ME');

    $this->actingAs($user)->get('/sync')
        ->assertOk()
        ->assertDontSee('DO-NOT-RENDER-ME', false);
});

test('the dashboard exposes an aggregated sync summary from stored state only', function () {
    $user = User::factory()->create();
    seedSyncConnector();

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('syncSummary.selected_libraries', 1)
            ->where('syncSummary.attention_count', 0)
            ->where('syncSummary.last_dry_run_at', null));

    Http::assertNothingSent();
});

test('the sync pages link only to existing routes', function () {
    $source = file_get_contents(resource_path('js/Pages/Sync/Index.tsx'));

    expect($source)->toContain('Dry run only')
        ->toContain('/sync/dry-run')
        ->not->toContain('/admin')
        ->not->toContain('/profile')
        ->not->toContain('href="/libraries"')
        ->not->toContain('/downloads');
});
