<?php

declare(strict_types=1);

use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Secrets\SecretStore;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('guests are redirected from connectors to login', function () {
    $this->get('/connectors')
        ->assertRedirect('/login')
        ->assertHeader('Location', '/login');
});

test('authenticated users can view the connectors overview with both providers', function () {
    $this->actingAs(User::factory()->create())
        ->get('/connectors')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Connectors/Index')
            ->has('connectors', 2)
            ->where('connectors.0.key', 'jellyfin')
            ->where('connectors.0.status', 'not_configured')
            ->where('connectors.1.key', 'audiobookshelf'));
});

test('authenticated users can view each connector configuration page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/connectors/jellyfin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Connectors/Show')
            ->where('connector.key', 'jellyfin')
            ->where('connector.label', 'Jellyfin'));

    $this->actingAs($user)->get('/connectors/audiobookshelf')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Connectors/Show')
            ->where('connector.key', 'audiobookshelf'));
});

test('an unknown connector key is not routable', function () {
    $this->actingAs(User::factory()->create())
        ->get('/connectors/plex')
        ->assertNotFound();
});

test('saving a connector stores the base URL and marks the secret configured', function () {
    $this->actingAs(User::factory()->create())
        ->post('/connectors/jellyfin', [
            'base_url' => 'http://jellyfin.local:8096/',
            'secret' => 'JELLYFIN-TOKEN',
        ])
        ->assertRedirect('/connectors/jellyfin');

    $instance = ConnectorInstance::query()->where('connector_key', 'jellyfin')->firstOrFail();

    expect($instance->base_url)->toBe('http://jellyfin.local:8096') // trailing slash trimmed
        ->and(app(SecretStore::class)->has($instance->secrets_ref))->toBeTrue();
});

test('saving the audiobookshelf connector stores the base URL and marks the secret configured', function () {
    $this->actingAs(User::factory()->create())
        ->post('/connectors/audiobookshelf', [
            'base_url' => 'http://abs.local:13378',
            'secret' => 'ABS-TOKEN',
        ])
        ->assertRedirect('/connectors/audiobookshelf');

    $instance = ConnectorInstance::query()->where('connector_key', 'audiobookshelf')->firstOrFail();

    expect($instance->base_url)->toBe('http://abs.local:13378')
        ->and(app(SecretStore::class)->has($instance->secrets_ref))->toBeTrue();
});

test('the stored secret is never rendered back to the frontend', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/connectors/jellyfin', [
        'base_url' => 'http://jellyfin.local:8096',
        'secret' => 'SUPER-SECRET-TOKEN',
    ]);

    $this->actingAs($user)->get('/connectors/jellyfin')
        ->assertOk()
        ->assertDontSee('SUPER-SECRET-TOKEN', false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('connector.secret_configured', true)
            ->missing('connector.secret'));
});

test('an empty secret on update keeps the existing secret', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/connectors/jellyfin', [
        'base_url' => 'http://jellyfin.local:8096',
        'secret' => 'ORIGINAL-TOKEN',
    ]);

    $this->actingAs($user)->post('/connectors/jellyfin', [
        'base_url' => 'http://jellyfin.local:9000',
        'secret' => '',
    ])->assertRedirect('/connectors/jellyfin');

    $instance = ConnectorInstance::query()->where('connector_key', 'jellyfin')->firstOrFail();
    $secrets = app(SecretStore::class);

    expect($instance->base_url)->toBe('http://jellyfin.local:9000')
        ->and($secrets->get($instance->secrets_ref))->toBe('ORIGINAL-TOKEN');
});

test('clearing the secret removes the stored credential', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/connectors/jellyfin', [
        'base_url' => 'http://jellyfin.local:8096',
        'secret' => 'ORIGINAL-TOKEN',
    ]);

    $this->actingAs($user)->post('/connectors/jellyfin', [
        'base_url' => 'http://jellyfin.local:8096',
        'clear_secret' => true,
    ])->assertRedirect('/connectors/jellyfin');

    $instance = ConnectorInstance::query()->where('connector_key', 'jellyfin')->firstOrFail();

    expect(app(SecretStore::class)->has($instance->secrets_ref))->toBeFalse();
});

test('an invalid base URL is rejected', function () {
    $user = User::factory()->create();

    foreach (['not-a-url', 'ftp://jellyfin.local', 'javascript:alert(1)'] as $invalid) {
        $this->actingAs($user)->post('/connectors/jellyfin', [
            'base_url' => $invalid,
            'secret' => 'TOKEN',
        ])->assertSessionHasErrors('base_url');
    }

    expect(ConnectorInstance::query()->where('connector_key', 'jellyfin')->exists())->toBeFalse();
});
