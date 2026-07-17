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
    '/catalog' => 'Catalog/Index',
    '/catalog/matches' => 'Catalog/Matches',
    '/catalog/jellyfin' => 'Catalog/Connector',
    '/catalog/audiobookshelf' => 'Catalog/Connector',
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
    // State-changing verbs (update/store/destroy/test/discover/dry-run/dismiss/
    // reopen/snapshot/normalize) must never be reachable via GET/HEAD.
    $stateChanging = ['store', 'update', 'destroy', 'delete', 'dismiss', 'reopen', 'dry-run', 'discover', 'selection', 'test', 'snapshot', 'normalize'];

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

    // Every real nav item delivered so far (V1 + the V2 external catalog).
    $navHrefs = ['/dashboard', '/connectors', '/catalog', '/sync', '/review', '/settings'];

    $getUris = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        ->map(fn ($route) => '/'.ltrim($route->uri(), '/'))
        ->all();

    foreach ($navHrefs as $href) {
        expect($layout)->toContain("href: '{$href}'");
        expect(in_array($href, $getUris, true))->toBeTrue("Nav href {$href} has no registered GET route.");
    }
});

test('every href in the frontend resolves to a registered GET route', function () {
    // The guardrail that catches a link to a page that does not exist. It reads
    // every literal href in every .tsx (including template literals such as
    // `/catalog/${key}/libraries/${id}`) and matches it against the real route
    // table, with route params and interpolations both normalised to '*'.
    $getPatterns = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        ->map(fn ($route) => preg_replace('/\{[^}]+\}/', '*', '/'.ltrim($route->uri(), '/')))
        ->unique()
        ->all();

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js')));
    $offenders = [];
    $checked = [];

    foreach ($iterator as $file) {
        if (!$file->isFile() || !str_ends_with((string) $file, '.tsx')) {
            continue;
        }

        $source = file_get_contents((string) $file);
        preg_match_all('/href=\{?[`"\']([^`"\']*)[`"\']\}?/', $source, $matches);

        foreach ($matches[1] as $href) {
            // Only app-internal links; skip external URLs, anchors and variables.
            if (!str_starts_with($href, '/')) {
                continue;
            }

            // `/catalog/${connector.key}` → `/catalog/*`; drop any query string.
            $pattern = preg_replace('/\$\{[^}]*\}/', '*', strtok($href, '?'));
            $checked[] = $pattern;

            if (!in_array($pattern, $getPatterns, true)) {
                $offenders[] = basename((string) $file).': '.$href;
            }
        }
    }

    expect($offenders)->toBe([], 'These hrefs have no registered GET route: '.implode(', ', $offenders));

    // Guard against a vacuous pass: the scanner must really see the app's links,
    // including the parameterised catalog/connector ones.
    expect(count($checked))->toBeGreaterThan(10)
        ->and($checked)->toContain('/dashboard', '/catalog', '/catalog/*', '/catalog/*/libraries/*', '/connectors/*');
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
