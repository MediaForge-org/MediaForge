<?php

declare(strict_types=1);

use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Core\Audit\AuditLog;
use App\Core\Media\MediaItem;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

/** Configure a Jellyfin connector and discover one library, returning that library. */
function seedDiscoveredLibrary(User $user): ConnectorLibrary
{
    test()->actingAs($user)->post('/connectors/jellyfin', [
        'base_url' => 'http://jellyfin.local:8096',
        'secret' => 'SELECT-TOKEN',
    ])->assertRedirect('/connectors/jellyfin');

    Http::fake(['*/Library/MediaFolders' => Http::response([
        'Items' => [['Id' => 'jf-movies', 'Name' => 'Movies', 'CollectionType' => 'movies']],
    ], 200)]);

    test()->actingAs($user)->post('/connectors/jellyfin/libraries/discover');

    return ConnectorLibrary::query()->where('external_id', 'jf-movies')->firstOrFail();
}

test('guests are redirected from the library selection route to login', function () {
    $this->post('/connectors/jellyfin/libraries/01HZZZZZZZZZZZZZZZZZZZZZZZ/selection')
        ->assertRedirect('/login');
});

test('a user can enable a discovered library for later sync and disable it again', function () {
    $user = User::factory()->create();
    $library = seedDiscoveredLibrary($user);

    expect($library->is_enabled)->toBeFalse();

    $this->actingAs($user)
        ->post("/connectors/jellyfin/libraries/{$library->id}/selection", ['enabled' => true])
        ->assertSessionHasNoErrors();

    expect($library->fresh()?->is_enabled)->toBeTrue();

    $this->actingAs($user)
        ->post("/connectors/jellyfin/libraries/{$library->id}/selection", ['enabled' => false]);

    expect($library->fresh()?->is_enabled)->toBeFalse();
});

test('changing a library selection does not create any media items', function () {
    $user = User::factory()->create();
    $library = seedDiscoveredLibrary($user);

    $this->actingAs($user)
        ->post("/connectors/jellyfin/libraries/{$library->id}/selection", ['enabled' => true]);

    expect(MediaItem::query()->count())->toBe(0)
        ->and(ConnectorLibrary::query()->count())->toBe(1); // no rows conjured by selecting
});

test('a selection change records a sanitized audit entry without the raw token', function () {
    $user = User::factory()->create();
    $library = seedDiscoveredLibrary($user);

    $this->actingAs($user)
        ->post("/connectors/jellyfin/libraries/{$library->id}/selection", ['enabled' => true]);

    $entry = AuditLog::query()->where('action', 'connector.library_selection_changed')->sole();
    expect($entry->changes)->toHaveKey('is_enabled')
        ->and($entry->context['library'])->toBe('jf-movies');

    $serialized = AuditLog::query()->get()
        ->map(fn (AuditLog $log): string => json_encode($log->changes).json_encode($log->context))
        ->implode('');
    expect($serialized)->not->toContain('SELECT-TOKEN');
});

test('the connectors overview exposes the discovered library count', function () {
    $user = User::factory()->create();
    seedDiscoveredLibrary($user);

    $this->actingAs($user)->get('/connectors')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Connectors/Index')
            ->where('connectors.0.key', 'jellyfin')
            ->where('connectors.0.library_count', 1));
});

test('the Jellyfin detail page exposes the discovered libraries without the secret', function () {
    $user = User::factory()->create();
    seedDiscoveredLibrary($user);

    $this->actingAs($user)->get('/connectors/jellyfin')
        ->assertOk()
        ->assertDontSee('SELECT-TOKEN', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Connectors/Show')
            ->where('connector.secret_configured', true)
            ->missing('connector.secret')
            ->has('connector.libraries', 1)
            ->where('connector.libraries.0.external_id', 'jf-movies')
            ->where('connector.libraries.0.name', 'Movies'));
});

test('the Audiobookshelf detail page exposes an empty libraries array before discovery', function () {
    $this->actingAs(User::factory()->create())->get('/connectors/audiobookshelf')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Connectors/Show')
            ->where('connector.key', 'audiobookshelf')
            ->has('connector.libraries', 0));
});

test('the connector pages link only to existing routes', function () {
    $source = file_get_contents(resource_path('js/Pages/Connectors/Show.tsx'))
        .file_get_contents(resource_path('js/Pages/Connectors/Index.tsx'));

    expect($source)->toContain('Discover libraries')
        ->toContain('No media sync in V1 D')
        ->not->toContain('/admin')
        ->not->toContain('/profile')
        ->not->toContain('href="/libraries"')
        ->not->toContain('/downloads');
});
