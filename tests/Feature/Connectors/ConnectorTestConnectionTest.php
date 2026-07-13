<?php

declare(strict_types=1);

use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
});

/** Configure a connector through the real save flow so the test exercises stored state. */
function configureConnector(User $user, string $key, string $baseUrl, string $secret): void
{
    test()->actingAs($user)->post("/connectors/{$key}", [
        'base_url' => $baseUrl,
        'secret' => $secret,
    ])->assertRedirect("/connectors/{$key}");
}

test('a successful Jellyfin test marks the connector healthy and sends the Emby token', function () {
    Http::fake(['*/System/Info' => Http::response(['ServerName' => 'Home Jellyfin', 'Version' => '10.9.11'], 200)]);

    $user = User::factory()->create();
    configureConnector($user, 'jellyfin', 'http://jellyfin.local:8096', 'JELLY-TOKEN');

    $this->actingAs($user)->post('/connectors/jellyfin/test');

    $instance = ConnectorInstance::query()->where('connector_key', 'jellyfin')->firstOrFail();

    expect($instance->health_status)->toBe('healthy')
        ->and($instance->health_detail)->toContain('Home Jellyfin')
        ->and($instance->last_checked_at)->not->toBeNull()
        ->and($instance->last_healthy_at)->not->toBeNull();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/System/Info')
        && $request->hasHeader('X-Emby-Token', 'JELLY-TOKEN'));
});

test('a successful Audiobookshelf test marks the connector healthy and sends the bearer token', function () {
    Http::fake(['*/api/me' => Http::response(['username' => 'librarian'], 200)]);

    $user = User::factory()->create();
    configureConnector($user, 'audiobookshelf', 'http://abs.local:13378', 'ABS-TOKEN');

    $this->actingAs($user)->post('/connectors/audiobookshelf/test');

    $instance = ConnectorInstance::query()->where('connector_key', 'audiobookshelf')->firstOrFail();

    expect($instance->health_status)->toBe('healthy')
        ->and($instance->health_detail)->toContain('librarian');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/me')
        && $request->hasHeader('Authorization', 'Bearer ABS-TOKEN'));
});

test('an authentication failure marks the connector unhealthy without leaking the token', function () {
    Http::fake(['*/System/Info' => Http::response([], 401)]);

    $user = User::factory()->create();
    configureConnector($user, 'jellyfin', 'http://jellyfin.local:8096', 'LEAKY-TOKEN');

    $this->actingAs($user)->post('/connectors/jellyfin/test');

    $instance = ConnectorInstance::query()->where('connector_key', 'jellyfin')->firstOrFail();

    expect($instance->health_status)->toBe('auth_failed')
        ->and($instance->health_detail)->toContain('401')
        ->and($instance->health_detail)->not->toContain('LEAKY-TOKEN');
});

test('a network failure marks the connector unreachable without leaking the token', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $user = User::factory()->create();
    configureConnector($user, 'jellyfin', 'http://jellyfin.local:8096', 'LEAKY-TOKEN');

    $this->actingAs($user)->post('/connectors/jellyfin/test');

    $instance = ConnectorInstance::query()->where('connector_key', 'jellyfin')->firstOrFail();

    expect($instance->health_status)->toBe('unreachable')
        ->and($instance->health_detail)->not->toContain('LEAKY-TOKEN')
        ->and($instance->last_checked_at)->not->toBeNull();
});

test('testing an unconfigured connector is blocked and makes no network call', function () {
    Http::fake();

    $this->actingAs(User::factory()->create())
        ->post('/connectors/jellyfin/test')
        ->assertSessionHas('error');

    Http::assertNothingSent();
});
