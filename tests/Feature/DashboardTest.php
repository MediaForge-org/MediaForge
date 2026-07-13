<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from dashboard to login', function () {
    $this->get('/dashboard')
        ->assertRedirect('/login')
        ->assertHeader('Location', '/login');
});

test('authenticated users can view the dashboard shell with their identity and V1 status', function () {
    $user = User::factory()->create([
        'name' => 'Dashboard User',
        'email' => 'dashboard@example.test',
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.user.name', 'Dashboard User')
            ->where('auth.user.email', 'dashboard@example.test')
            ->where('status', 'V1 foundation'));
});

test('dashboard shell visibly provides valid navigation without unavailable links', function () {
    $source = file_get_contents(resource_path('js/Pages/Dashboard.tsx'))
        .file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.tsx'));

    expect($source)->toContain('MediaForge')
        ->toContain('Dashboard')
        ->toContain('Settings')
        ->toContain('Library Overview')
        ->toContain('Connectors Overview')
        ->toContain('Review Tasks')
        ->not->toContain('href="/connectors"')
        ->not->toContain('/admin')
        ->not->toContain('/profile')
        ->not->toContain('/libraries')
        ->not->toContain('/downloads');
});

test('logout remains post only', function () {
    $route = Route::getRoutes()->getByName('logout');

    expect($route)->not->toBeNull()
        ->and($route->methods())->toBe(['POST']);
});

test('welcome page offers login and registration to guests', function () {
    $this->withoutVite();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('auth.user', null));
});

test('welcome page offers the dashboard to authenticated users', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->has('auth.user'));
});
