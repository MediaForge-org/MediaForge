<?php

declare(strict_types=1);

use App\Connectors\Sdk\Import\ImportPlanScope;
use App\Connectors\Sdk\Models\MediaImportExecution;
use App\Core\Media\MediaItem;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
});

/* ---------------------------------------------------------------------------
 | Routing / auth
 * ------------------------------------------------------------------------- */

test('a guest cannot execute an import', function () {
    seedImportableCatalog();
    $plan = createImportPlan();

    $this->post("/imports/{$plan->id}/execute-ready")
        ->assertRedirect('/login')
        ->assertHeader('Location', '/login');

    // The guard held: nothing was created.
    expect(MediaItem::query()->count())->toBe(0)
        ->and(MediaImportExecution::query()->count())->toBe(0);
});

test('a guest cannot view an execution', function () {
    seedImportableCatalog();
    $execution = executeImportPlan(createImportPlan());

    $this->get("/imports/runs/{$execution->id}")->assertRedirect('/login');
});

test('an authenticated user can execute a ready plan and lands on the run', function () {
    $user = User::factory()->create();
    seedImportableCatalog();
    $plan = createImportPlan();

    $response = $this->actingAs($user)->post("/imports/{$plan->id}/execute-ready");

    $execution = MediaImportExecution::query()->sole();

    $response->assertRedirect("/imports/runs/{$execution->id}")->assertSessionHas('success');

    expect($execution->imported_count)->toBe(4)
        ->and($execution->created_by)->toBe('user:'.$user->id)
        ->and(MediaItem::query()->count())->toBe(4);
});

test('the flash message says plainly that no files were touched', function () {
    $user = User::factory()->create();
    seedImportableCatalog();
    $plan = createImportPlan();

    $this->actingAs($user)->post("/imports/{$plan->id}/execute-ready")
        ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'No files were touched.'));
});

test('there is no GET route for executing an import', function () {
    $user = User::factory()->create();
    seedImportableCatalog();
    $plan = createImportPlan();

    // 405, not 404: the URI exists but answers POST only.
    $this->actingAs($user)->get("/imports/{$plan->id}/execute-ready")->assertMethodNotAllowed();

    expect(MediaItem::query()->count())->toBe(0);
});

test('executing an unknown plan returns 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/imports/'.Str::ulid().'/execute-ready')->assertNotFound();
    $this->actingAs($user)->post('/imports/not-a-ulid/execute-ready')->assertNotFound();

    expect(MediaImportExecution::query()->count())->toBe(0);
});

test('an unknown run returns 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/imports/runs/'.Str::ulid())->assertNotFound();
    $this->actingAs($user)->get('/imports/runs/not-a-ulid')->assertNotFound();
});

test('executing a plan with no ready items creates nothing and says so', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'jf-1', 'Mystery', 'unknown');
    normalizeConnector($instance);
    $plan = createImportPlan();

    $this->actingAs($user)->post("/imports/{$plan->id}/execute-ready")
        ->assertRedirect()
        ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'No ready items to import.'));

    expect(MediaItem::query()->count())->toBe(0)
        ->and(MediaImportExecution::query()->sole()->status)->toBe('empty');
});

/* ---------------------------------------------------------------------------
 | Rendering
 * ------------------------------------------------------------------------- */

test('the run page shows the execution in full', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    seedNormalizationItem($instance, $library, 'movie-1', 'The Matrix', 'movie', ['year' => 1999]);
    seedNormalizationItem($instance, $library, 'jf-2', 'Mystery', 'unknown');
    normalizeConnector($instance);

    $execution = executeImportPlan(createImportPlan());

    $this->actingAs($user)->get("/imports/runs/{$execution->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Imports/Run')
            ->where('execution.id', $execution->id)
            ->where('execution.status', 'completed_with_warnings')
            ->where('execution.imported_count', 1)
            ->where('execution.skipped_count', 1)
            ->where('execution.already_existing_count', 0)
            ->where('execution.failed_count', 0)
            ->has('execution.reasons')
            ->has('sections.created.data', 1)
            ->where('sections.created.data.0.title', 'The Matrix')
            ->where('sections.created.data.0.action', 'created')
            ->has('sections.created.data.0.action_label')
            ->where('sections.created.data.0.connector', 'jellyfin')
            ->where('sections.created.data.0.library_name', 'Movies')
            ->has('sections.linked_existing.data', 0)
            ->has('sections.skipped.data', 1)
            ->has('sections.failed.data', 0));

    Http::assertNothingSent();
});

test('the plan page offers the execute button only when there are ready items', function () {
    $user = User::factory()->create();
    seedImportableCatalog();
    $readyPlan = createImportPlan();

    $this->actingAs($user)->get("/imports/{$readyPlan->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Imports/Show')
            ->where('plan.ready_count', 4)
            ->has('executions', 0)
            ->has('importableKinds'));

    // A plan with nothing ready must not offer the action at all.
    [$other, $library] = seedNormalizationConnector('audiobookshelf', 'ABS-TOKEN');
    seedNormalizationItem($other, $library, 'abs-1', 'Mystery', 'unknown');
    normalizeConnector($other, 'audiobookshelf');
    $emptyPlan = createImportPlan(ImportPlanScope::Connector, $other);

    $this->actingAs($user)->get("/imports/{$emptyPlan->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('plan.ready_count', 0));

    // The button is bound to ready_count in the component itself.
    $source = file_get_contents(resource_path('js/Pages/Imports/Show.tsx'));
    expect($source)->toContain('const canImport = plan.ready_count > 0;')
        ->toContain('No ready items to import.');
});

test('the plan page lists the runs that already imported it', function () {
    $user = User::factory()->create();
    seedImportableCatalog();
    $plan = createImportPlan();
    $execution = executeImportPlan($plan);

    $this->actingAs($user)->get("/imports/{$plan->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('executions', 1)
            ->where('executions.0.id', $execution->id)
            ->where('executions.0.imported_count', 4));
});

test('the imports overview shows executions and the internal media summary', function () {
    $user = User::factory()->create();
    seedImportableCatalog();
    $plan = createImportPlan();
    $execution = executeImportPlan($plan);

    $this->actingAs($user)->get('/imports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Imports/Index')
            ->has('executions', 1)
            ->where('executions.0.id', $execution->id)
            ->where('internalMedia.media_items', 4)
            ->where('internalMedia.movies', 1)
            ->where('internalMedia.series', 1)
            ->where('internalMedia.seasons', 1)
            ->where('internalMedia.episodes', 1)
            ->where('internalMedia.execution_count', 1)
            ->where('internalMedia.latest_execution.id', $execution->id)
            // The plan is flagged as already imported.
            ->where("executionCounts.{$plan->id}", 1));

    Http::assertNothingSent();
});

test('the imports overview copes with no internal import at all', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/imports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('executions', 0)
            ->where('internalMedia.media_items', 0)
            ->where('internalMedia.latest_execution', null)
            ->has('executionCounts'));
});

test('the dashboard exposes the internal media summary from stored state only', function () {
    $user = User::factory()->create();
    seedImportableCatalog();
    executeImportPlan(createImportPlan());

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('internalMedia.media_items', 4)
            ->where('internalMedia.imported_items', 4)
            ->where('internalMedia.execution_count', 1)
            ->has('internalMedia.latest_execution'));

    Http::assertNothingSent();
});

test('the dashboard copes with an empty internal catalog', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('internalMedia.media_items', 0)
            ->where('internalMedia.latest_execution', null));
});

/* ---------------------------------------------------------------------------
 | Catalog import status
 * ------------------------------------------------------------------------- */

test('the catalog marks items as imported once they have an internal record', function () {
    $user = User::factory()->create();
    [$instance, $library] = seedNormalizationConnector();
    // Sorted by title: "A Weak Item" first, "The Matrix" second.
    // A title with nothing else to go on is the one verdict V2 C calls needs_review.
    seedNormalizationItem($instance, $library, 'weak-1', 'A Weak Item', 'movie', ['year' => null, 'runtime_seconds' => null]);
    seedNormalizationItem($instance, $library, 'movie-1', 'The Matrix', 'movie', ['year' => 1999]);
    normalizeConnector($instance);

    // Before the import, nothing is imported.
    $this->actingAs($user)->get('/catalog?sort=title&direction=asc')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.data.0.title', 'A Weak Item')
            ->where('items.data.0.import_status', 'needs_review')
            ->where('items.data.1.title', 'The Matrix')
            ->where('items.data.1.import_status', 'not_imported'));

    executeImportPlan(createImportPlan());

    $this->actingAs($user)->get('/catalog?sort=title&direction=asc')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Still needs review — the import refused it, exactly as planned.
            ->where('items.data.0.import_status', 'needs_review')
            ->where('items.data.1.import_status', 'imported'));

    Http::assertNothingSent();
});

test('the catalog table shows the import status but offers no import action', function () {
    $source = file_get_contents(resource_path('js/Components/Catalog/CatalogItemsTable.tsx'));

    expect($source)->toContain('CatalogImportStatusBadge')
        ->toContain('import_status')
        // The flow is catalog → dry run → plan → execute; never straight from here.
        ->not->toContain('execute-ready')
        ->not->toContain('router.post');
});

/* ---------------------------------------------------------------------------
 | Navigation
 * ------------------------------------------------------------------------- */

test('the run page links back to its plan and to the imports overview', function () {
    $user = User::factory()->create();
    seedImportableCatalog();
    $plan = createImportPlan();
    $execution = executeImportPlan($plan);

    $this->actingAs($user)->get("/imports/runs/{$execution->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('execution.plan_id', $plan->id));

    // Both link targets really resolve.
    $this->actingAs($user)->get("/imports/{$plan->id}")->assertOk();
    $this->actingAs($user)->get('/imports')->assertOk();
});
