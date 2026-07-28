<?php

declare(strict_types=1);

use App\Connectors\Sdk\Import\ImportPlanScope;
use App\Connectors\Sdk\Models\MediaImportPlan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    // Rendering an import plan must never touch the network.
    Http::preventStrayRequests();
});

/* ---------------------------------------------------------------------------
 | Routing / auth
 * ------------------------------------------------------------------------- */

test('guests cannot view the import plans overview', function () {
    $this->get('/imports')->assertRedirect('/login')->assertHeader('Location', '/login');
});

test('guests cannot view a single import plan', function () {
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    normalizeConnector($instance);
    $plan = createImportPlan();

    $this->get("/imports/{$plan->id}")->assertRedirect('/login');
});

test('guests cannot create an import dry run', function () {
    $this->post('/imports/dry-run', ['scope' => 'all'])->assertRedirect('/login');

    expect(MediaImportPlan::query()->count())->toBe(0);
});

test('an authenticated user can view the import plans overview', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/imports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Imports/Index')
            ->has('summary.plan_count')
            ->has('summary.planned_items')
            ->has('summary.ready_items')
            ->has('summary.blocked_items')
            ->has('summary.review_items')
            ->has('summary.duplicate_items')
            ->has('summary.unsupported_items')
            ->where('latestPlan', null)
            ->has('plans', 0)
            ->has('connectors', 2));

    Http::assertNothingSent();
});

test('an authenticated user can create an import dry run and lands on the plan', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    normalizeConnector($instance);

    $response = $this->actingAs($user)->post('/imports/dry-run', ['scope' => 'all']);

    $plan = MediaImportPlan::query()->sole();
    $response->assertRedirect("/imports/{$plan->id}")->assertSessionHas('success');

    expect($plan->created_by)->toBe('user:'.$user->id);
});

test('a dry run can be scoped to a connector and to a library over HTTP', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    normalizeConnector($instance);

    $this->actingAs($user)->post('/imports/dry-run', ['scope' => 'connector', 'connector' => 'jellyfin'])
        ->assertRedirect();

    $this->actingAs($user)->post('/imports/dry-run', [
        'scope' => 'library',
        'connector' => 'jellyfin',
        'library' => $library->id,
    ])->assertRedirect();

    expect(MediaImportPlan::query()->where('scope_type', 'connector')->count())->toBe(1)
        ->and(MediaImportPlan::query()->where('scope_type', 'library')->count())->toBe(1);
});

test('there is no GET route for creating a dry run', function () {
    $user = User::factory()->create();
    seedNormalizationConnector();

    // 405, not 404: the URI exists but only answers POST — and the ULID
    // constraint on /imports/{plan} keeps "dry-run" from being read as a plan id.
    $this->actingAs($user)->get('/imports/dry-run')->assertMethodNotAllowed();

    expect(MediaImportPlan::query()->count())->toBe(0);
});

test('an unknown connector scope returns 404', function () {
    $user = User::factory()->create();
    seedNormalizationConnector();

    $this->actingAs($user)->post('/imports/dry-run', ['scope' => 'connector', 'connector' => 'plex'])
        ->assertNotFound();

    // A registered connector with no configured instance is a 404 too — never a
    // silently widened scope.
    $this->actingAs($user)->post('/imports/dry-run', ['scope' => 'connector', 'connector' => 'audiobookshelf'])
        ->assertNotFound();

    expect(MediaImportPlan::query()->count())->toBe(0);
});

test('a library that does not belong to the connector returns 404', function () {
    $user = User::factory()->create();
    seedNormalizationConnector('jellyfin');
    [, $absLibrary] = seedNormalizationConnector('audiobookshelf', 'ABS-TOKEN');

    $this->actingAs($user)->post('/imports/dry-run', [
        'scope' => 'library',
        'connector' => 'jellyfin',
        'library' => $absLibrary->id,
    ])->assertNotFound();

    expect(MediaImportPlan::query()->count())->toBe(0);
});

test('an unknown plan id returns 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/imports/'.Str::ulid())->assertNotFound();
    $this->actingAs($user)->get('/imports/not-a-ulid')->assertNotFound();
});

/* ---------------------------------------------------------------------------
 | Rendering
 * ------------------------------------------------------------------------- */

test('the overview lists the latest plans with their outcome', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    seedNormalizationItem($instance, $library, 'jf-2', 'Mystery', 'unknown');
    normalizeConnector($instance);

    $plan = createImportPlan();

    $this->actingAs($user)->get('/imports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Imports/Index')
            ->where('summary.plan_count', 1)
            ->where('summary.latest_status', 'warnings')
            ->where('summary.planned_items', 2)
            ->where('summary.ready_items', 1)
            ->where('summary.review_items', 1)
            ->where('latestPlan.id', $plan->id)
            ->has('plans', 1)
            ->where('plans.0.status', 'warnings'));

    Http::assertNothingSent();
});

test('a plan detail page groups items into ready, warning, review, blocked and skipped', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');                                        // ready
    seedNormalizationItem($instance, $library, 'jf-2', 'Yearless', 'movie', ['year' => null]);               // warning
    seedNormalizationItem($instance, $library, 'jf-3', 'Mystery', 'unknown');                                // needs review
    seedNormalizationItem($instance, $library, 'jf-4', '   ');                                               // blocked
    seedNormalizationItem($instance, $library, 'jf-5', 'A Folder', 'folder', ['year' => null, 'runtime_seconds' => null]);
    normalizeConnector($instance);

    $plan = createImportPlan();

    $this->actingAs($user)->get("/imports/{$plan->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Imports/Show')
            ->where('plan.id', $plan->id)
            ->where('plan.status', 'blocked')
            ->has('sections.ready.data', 1)
            ->has('sections.warning.data', 1)
            ->has('sections.needs_review.data', 1)
            ->has('sections.blocked.data', 1)
            ->has('sections.skipped.data', 1)
            ->where('sections.ready.data.0.target_title', 'The Matrix')
            ->where('sections.ready.data.0.planned_action', 'create_media')
            ->has('sections.ready.data.0.planned_action_label')
            ->has('sections.ready.data.0.reasons')
            ->where('sections.ready.data.0.connector.key', 'jellyfin')
            ->where('sections.ready.data.0.library_name', 'Movies')
            ->has('targets')
            ->has('duplicates.data'));

    Http::assertNothingSent();
});

test('a plan detail page lists the extra copies of a duplicate separately', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    seedNormalizationItem($instance, $library, 'jf-2', 'The Matrix');
    normalizeConnector($instance);

    $plan = createImportPlan();

    // One copy stays importable; only the EXTRA copy is listed for a decision.
    $this->actingAs($user)->get("/imports/{$plan->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('duplicates.total', 1)
            ->has('duplicates.data', 1)
            ->where('duplicates.data.0.planned_action', 'skip_duplicate')
            ->where('plan.ready_count', 1));
});

test('a plan detail page explains why the plan came out the way it did', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'Mystery', 'unknown');
    normalizeConnector($instance);

    $plan = createImportPlan();

    $this->actingAs($user)->get("/imports/{$plan->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('plan.reasons')
            ->where('plan.note', 'Dry run only. No media is imported and no files are copied, moved, deleted or renamed.'));
});

test('the dashboard exposes the latest import plan summary from stored state only', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    normalizeConnector($instance);
    createImportPlan(ImportPlanScope::Connector, $instance);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('importSummary.plan_count', 1)
            ->where('importSummary.status', 'ready')
            ->where('importSummary.planned_items', 1)
            ->where('importSummary.ready_items', 1)
            ->where('importSummary.blocked_items', 0));

    Http::assertNothingSent();
});

test('the dashboard copes with no import plan at all', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('importSummary.status', 'empty')
            ->where('importSummary.plan_id', null));
});

/* ---------------------------------------------------------------------------
 | Entry points from the catalog
 * ------------------------------------------------------------------------- */

test('the catalog pages offer a scoped import dry run', function () {
    $index = file_get_contents(resource_path('js/Pages/Catalog/Index.tsx'));
    $connector = file_get_contents(resource_path('js/Pages/Catalog/Connector.tsx'));
    $libraryPage = file_get_contents(resource_path('js/Pages/Catalog/Library.tsx'));

    expect($index)->toContain('Create import dry run')
        ->toContain("router.post('/imports/dry-run'")
        ->toContain('View import plans');

    expect($connector)->toContain('Create import dry run for connector')
        ->toContain("scope: 'connector'");

    expect($libraryPage)->toContain('Create import dry run for library')
        ->toContain("scope: 'library'");
});

test('the matching preview points at the import dry run without offering to accept anything', function () {
    $source = file_get_contents(resource_path('js/Pages/Catalog/Matches.tsx'));

    expect($source)->toContain('Use import dry run to see how these suggestions would affect a future import.')
        ->toContain('/imports');
});

test('the sidebar exposes import plans as a real navigation link', function () {
    $layout = file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.tsx'));

    expect($layout)->toContain("href: '/imports'")
        ->toContain('Import Plans');
});
