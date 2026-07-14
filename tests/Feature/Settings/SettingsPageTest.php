<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('guests are redirected from settings to login', function () {
    $this->get('/settings')
        ->assertRedirect('/login')
        ->assertHeader('Location', '/login');
});

test('authenticated users can view the visible settings overview without connector secrets', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Index')
            ->has('definitions', 5)
            ->where('definitions.0.key', 'api.token_expiry_days'));
});

test('settings page visibly structures read-only areas without broken links', function () {
    $source = file_get_contents(resource_path('js/Pages/Settings/Index.tsx'))
        .file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.tsx'));

    expect($source)->toContain('Application')
        ->toContain('Security')
        ->toContain('Media Paths')
        ->toContain('Connectors')
        ->toContain('Playback')
        ->toContain('Privacy')
        ->toContain('/connectors')
        ->not->toContain('/admin')
        ->not->toContain('/profile')
        ->not->toContain('/libraries')
        ->not->toContain('/downloads');
});
