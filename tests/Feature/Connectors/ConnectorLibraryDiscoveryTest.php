<?php

declare(strict_types=1);

use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Core\Audit\AuditLog;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
});

/** Configure a connector through the real save flow so discovery has a stored secret. */
function configureDiscoveryConnector(User $user, string $key, string $baseUrl, string $secret): void
{
    test()->actingAs($user)->post("/connectors/{$key}", [
        'base_url' => $baseUrl,
        'secret' => $secret,
    ])->assertRedirect("/connectors/{$key}");
}

function instanceId(string $key): string
{
    return ConnectorInstance::query()->where('connector_key', $key)->firstOrFail()->id;
}

test('guests are redirected from the discovery route to login', function () {
    $this->post('/connectors/jellyfin/libraries/discover')
        ->assertRedirect('/login');
});

test('discovery requires a configured Jellyfin connector', function () {
    Http::fake();

    $this->actingAs(User::factory()->create())
        ->post('/connectors/jellyfin/libraries/discover')
        ->assertSessionHas('error');

    Http::assertNothingSent();
});

test('Jellyfin discovery sends the Emby token and stores the discovered libraries', function () {
    Http::fake(['*/Library/MediaFolders' => Http::response([
        'Items' => [
            ['Id' => 'jf-movies', 'Name' => 'Movies', 'CollectionType' => 'movies', 'Path' => '/data/movies'],
            ['Id' => 'jf-shows', 'Name' => 'TV Shows', 'CollectionType' => 'tvshows'],
        ],
        'TotalRecordCount' => 2,
    ], 200)]);

    $user = User::factory()->create();
    configureDiscoveryConnector($user, 'jellyfin', 'http://jellyfin.local:8096', 'JELLY-TOKEN');

    $this->actingAs($user)->post('/connectors/jellyfin/libraries/discover');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/Library/MediaFolders')
        && $request->hasHeader('X-Emby-Token', 'JELLY-TOKEN'));

    $libraries = ConnectorLibrary::query()->where('connector_instance_id', instanceId('jellyfin'))->get();

    expect($libraries)->toHaveCount(2)
        ->and($libraries->firstWhere('external_id', 'jf-movies')?->name)->toBe('Movies')
        ->and($libraries->firstWhere('external_id', 'jf-movies')?->collection_type)->toBe('movies')
        ->and($libraries->firstWhere('external_id', 'jf-movies')?->path)->toBe('/data/movies')
        ->and($libraries->firstWhere('external_id', 'jf-movies')?->discovery_status)->toBe('present');

    $instance = ConnectorInstance::query()->where('connector_key', 'jellyfin')->firstOrFail();
    expect($instance->libraries_discovered_at)->not->toBeNull();
});

test('Audiobookshelf discovery sends the bearer token and stores the discovered libraries', function () {
    Http::fake(['*/api/libraries' => Http::response([
        'libraries' => [
            ['id' => 'abs-books', 'name' => 'Audiobooks', 'mediaType' => 'book', 'folders' => [['fullPath' => '/audiobooks']]],
            ['id' => 'abs-pods', 'name' => 'Podcasts', 'mediaType' => 'podcast', 'folders' => []],
        ],
    ], 200)]);

    $user = User::factory()->create();
    configureDiscoveryConnector($user, 'audiobookshelf', 'http://abs.local:13378', 'ABS-TOKEN');

    $this->actingAs($user)->post('/connectors/audiobookshelf/libraries/discover');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/libraries')
        && $request->hasHeader('Authorization', 'Bearer ABS-TOKEN'));

    $libraries = ConnectorLibrary::query()->where('connector_instance_id', instanceId('audiobookshelf'))->get();

    expect($libraries)->toHaveCount(2)
        ->and($libraries->firstWhere('external_id', 'abs-books')?->name)->toBe('Audiobooks')
        ->and($libraries->firstWhere('external_id', 'abs-books')?->collection_type)->toBe('book')
        ->and($libraries->firstWhere('external_id', 'abs-books')?->path)->toBe('/audiobooks');
});

test('repeated discovery updates existing libraries and flags vanished ones missing instead of duplicating', function () {
    $user = User::factory()->create();
    configureDiscoveryConnector($user, 'jellyfin', 'http://jellyfin.local:8096', 'JELLY-TOKEN');

    // A sequence so the second discovery returns a different payload than the first
    // (re-calling Http::fake for the same pattern would keep the first stub).
    Http::fakeSequence('*/Library/MediaFolders')
        ->push(['Items' => [
            ['Id' => 'jf-movies', 'Name' => 'Movies', 'CollectionType' => 'movies'],
            ['Id' => 'jf-shows', 'Name' => 'TV Shows', 'CollectionType' => 'tvshows'],
        ]], 200)
        ->push(['Items' => [
            ['Id' => 'jf-movies', 'Name' => 'Films', 'CollectionType' => 'movies'],
            ['Id' => 'jf-music', 'Name' => 'Music', 'CollectionType' => 'music'],
        ]], 200);

    $this->actingAs($user)->post('/connectors/jellyfin/libraries/discover');

    // Keep a selection to prove it survives re-discovery.
    ConnectorLibrary::query()->where('external_id', 'jf-movies')->firstOrFail()->update(['is_enabled' => true]);

    // Second discovery: Movies renamed, TV Shows gone, a new library appears.
    $this->actingAs($user)->post('/connectors/jellyfin/libraries/discover');

    $libraries = ConnectorLibrary::query()->where('connector_instance_id', instanceId('jellyfin'))->get();

    expect($libraries)->toHaveCount(3) // movies, shows(missing), music
        ->and($libraries->firstWhere('external_id', 'jf-movies')?->name)->toBe('Films')
        ->and($libraries->firstWhere('external_id', 'jf-movies')?->is_enabled)->toBeTrue() // selection preserved
        ->and($libraries->firstWhere('external_id', 'jf-shows')?->discovery_status)->toBe('missing')
        ->and($libraries->firstWhere('external_id', 'jf-music')?->discovery_status)->toBe('present');
});

test('a failed Jellyfin discovery stores a sanitized error, keeps existing libraries and never leaks the token', function () {
    $user = User::factory()->create();
    configureDiscoveryConnector($user, 'jellyfin', 'http://jellyfin.local:8096', 'LEAKY-TOKEN');

    // First a healthy response, then a 401 on the next call (sequence, not re-fake).
    Http::fakeSequence('*/Library/MediaFolders')
        ->push(['Items' => [['Id' => 'jf-movies', 'Name' => 'Movies']]], 200)
        ->push([], 401);

    $this->actingAs($user)->post('/connectors/jellyfin/libraries/discover');
    $this->actingAs($user)->post('/connectors/jellyfin/libraries/discover')->assertSessionHas('error');

    $instance = ConnectorInstance::query()->where('connector_key', 'jellyfin')->firstOrFail();

    expect($instance->last_discovery_error)->toContain('401')
        ->and($instance->last_discovery_error)->not->toContain('LEAKY-TOKEN')
        ->and(ConnectorLibrary::query()->where('connector_instance_id', $instance->id)->count())->toBe(1); // not wiped
});

test('a network failure during discovery is handled without leaking the token', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $user = User::factory()->create();
    configureDiscoveryConnector($user, 'audiobookshelf', 'http://abs.local:13378', 'LEAKY-TOKEN');

    $this->actingAs($user)->post('/connectors/audiobookshelf/libraries/discover')->assertSessionHas('error');

    $instance = ConnectorInstance::query()->where('connector_key', 'audiobookshelf')->firstOrFail();

    expect($instance->last_discovery_error)->not->toContain('LEAKY-TOKEN')
        ->and($instance->last_discovery_error)->not->toBeNull();
});

test('discovery records a sanitized audit entry that never contains the raw token', function () {
    Http::fake(['*/Library/MediaFolders' => Http::response(['Items' => [['Id' => 'jf-1', 'Name' => 'Movies']]], 200)]);

    $user = User::factory()->create();
    configureDiscoveryConnector($user, 'jellyfin', 'http://jellyfin.local:8096', 'AUDIT-TOKEN');

    $this->actingAs($user)->post('/connectors/jellyfin/libraries/discover');

    $entry = AuditLog::query()->where('action', 'connector.libraries_discovered')->sole();
    expect($entry->changes)->toHaveKey('count')
        ->and($entry->context['connector'])->toBe('jellyfin');

    $serialized = AuditLog::query()->get()
        ->map(fn (AuditLog $log): string => json_encode($log->changes).json_encode($log->context))
        ->implode('');
    expect($serialized)->not->toContain('AUDIT-TOKEN');
});
