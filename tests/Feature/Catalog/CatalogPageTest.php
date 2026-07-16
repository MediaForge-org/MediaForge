<?php

declare(strict_types=1);

use App\Connectors\Sdk\Catalog\CatalogItemQuery;
use App\Connectors\Sdk\Models\ConnectorCatalogItem;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Secrets\SecretStore;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
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
function captureItem(
    ConnectorInstance $instance,
    ConnectorLibrary $library,
    string $externalId,
    string $title,
    string $kind = 'movie',
    ?int $year = 1999,
    bool $present = true,
): ConnectorCatalogItem {
    return ConnectorCatalogItem::query()->create([
        'connector_instance_id' => $instance->id,
        'connector_library_id' => $library->id,
        'external_id' => $externalId,
        'media_kind' => $kind,
        'title' => $title,
        'year' => $year,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'is_present' => $present,
        'missing_since' => $present ? null : now(),
        'metadata' => [],
    ]);
}

/** A second discovered library on an existing connector instance. */
function seedCatalogLibrary(ConnectorInstance $instance, string $externalId, string $name): ConnectorLibrary
{
    return ConnectorLibrary::query()->create([
        'connector_instance_id' => $instance->id,
        'provider_key' => $instance->connector_key,
        'external_id' => $externalId,
        'name' => $name,
        'collection_type' => 'movies',
        'is_enabled' => true,
        'discovery_status' => 'present',
        'last_seen_at' => now(),
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
            ->has('items.data'));

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
            ->has('items.data', 0)
            ->where('items.meta.total', 0)
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
            ->has('items.data', 2)
            ->where('items.data.0.connector.key', 'jellyfin')
            ->where('items.data.0.library_name', 'Movies')
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

/* ---------------------------------------------------------------------------
 | V2 B — routing / auth for the connector + library catalog pages
 * ------------------------------------------------------------------------- */

test('guests are redirected from the connector and library catalog pages', function () {
    [, $library] = seedCatalogPageConnector();

    $this->get('/catalog/jellyfin')->assertRedirect('/login');
    $this->get("/catalog/jellyfin/libraries/{$library->id}")->assertRedirect('/login');
});

test('an unknown catalog connector returns 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/catalog/plex')->assertNotFound();
    $this->actingAs($user)->get('/catalog/plex/libraries/'.Str::ulid())->assertNotFound();
});

test('a catalog connector page 404s when the connector is not configured at all', function () {
    $user = User::factory()->create();

    // No jellyfin instance exists → there is no library to scope to.
    $this->actingAs($user)->get('/catalog/jellyfin/libraries/'.Str::ulid())->assertNotFound();
});

test('a library that does not belong to the connector returns 404', function () {
    $user = User::factory()->create();
    seedCatalogPageConnector('jellyfin');
    [, $absLibrary] = seedCatalogPageConnector('audiobookshelf', 'ABS-TOKEN');

    // The library exists, but under a different connector.
    $this->actingAs($user)->get("/catalog/jellyfin/libraries/{$absLibrary->id}")
        ->assertNotFound();
});

test('a malformed library id is rejected by the route constraint', function () {
    $user = User::factory()->create();
    seedCatalogPageConnector();

    $this->actingAs($user)->get('/catalog/jellyfin/libraries/not-a-ulid')->assertNotFound();
});

/* ---------------------------------------------------------------------------
 | V2 B — browsing: search / filter / sort / pagination
 * ------------------------------------------------------------------------- */

test('the catalog lists captured items with pagination metadata', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector();
    captureItem($instance, $library, 'jf-1', 'The Matrix');
    captureItem($instance, $library, 'jf-2', 'Arrival');

    $this->actingAs($user)->get('/catalog')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Index')
            ->has('items.data', 2)
            ->where('items.meta.total', 2)
            ->where('items.meta.current_page', 1)
            ->where('items.meta.last_page', 1)
            ->has('filters')
            ->has('kinds')
            ->has('libraryOptions', 1));

    Http::assertNothingSent();
});

test('the catalog filters items by a title search', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector();
    captureItem($instance, $library, 'jf-1', 'The Matrix');
    captureItem($instance, $library, 'jf-2', 'Arrival');

    // Case-insensitive partial match.
    $this->actingAs($user)->get('/catalog?q=matrix')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.title', 'The Matrix')
            ->where('filters.q', 'matrix'));
});

test('a title search wildcard is escaped instead of widening the match', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector();
    captureItem($instance, $library, 'jf-1', 'The Matrix');
    captureItem($instance, $library, 'jf-2', '100% Wolf');

    // A literal '%' must match only the title that really contains it.
    $this->actingAs($user)->get('/catalog?q=%25')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.title', '100% Wolf'));
});

test('the catalog filters items by connector', function () {
    $user = User::factory()->create();
    [$jellyfin, $jellyfinLibrary] = seedCatalogPageConnector('jellyfin');
    [$abs, $absLibrary] = seedCatalogPageConnector('audiobookshelf', 'ABS-TOKEN');
    captureItem($jellyfin, $jellyfinLibrary, 'jf-1', 'The Matrix');
    captureItem($abs, $absLibrary, 'abs-1', 'Dune', 'audiobook');

    $this->actingAs($user)->get('/catalog?connector=audiobookshelf')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.title', 'Dune')
            ->where('items.data.0.connector.key', 'audiobookshelf')
            ->where('filters.connector', 'audiobookshelf'));
});

test('filtering by a registered connector with no configured instance yields nothing, not everything', function () {
    $user = User::factory()->create();
    // Only Jellyfin is configured; Audiobookshelf is registered but has no instance.
    [$jellyfin, $jellyfinLibrary] = seedCatalogPageConnector('jellyfin');
    captureItem($jellyfin, $jellyfinLibrary, 'jf-1', 'The Matrix');

    $this->actingAs($user)->get('/catalog?connector=audiobookshelf')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // The filter must scope to the (empty) Audiobookshelf catalog — never
            // silently fall back to showing every other connector's items.
            ->has('items.data', 0)
            ->where('items.meta.total', 0)
            ->has('libraryOptions', 0)
            ->where('filters.connector', 'audiobookshelf'));

    // The same scoping applies to the connector page itself.
    $this->actingAs($user)->get('/catalog/audiobookshelf')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 0)
            ->has('libraries', 0));
});

test('an unknown connector filter is ignored instead of breaking the page', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector();
    captureItem($instance, $library, 'jf-1', 'The Matrix');

    $this->actingAs($user)->get('/catalog?connector=bogus')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('filters.connector', ''));
});

test('the catalog filters items by library', function () {
    $user = User::factory()->create();
    [$instance, $movies] = seedCatalogPageConnector();
    $shows = seedCatalogLibrary($instance, 'jf-shows', 'Shows');
    captureItem($instance, $movies, 'jf-1', 'The Matrix');
    captureItem($instance, $shows, 'jf-2', 'Severance', 'series');

    $this->actingAs($user)->get("/catalog?library={$shows->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.title', 'Severance')
            ->where('filters.library', $shows->id));
});

test('the catalog filters items by media kind', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector();
    captureItem($instance, $library, 'jf-1', 'The Matrix', 'movie');
    captureItem($instance, $library, 'jf-2', 'Severance', 'series');

    $this->actingAs($user)->get('/catalog?kind=series')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.title', 'Severance')
            ->where('filters.kind', 'series'));
});

test('an unknown media kind filter is ignored instead of breaking the page', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector();
    captureItem($instance, $library, 'jf-1', 'The Matrix');

    $this->actingAs($user)->get('/catalog?kind=hologram')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('filters.kind', ''));
});

test('the catalog filters items by present and missing status', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector();
    captureItem($instance, $library, 'jf-1', 'Kept');
    captureItem($instance, $library, 'jf-2', 'Vanished', 'movie', 1999, present: false);

    // Default: present only.
    $this->actingAs($user)->get('/catalog')
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.title', 'Kept')
            ->where('filters.status', 'present'));

    $this->actingAs($user)->get('/catalog?status=missing')
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.title', 'Vanished')
            ->where('items.data.0.is_present', false));

    $this->actingAs($user)->get('/catalog?status=all')
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 2));
});

test('the catalog sorts by an allowlisted column in both directions', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector();
    captureItem($instance, $library, 'jf-1', 'The Matrix', 'movie', 1999);
    captureItem($instance, $library, 'jf-2', 'Arrival', 'movie', 2016);

    // Default: title ascending.
    $this->actingAs($user)->get('/catalog')
        ->assertInertia(fn (Assert $page) => $page->where('items.data.0.title', 'Arrival'));

    $this->actingAs($user)->get('/catalog?sort=title&direction=desc')
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.data.0.title', 'The Matrix')
            ->where('filters.sort', 'title')
            ->where('filters.direction', 'desc'));

    $this->actingAs($user)->get('/catalog?sort=year&direction=desc')
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.data.0.year', 2016)
            ->where('filters.sort', 'year'));

    $this->actingAs($user)->get('/catalog?sort=media_kind&direction=asc')
        ->assertInertia(fn (Assert $page) => $page->where('filters.sort', 'media_kind'));

    $this->actingAs($user)->get('/catalog?sort=last_seen_at&direction=desc')
        ->assertInertia(fn (Assert $page) => $page->where('filters.sort', 'last_seen_at'));
});

test('a sort column outside the allowlist falls back safely instead of reaching SQL', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector();
    captureItem($instance, $library, 'jf-1', 'The Matrix');

    $this->actingAs($user)->get('/catalog?sort=items.id;DROP+TABLE+users&direction=sideways')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('filters.sort', 'title')
            ->where('filters.direction', 'asc'));

    // The allowlist protected the schema.
    expect(Schema::hasTable('users'))->toBeTrue();
});

test('the catalog paginates a long item list', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector();
    $perPage = CatalogItemQuery::PER_PAGE;

    for ($i = 0; $i < $perPage + 5; $i++) {
        captureItem($instance, $library, "jf-{$i}", sprintf('Item %03d', $i));
    }

    $this->actingAs($user)->get('/catalog')
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', $perPage)
            ->where('items.meta.total', $perPage + 5)
            ->where('items.meta.last_page', 2)
            ->where('items.meta.current_page', 1)
            ->where('items.meta.from', 1)
            ->where('items.meta.to', $perPage));

    $this->actingAs($user)->get('/catalog?page=2')
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 5)
            ->where('items.meta.current_page', 2)
            ->where('items.data.0.title', sprintf('Item %03d', $perPage)));
});

test('the catalog echoes the submitted search back so the filter form can seed its draft', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector();
    captureItem($instance, $library, 'jf-1', 'The Matrix');

    // The rendered props must carry the applied search verbatim — that value is
    // what seeds the filter form's local draft state on mount.
    $this->actingAs($user)->get('/catalog?q=Matrix&kind=movie&status=all&sort=year&direction=desc')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', 'Matrix')
            ->where('filters.kind', 'movie')
            ->where('filters.status', 'all')
            ->where('filters.sort', 'year')
            ->where('filters.direction', 'desc')
            ->has('items.data', 1));

    // The same holds on the connector and library pages.
    $this->actingAs($user)->get('/catalog/jellyfin?q=Matrix')
        ->assertInertia(fn (Assert $page) => $page->where('filters.q', 'Matrix')->has('items.data', 1));

    $this->actingAs($user)->get("/catalog/jellyfin/libraries/{$library->id}?q=Matrix")
        ->assertInertia(fn (Assert $page) => $page->where('filters.q', 'Matrix')->has('items.data', 1));
});

test('resetting the filters clears the search and returns the unfiltered list', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector();
    captureItem($instance, $library, 'jf-1', 'The Matrix');
    captureItem($instance, $library, 'jf-2', 'Arrival');

    $this->actingAs($user)->get('/catalog?q=Matrix')
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 1));

    // "Reset filters" navigates to the bare base path.
    $this->actingAs($user)->get('/catalog')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 2)
            ->where('filters.q', '')
            ->where('filters.kind', '')
            ->where('filters.connector', '')
            ->where('filters.status', 'present')
            ->where('filters.sort', 'title')
            ->where('filters.direction', 'asc'));
});

test('pagination keeps the active search and filter params', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector();
    $perPage = CatalogItemQuery::PER_PAGE;

    // Enough matches to need two pages, plus one item that must stay filtered out.
    for ($i = 0; $i < $perPage + 3; $i++) {
        captureItem($instance, $library, "match-{$i}", sprintf('Matrix %03d', $i));
    }
    captureItem($instance, $library, 'other-1', 'Arrival');

    $this->actingAs($user)->get('/catalog?q=Matrix')
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', $perPage)
            ->where('items.meta.total', $perPage + 3)
            ->where('items.meta.last_page', 2));

    // Page 2 of the SAME search — the filter must survive the page hop.
    $this->actingAs($user)->get('/catalog?q=Matrix&page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 3)
            ->where('items.meta.current_page', 2)
            ->where('items.meta.total', $perPage + 3)
            ->where('filters.q', 'Matrix'));
});

test('the catalog renders an empty item list without breaking', function () {
    $user = User::factory()->create();
    seedCatalogPageConnector();

    $this->actingAs($user)->get('/catalog?q=nothing-matches-this')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 0)
            ->where('items.meta.total', 0)
            ->where('items.meta.from', null));
});

/* ---------------------------------------------------------------------------
 | V2 B — connector + library catalog pages
 * ------------------------------------------------------------------------- */

test('the connector catalog page shows only that connector items', function () {
    $user = User::factory()->create();
    [$jellyfin, $jellyfinLibrary] = seedCatalogPageConnector('jellyfin');
    [$abs, $absLibrary] = seedCatalogPageConnector('audiobookshelf', 'ABS-TOKEN');
    captureItem($jellyfin, $jellyfinLibrary, 'jf-1', 'The Matrix');
    captureItem($abs, $absLibrary, 'abs-1', 'Dune', 'audiobook');

    $this->actingAs($user)->get('/catalog/jellyfin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Connector')
            ->where('connector.key', 'jellyfin')
            ->where('connector.catalog.present_item_count', 1)
            ->has('items.data', 1)
            ->where('items.data.0.title', 'The Matrix')
            ->has('libraries', 1)
            ->where('libraries.0.id', $jellyfinLibrary->id)
            ->has('latestRuns')
            ->has('filters'));

    $this->actingAs($user)->get('/catalog/audiobookshelf')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Connector')
            ->where('connector.key', 'audiobookshelf')
            ->has('items.data', 1)
            ->where('items.data.0.title', 'Dune'));

    Http::assertNothingSent();
});

test('the connector catalog page renders for a connector that is not configured', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/catalog/jellyfin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Connector')
            ->where('connector.configured', false)
            ->where('connector.catalog.status', 'not_ready')
            ->has('items.data', 0)
            ->has('libraries', 0));
});

test('the library catalog page shows only that library items', function () {
    $user = User::factory()->create();
    [$instance, $movies] = seedCatalogPageConnector();
    $shows = seedCatalogLibrary($instance, 'jf-shows', 'Shows');
    captureItem($instance, $movies, 'jf-1', 'The Matrix');
    captureItem($instance, $shows, 'jf-2', 'Severance', 'series');

    $this->actingAs($user)->get("/catalog/jellyfin/libraries/{$shows->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Library')
            ->where('library.id', $shows->id)
            ->where('library.name', 'Shows')
            ->where('library.is_enabled', true)
            ->where('scope.present_item_count', 1)
            ->has('items.data', 1)
            ->where('items.data.0.title', 'Severance')
            ->has('filters')
            ->has('kinds'));

    Http::assertNothingSent();
});

test('the library catalog page filters within the library scope only', function () {
    $user = User::factory()->create();
    [$instance, $movies] = seedCatalogPageConnector();
    $shows = seedCatalogLibrary($instance, 'jf-shows', 'Shows');
    captureItem($instance, $movies, 'jf-1', 'Matrix Movie');
    captureItem($instance, $shows, 'jf-2', 'Matrix Series', 'series');

    // The search matches both titles, but the library scope wins.
    $this->actingAs($user)->get("/catalog/jellyfin/libraries/{$shows->id}?q=matrix")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.title', 'Matrix Series'));
});

test('the catalog pages never render a stored secret', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedCatalogPageConnector('jellyfin', 'PAGES-DO-NOT-LEAK');
    captureItem($instance, $library, 'jf-1', 'The Matrix');

    foreach (['/catalog', '/catalog/jellyfin', "/catalog/jellyfin/libraries/{$library->id}"] as $path) {
        $this->actingAs($user)->get($path)
            ->assertOk()
            ->assertDontSee('PAGES-DO-NOT-LEAK', false);
    }

    Http::assertNothingSent();
});

test('the catalog pages link only to existing routes', function () {
    foreach (['Index', 'Connector', 'Library'] as $component) {
        $source = file_get_contents(resource_path("js/Pages/Catalog/{$component}.tsx"));

        expect($source)->not->toContain('/admin')
            ->not->toContain('/profile')
            ->not->toContain('/downloads')
            ->not->toContain('href="/libraries"')
            ->not->toContain("href='/libraries'");
    }

    // The read-only promise stays on every catalog page.
    foreach (['Index', 'Connector', 'Library'] as $component) {
        expect(file_get_contents(resource_path("js/Pages/Catalog/{$component}.tsx")))
            ->toContain('Read-only catalog. No media import.');
    }
});

test('the catalog search input is driven by local draft state, not straight from server props', function () {
    $source = file_get_contents(resource_path('js/Components/Catalog/CatalogFilterBar.tsx'));

    // The regression this pins: binding the input to the echoed prop let an
    // in-flight Inertia response overwrite freshly typed characters with the older
    // search value, so a character would vanish and the box snap back.
    expect($source)->not->toContain('value={filters.q}')
        ->not->toContain('value={filters.connector}')
        ->not->toContain('value={filters.kind}')
        ->not->toContain('value={filters.status}');

    // The input is bound to the local draft instead.
    expect($source)->toContain('value={draft.q}')
        ->toContain('useState<CatalogFilters>(filters)');

    // A guard must exist that stops a server echo clobbering unsubmitted typing.
    expect($source)->toContain('dirty');

    // Navigation stays an Inertia GET that preserves the component state.
    expect($source)->toContain('preserveState: true')
        ->toContain('preserveScroll: true')
        ->toContain('replace: true');
});

test('the catalog search applies explicitly instead of on an unstable timer', function () {
    $source = file_get_contents(resource_path('js/Components/Catalog/CatalogFilterBar.tsx'));

    // Explicit submit (Enter via the form, or the Search button) — no debounce
    // timer racing the response, and therefore no timer to leak on unmount.
    expect($source)->toContain('onSubmit={submitSearch}')
        ->toContain('type="submit"')
        ->not->toContain('setTimeout');
});

test('the connector detail page links to the real catalog library route', function () {
    $user = User::factory()->create();
    [, $library] = seedCatalogPageConnector();

    $source = file_get_contents(resource_path('js/Pages/Connectors/Show.tsx'));
    expect($source)->toContain('/catalog/${connector.key}/libraries/${library.id}');

    // That link target really resolves.
    $this->actingAs($user)->get("/catalog/jellyfin/libraries/{$library->id}")->assertOk();
});
