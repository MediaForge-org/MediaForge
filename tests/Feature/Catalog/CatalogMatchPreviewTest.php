<?php

declare(strict_types=1);

use App\Connectors\Sdk\Models\ConnectorCatalogItemNormalization;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Core\Media\MediaEdition;
use App\Core\Media\MediaFile;
use App\Core\Media\MediaItem;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    // Rendering the match preview must never touch the network.
    Http::preventStrayRequests();
});

/* ---------------------------------------------------------------------------
 | Routing / auth
 * ------------------------------------------------------------------------- */

test('guests are redirected from the match preview to login', function () {
    $this->get('/catalog/matches')
        ->assertRedirect('/login')
        ->assertHeader('Location', '/login');
});

test('guests cannot rebuild normalization', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');

    $this->post('/catalog/normalize')->assertRedirect('/login');
    $this->post("/catalog/jellyfin/libraries/{$library->id}/normalize")->assertRedirect('/login');

    expect(ConnectorCatalogItemNormalization::query()->count())->toBe(0);
});

test('the normalization routes are POST only and there is no GET normalization route', function () {
    foreach (['catalog.normalize', 'catalog.library.normalize'] as $name) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull()
            ->and($route->methods())->toContain('POST')
            ->and($route->methods())->not->toContain('GET');
    }

    // No GET route anywhere may mention normalize.
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (in_array('GET', $route->methods(), true)) {
            expect(str_contains($route->uri(), 'normalize'))->toBeFalse(
                "GET route {$route->uri()} looks state-changing."
            );
        }
    }
});

test('the match preview route is a real GET page that renders for an authenticated user', function () {
    $user = User::factory()->create();
    seedNormalizationConnector();

    $this->actingAs($user)->get('/catalog/matches')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Matches')
            ->has('preview.duplicate_suspects')
            ->has('preview.episode_groups')
            ->has('preview.audiobook_groups')
            ->has('preview.weak_metadata')
            ->has('normalization')
            ->where('preview.note', 'Matching preview only. No imports or merges in V2 C.'));

    Http::assertNothingSent();
});

test('the matches route is not shadowed by the connector route', function () {
    $user = User::factory()->create();
    seedNormalizationConnector();

    // "matches" is not a registry key; it must resolve to the preview page,
    // never to /catalog/{connector}.
    $this->actingAs($user)->get('/catalog/matches')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Catalog/Matches'));
});

test('rebuilding normalization for an unknown connector or foreign library is rejected', function () {
    $user = User::factory()->create();
    seedNormalizationConnector('jellyfin');
    [, $absLibrary] = seedNormalizationConnector('audiobookshelf', 'ABS-NORM-TOKEN');

    // Unknown connector key → route constraint.
    $this->actingAs($user)->post('/catalog/plex/libraries/'.Str::ulid().'/normalize')->assertNotFound();
    // Real library, wrong connector.
    $this->actingAs($user)->post("/catalog/jellyfin/libraries/{$absLibrary->id}/normalize")->assertNotFound();
    // Well-formed but non-existent library.
    $this->actingAs($user)->post('/catalog/jellyfin/libraries/'.Str::ulid().'/normalize')->assertNotFound();
});

/* ---------------------------------------------------------------------------
 | Rebuild actions
 * ------------------------------------------------------------------------- */

test('an authenticated user can rebuild normalization for every connector', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    seedNormalizationItem($instance, $library, 'jf-2', 'Arrival');

    $this->actingAs($user)->from('/catalog')->post('/catalog/normalize')
        ->assertRedirect('/catalog')
        ->assertSessionHas('success');

    expect(ConnectorCatalogItemNormalization::query()->count())->toBe(2);
    expect(session('success'))->toContain('No media was imported');
});

test('an authenticated user can rebuild normalization for one library only', function () {
    $user = User::factory()->create();
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

    $this->actingAs($user)->from("/catalog/jellyfin/libraries/{$shows->id}")
        ->post("/catalog/jellyfin/libraries/{$shows->id}/normalize")
        ->assertSessionHas('success');

    expect(ConnectorCatalogItemNormalization::query()->count())->toBe(1)
        ->and(ConnectorCatalogItemNormalization::query()->sole()->normalized_title)->toBe('Severance');
});

test('rebuilding normalization creates no media items, editions or files', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');

    $this->actingAs($user)->post('/catalog/normalize');

    expect(MediaItem::query()->count())->toBe(0)
        ->and(MediaEdition::query()->count())->toBe(0)
        ->and(MediaFile::query()->count())->toBe(0);

    Http::assertNothingSent();
});

/* ---------------------------------------------------------------------------
 | Match preview content
 * ------------------------------------------------------------------------- */

test('items sharing a normalized identity surface as duplicate suspects', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    // Same film, captured twice with cosmetically different titles.
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    seedNormalizationItem($instance, $library, 'jf-2', '  The   Matrix  ');
    seedNormalizationItem($instance, $library, 'jf-3', 'Arrival', 'movie', ['year' => 2016]);

    normalizeConnector($instance);

    $this->actingAs($user)->get('/catalog/matches')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('preview.duplicate_suspects', 1)
            ->where('preview.duplicate_suspects.0.title', 'The Matrix')
            ->where('preview.duplicate_suspects.0.release_year', 1999)
            ->where('preview.duplicate_suspects.0.kind', 'movie')
            ->where('preview.duplicate_suspects.0.item_count', 2)
            ->where('preview.duplicate_suspects.0.score', 85)
            ->has('preview.duplicate_suspects.0.items', 2));
});

test('two year-less items with the same title still pair up as a weaker duplicate suspect', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'Untitled Doc', 'movie', ['year' => null]);
    seedNormalizationItem($instance, $library, 'jf-2', 'Untitled Doc', 'movie', ['year' => null]);

    normalizeConnector($instance);

    $this->actingAs($user)->get('/catalog/matches')
        ->assertInertia(fn (Assert $page) => $page
            ->has('preview.duplicate_suspects', 1)
            ->where('preview.duplicate_suspects.0.release_year', null)
            // No year to corroborate → lower score.
            ->where('preview.duplicate_suspects.0.score', 60));
});

test('episodes of the same series and season are grouped', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'series-1', 'Severance', 'series', ['runtime_seconds' => null]);

    foreach ([1, 2, 3] as $episode) {
        seedNormalizationItem($instance, $library, "ep-{$episode}", "Episode {$episode}", 'episode', [
            'external_parent_id' => 'series-1',
            'parent_index_number' => 1,
            'index_number' => $episode,
        ]);
    }

    normalizeConnector($instance);

    $this->actingAs($user)->get('/catalog/matches')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('preview.episode_groups', 1)
            ->where('preview.episode_groups.0.parent_title', 'Severance')
            ->where('preview.episode_groups.0.season_number', 1)
            ->where('preview.episode_groups.0.item_count', 3)
            ->where('preview.episode_groups.0.missing_episode_count', 0)
            ->where('preview.episode_groups.0.score', 90)
            ->has('preview.episode_groups.0.items', 3));
});

test('an episode group reports how many episodes lack a number', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'series-1', 'Severance', 'series', ['runtime_seconds' => null]);
    seedNormalizationItem($instance, $library, 'ep-1', 'Numbered', 'episode', [
        'external_parent_id' => 'series-1', 'parent_index_number' => 1, 'index_number' => 1,
    ]);
    seedNormalizationItem($instance, $library, 'ep-2', 'Unnumbered', 'episode', [
        'external_parent_id' => 'series-1', 'parent_index_number' => 1, 'index_number' => null,
    ]);

    normalizeConnector($instance);

    $this->actingAs($user)->get('/catalog/matches')
        ->assertInertia(fn (Assert $page) => $page
            ->where('preview.episode_groups.0.missing_episode_count', 1)
            ->where('preview.episode_groups.0.score', 65));
});

test('audiobooks sharing a normalized title are grouped', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector('audiobookshelf', 'ABS-MATCH-TOKEN');
    seedNormalizationItem($instance, $library, 'abs-1', 'Dune', 'audiobook', ['year' => 1965]);
    seedNormalizationItem($instance, $library, 'abs-2', 'Dune', 'book', ['year' => 1965]);

    normalizeConnector($instance, 'audiobookshelf');

    $this->actingAs($user)->get('/catalog/matches')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('preview.audiobook_groups', 1)
            ->where('preview.audiobook_groups.0.title', 'Dune')
            ->where('preview.audiobook_groups.0.item_count', 2)
            ->has('preview.audiobook_groups.0.items', 2));
});

test('weak metadata items appear on the match preview', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'Just A Title', 'movie', ['year' => null, 'runtime_seconds' => null]);
    seedNormalizationItem($instance, $library, 'jf-2', 'Good One', 'movie', ['year' => 1999, 'runtime_seconds' => 7200]);

    normalizeConnector($instance);

    $this->actingAs($user)->get('/catalog/matches')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('preview.weak_metadata', 1)
            ->where('preview.weak_metadata.0.title', 'Just A Title')
            ->where('preview.weak_metadata.0.status', 'needs_review'));
});

test('the match preview can be scoped to a connector and a library', function () {
    $user = User::factory()->create();
    [$jellyfin, $jellyfinLibrary] = seedNormalizationConnector('jellyfin');
    [$abs, $absLibrary] = seedNormalizationConnector('audiobookshelf', 'ABS-SCOPE-TOKEN');

    seedNormalizationItem($jellyfin, $jellyfinLibrary, 'jf-1', 'Dup', 'movie');
    seedNormalizationItem($jellyfin, $jellyfinLibrary, 'jf-2', 'Dup', 'movie');
    seedNormalizationItem($abs, $absLibrary, 'abs-1', 'Book Dup', 'audiobook');
    seedNormalizationItem($abs, $absLibrary, 'abs-2', 'Book Dup', 'audiobook');

    normalizeConnector($jellyfin);
    normalizeConnector($abs, 'audiobookshelf');

    // Unscoped → both connectors' duplicates.
    $this->actingAs($user)->get('/catalog/matches')
        ->assertInertia(fn (Assert $page) => $page->has('preview.duplicate_suspects', 2));

    // Scoped to Jellyfin → only its duplicate.
    $this->actingAs($user)->get('/catalog/matches?connector=jellyfin')
        ->assertInertia(fn (Assert $page) => $page
            ->has('preview.duplicate_suspects', 1)
            ->where('preview.duplicate_suspects.0.title', 'Dup')
            ->where('filters.connector', 'jellyfin'));

    // Scoped to a library.
    $this->actingAs($user)->get("/catalog/matches?connector=audiobookshelf&library={$absLibrary->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('preview.duplicate_suspects', 1)
            ->where('preview.duplicate_suspects.0.title', 'Book Dup'));
});

test('the match preview is empty when every item is unambiguous', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix', 'movie', ['year' => 1999]);
    seedNormalizationItem($instance, $library, 'jf-2', 'Arrival', 'movie', ['year' => 2016]);

    normalizeConnector($instance);

    $this->actingAs($user)->get('/catalog/matches')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('preview.duplicate_suspects', 0)
            ->has('preview.episode_groups', 0)
            ->has('preview.audiobook_groups', 0)
            ->has('preview.weak_metadata', 0));
});

test('the match preview never renders a stored secret', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector('jellyfin', 'MATCHES-DO-NOT-LEAK');
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    seedNormalizationItem($instance, $library, 'jf-2', 'The Matrix');

    normalizeConnector($instance);

    $this->actingAs($user)->get('/catalog/matches')
        ->assertOk()
        ->assertDontSee('MATCHES-DO-NOT-LEAK', false);
});

/* ---------------------------------------------------------------------------
 | The preview accepts nothing — V2 C boundary
 * ------------------------------------------------------------------------- */

test('the match preview offers no accept, import or merge action', function () {
    $source = file_get_contents(resource_path('js/Pages/Catalog/Matches.tsx'));

    // The real invariant: the preview issues NO write of any kind. Accepting,
    // importing or merging would all need one, so their absence is what matters —
    // not the wording. (Scanning for the words "accept"/"merge" would only flag the
    // page's own disclaimer, which is exactly the text we want to keep.)
    foreach (['router.post', 'router.put', 'router.patch', 'router.delete', 'useForm', '<form'] as $write) {
        expect($source)->not->toContain($write);
    }

    // No actionable label that would commit a match.
    foreach (['Import now', 'Merge now', 'Accept match', 'Accept suggestion', 'Import selected', 'Merge selected'] as $label) {
        expect($source)->not->toContain($label);
    }

    // And it states the boundary out loud.
    expect($source)->toContain('Matching preview only');

    // No route exists anywhere that could accept/import/merge a match.
    foreach (Route::getRoutes()->getRoutes() as $route) {
        foreach (['import', 'merge', 'accept'] as $needle) {
            expect(str_contains($route->uri(), $needle))->toBeFalse(
                "Route {$route->uri()} suggests an import/merge action, which V2 C must not have."
            );
        }
    }
});

test('the catalog pages reference no forbidden routes', function () {
    foreach (['Index', 'Connector', 'Library', 'Matches'] as $component) {
        $source = file_get_contents(resource_path("js/Pages/Catalog/{$component}.tsx"));

        expect($source)->not->toContain('/admin')
            ->not->toContain('/profile')
            ->not->toContain('/downloads')
            ->not->toContain('href="/libraries"')
            ->not->toContain("href='/libraries'");
    }
});
