<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

/** Every primary authenticated page must render (no empty/broken pages). */
$authenticatedPages = [
    '/dashboard' => 'Dashboard',
    '/connectors' => 'Connectors/Index',
    '/connectors/jellyfin' => 'Connectors/Show',
    '/connectors/audiobookshelf' => 'Connectors/Show',
    '/sync' => 'Sync/Index',
    '/review' => 'Review/Index',
    '/settings' => 'Settings/Index',
];

test('every primary authenticated page renders for a logged-in user', function () use ($authenticatedPages) {
    $user = User::factory()->create();

    foreach ($authenticatedPages as $path => $component) {
        $this->actingAs($user)->get($path)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }
});

test('every primary authenticated page redirects guests to login', function () use ($authenticatedPages) {
    foreach (array_keys($authenticatedPages) as $path) {
        $this->get($path)
            ->assertRedirect('/login')
            ->assertHeader('Location', '/login');
    }
});

test('the public entry points are reachable for guests', function () {
    $this->withoutVite();

    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Welcome'));
    $this->get('/login')->assertOk();
    $this->get('/register')->assertOk();
});

test('logout is POST-only and there is no GET logout route', function () {
    $logoutRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_contains($route->uri(), 'logout'));

    expect($logoutRoutes)->not->toBeEmpty();

    $logoutRoutes->each(function ($route) {
        expect($route->methods())->toContain('POST')
            ->and($route->methods())->not->toContain('GET');
    });
});

test('no registered GET route performs a state-changing action', function () {
    // State-changing verbs (update/store/destroy/test/discover/dry-run/dismiss/reopen)
    // must never be reachable via GET/HEAD.
    $stateChanging = ['store', 'update', 'destroy', 'delete', 'dismiss', 'reopen', 'dry-run', 'discover', 'selection', 'test'];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $isGet = in_array('GET', $route->methods(), true);

        if (!$isGet) {
            continue;
        }

        foreach ($stateChanging as $needle) {
            expect(str_contains($route->uri(), $needle))->toBeFalse(
                "GET route {$route->uri()} looks state-changing (matched '{$needle}')."
            );
        }
    }
});

test('the sidebar navigation links only to registered GET routes', function () {
    $layout = file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.tsx'));

    // The real nav items delivered in V1.
    $navHrefs = ['/dashboard', '/connectors', '/sync', '/review', '/settings'];

    $getUris = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        ->map(fn ($route) => '/'.ltrim($route->uri(), '/'))
        ->all();

    foreach ($navHrefs as $href) {
        expect($layout)->toContain("href: '{$href}'");
        expect(in_array($href, $getUris, true))->toBeTrue("Nav href {$href} has no registered GET route.");
    }
});

test('no frontend source references a forbidden or non-existent route', function () {
    // Walk every .tsx source under resources/js recursively.
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js')));
    $sources = '';
    foreach ($it as $file) {
        if ($file->isFile() && str_ends_with((string) $file, '.tsx')) {
            $sources .= file_get_contents((string) $file);
        }
    }

    expect($sources)->not->toContain('/admin')
        ->not->toContain('/profile')
        ->not->toContain('/downloads')
        ->not->toContain('href="/libraries"')
        ->not->toContain("href='/libraries'")
        ->not->toContain('href="/logout"'); // logout must be a POST form, never a link
});
