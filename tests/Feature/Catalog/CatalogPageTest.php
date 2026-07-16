<?php

declare(strict_types=1);

use App\Connectors\Sdk\Models\ConnectorCatalogItem;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Secrets\SecretStore;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    // Rendering the catalog pages must never touch the network.
    Http::preventStrayRequests();
});

/**
 * A configured connector with one discovered library — built directly, no HTTP.
 *
 * @return array{0: ConnectorInstance, 1: ConnectorLibrary}
 */
function seedCatalogPageConnector(string $key = 'jellyfin', string $token = 'PAGE-TOKEN'): array
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

    $library = ConnectorLibrary::query()->create([
        'connector_instance_id' => $instance->id,
        'provider_key' => $key,
        'external_id' => $key.'-lib',
        'name' => 'Movies',
        'collection_type' => 'movies',
        'is_enabled' => true,
        'discovery_status' => 'present',
        'last_seen_at' => now(),
    ]);

    return [$instance, $library];
}

/** Capture one external item without going through the network. */
function captureItem(ConnectorInstance $instance, ConnectorLibrary $library, string $externalId, string $title): ConnectorCatalogItem
{
    return ConnectorCatalogItem::query()->create([
        'connector_instance_id' => $instance->id,
        'connector_library_id' => $library->id,
        'external_id' => $externalId,
        'media_kind' => 'movie',
        'title' => $title,
        'year' => 1999,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'is_present' => true,
        'metadata' => [],
    ]);
}

test('guests are redirected from the catalog to login', function () {
    $this->get('/catalog')
        ->assertRedirect('/login')
        ->assertHeader('Location', '/login');
});

test('authenticated users can view the external catalog', function () {
    $user = User::factory()->create();
    seedCatalogPageConnector();

    $this->actingAs($user)->get('/catalog')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Index')
            ->has('connectors', 2)
            ->has('summary.external_items')
            ->has('summary.snapshot_runs')
            ->has('summary.libraries_captured')
            ->has('summary.attention_count')
            ->has('latestRuns')
            ->has('latestItems'));

    Http::assertNothingSent();
});

test('the catalog shows an empty state when no snapshots exist', function () {
    $user = User::factory()->create();
    seedCatalogPageConnector();

    $this->actingAs($user)->get('/catalog')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Index')
            ->where('summary.external_items', 0)
            ->where('summary.snapshot_runs', 0)
            ->where('summary.libraries_captured', 0)
            ->has('latestRuns', 0)
            ->has('latestItems', 0)
            ->where('connectors.0.catalog.status', 'ready_for_snapshot'));
});

test('the catalog exposes captured external items and counts', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector();
    captureItem($instance, $library, 'jf-1', 'The Matrix');
    captureItem($instance, $library, 'jf-2', 'Arrival');

    $this->actingAs($user)->get('/catalog')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Index')
            ->where('summary.external_items', 2)
            ->where('summary.libraries_captured', 1)
            ->has('latestItems', 2)
            ->where('latestItems.0.connector.key', 'jellyfin')
            ->where('latestItems.0.library_name', 'Movies')
            ->where('connectors.0.catalog.present_item_count', 2));

    Http::assertNothingSent();
});

test('the catalog never renders a stored secret', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector('jellyfin', 'CATALOG-DO-NOT-LEAK');
    captureItem($instance, $library, 'jf-1', 'The Matrix');

    $this->actingAs($user)->get('/catalog')
        ->assertOk()
        ->assertDontSee('CATALOG-DO-NOT-LEAK', false);
});

test('the dashboard exposes a catalog summary from stored state only', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector();
    captureItem($instance, $library, 'jf-1', 'The Matrix');

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('catalogSummary.external_items', 1)
            ->where('catalogSummary.attention_count', 0)
            ->where('catalogSummary.last_snapshot_at', null));

    Http::assertNothingSent();
});

test('the connectors overview exposes the catalog snapshot summary per connector', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector();
    captureItem($instance, $library, 'jf-1', 'The Matrix');

    $this->actingAs($user)->get('/connectors')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Connectors/Index')
            ->where('connectors.0.key', 'jellyfin')
            ->where('connectors.0.catalog.present_item_count', 1)
            ->where('connectors.0.catalog.status', 'ready_for_snapshot'));

    Http::assertNothingSent();
});

test('the connector detail page exposes the catalog section with per-library capture counts', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector('jellyfin', 'DETAIL-TOKEN');
    captureItem($instance, $library, 'jf-1', 'The Matrix');

    $this->actingAs($user)->get('/connectors/jellyfin')
        ->assertOk()
        ->assertDontSee('DETAIL-TOKEN', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Connectors/Show')
            ->where('connector.catalog.present_item_count', 1)
            ->where('connector.catalog.status', 'ready_for_snapshot')
            ->where("connector.catalog.libraries.{$library->id}.external_item_count", 1));

    Http::assertNothingSent();
});

test('the catalog page links only to existing routes', function () {
    $source = file_get_contents(resource_path('js/Pages/Catalog/Index.tsx'));

    expect($source)->toContain('Read-only')
        ->toContain('/connectors/')
        ->toContain('/review')
        ->not->toContain('/admin')
        ->not->toContain('/profile')
        ->not->toContain('href="/libraries"')
        ->not->toContain('/downloads');
});

test('the sidebar exposes the external catalog as a real navigation link', function () {
    $source = file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.tsx'));

    expect($source)->toContain("href: '/catalog'")
        ->toContain('External Catalog');
});
