<?php

declare(strict_types=1);

use App\Connectors\Sdk\Import\ImportPlanScope;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Models\MediaImportPlan;
use App\Connectors\Sdk\Models\MediaImportPlanItem;
use App\Core\Audit\AuditLog;
use App\Core\Media\MediaEdition;
use App\Core\Media\MediaFile;
use App\Core\Media\MediaItem;
use App\Core\Review\ReviewTask;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
});

/**
 * Seed a connector whose catalog covers every plan outcome, so one dry run
 * exercises the whole planner.
 *
 * @return array{0: ConnectorInstance, 1: ConnectorLibrary}
 */
function seedImportSecurityCatalog(string $token = 'IMPORT-DO-NOT-LEAK'): array
{
    [$instance, $library] = seedNormalizationConnector('jellyfin', $token);

    seedNormalizationItem($instance, $library, 'jf-1', 'The Matrix');
    seedNormalizationItem($instance, $library, 'jf-2', 'The Matrix');                                   // duplicate suspect
    seedNormalizationItem($instance, $library, 'jf-3', 'Mystery', 'unknown');                           // needs review
    seedNormalizationItem($instance, $library, 'jf-4', '   ');                                          // blocked
    seedNormalizationItem($instance, $library, 'jf-5', 'A Folder', 'folder', ['year' => null, 'runtime_seconds' => null]);
    normalizeConnector($instance);

    return [$instance, $library];
}

/* ---------------------------------------------------------------------------
 | Nothing is imported, nothing is touched
 * ------------------------------------------------------------------------- */

test('a dry run creates no media items, editions or files', function () {
    seedImportSecurityCatalog();

    createImportPlan();

    expect(MediaItem::query()->count())->toBe(0)
        ->and(MediaEdition::query()->count())->toBe(0)
        ->and(MediaFile::query()->count())->toBe(0);
});

test('a dry run performs no file operation and writes to no disk', function () {
    Storage::fake('local');
    seedImportSecurityCatalog();

    createImportPlan();

    // No file was written anywhere MediaForge could reach.
    expect(Storage::disk('local')->allFiles())->toBe([]);

    // And the planner never even names a path: every planned target is a logical
    // identity, so there is nothing to copy, move, delete or rename.
    foreach (MediaImportPlanItem::query()->get() as $item) {
        expect(str_contains((string) $item->target_key, '/'))->toBeFalse()
            ->and(str_contains((string) $item->target_parent_key, '/'))->toBeFalse()
            ->and(str_contains((string) json_encode($item->source_snapshot), '/'))->toBeFalse();
    }
});

test('the import plan source contains no file operation at all', function () {
    $sources = [
        app_path('Connectors/Sdk/Actions/CreateMediaImportPlan.php'),
        app_path('Connectors/Sdk/Actions/CreateImportPlanReviewTasks.php'),
        app_path('Connectors/Sdk/Import/PlanCatalogItemImport.php'),
        app_path('Connectors/Sdk/ImportPlanReadModel.php'),
        app_path('Http/Controllers/ImportPlanController.php'),
    ];

    foreach ($sources as $path) {
        $code = File::get($path);

        foreach (['File::copy', 'File::move', 'File::delete', 'Storage::', 'unlink(', 'rename(', 'copy(', 'rmdir('] as $needle) {
            expect(str_contains($code, $needle))->toBeFalse("{$path} references {$needle}.");
        }

        // Nor does it write a media table.
        foreach (['MediaItem', 'MediaEdition', 'MediaFile'] as $model) {
            expect(str_contains($code, $model))->toBeFalse("{$path} references {$model}.");
        }
    }
});

test('rendering an import plan makes no remote request', function () {
    $user = User::factory()->create();
    seedImportSecurityCatalog();
    $plan = createImportPlan();

    $this->actingAs($user)->get('/imports')->assertOk();
    $this->actingAs($user)->get("/imports/{$plan->id}")->assertOk();
    $this->actingAs($user)->get('/dashboard')->assertOk();

    Http::assertNothingSent();
});

test('creating a dry run makes no remote request and changes nothing on the media servers', function () {
    $user = User::factory()->create();
    seedImportSecurityCatalog();

    $this->actingAs($user)->post('/imports/dry-run', ['scope' => 'all'])->assertRedirect();

    Http::assertNothingSent();
});

/* ---------------------------------------------------------------------------
 | Secrets stay out of everything a plan produces
 * ------------------------------------------------------------------------- */

test('a stored plan never carries the connector token', function () {
    seedImportSecurityCatalog();

    $plan = createImportPlan();

    expect(json_encode($plan->toArray()))->not->toContain('IMPORT-DO-NOT-LEAK');

    foreach (MediaImportPlanItem::query()->get() as $item) {
        expect(json_encode($item->toArray()))->not->toContain('IMPORT-DO-NOT-LEAK');
    }
});

test('the plan item snapshot stores only minimal derived hints, never a raw payload', function () {
    seedImportSecurityCatalog();

    createImportPlan();

    foreach (MediaImportPlanItem::query()->get() as $item) {
        expect(array_keys($item->source_snapshot))
            ->toEqualCanonicalizing(['source_kind', 'normalization_status', 'normalization_confidence']);
        expect($item->reasons)->each->toBeString();
    }
});

test('the review evidence and the audit entry carry no token', function () {
    seedImportSecurityCatalog();

    createImportPlan();

    $task = ReviewTask::query()->where('task_type', 'media_import_plan')->sole();
    expect(json_encode($task->evidence))->not->toContain('IMPORT-DO-NOT-LEAK');

    $serialized = AuditLog::query()->get()
        ->map(fn (AuditLog $log): string => json_encode($log->changes).json_encode($log->context))
        ->implode('');

    expect($serialized)->not->toContain('IMPORT-DO-NOT-LEAK');
});

test('the import pages never render a stored secret', function () {
    $user = User::factory()->create();
    seedImportSecurityCatalog();
    $plan = createImportPlan();

    foreach (['/imports', "/imports/{$plan->id}", '/dashboard'] as $path) {
        $this->actingAs($user)->get($path)
            ->assertOk()
            ->assertDontSee('IMPORT-DO-NOT-LEAK', false);
    }
});

/* ---------------------------------------------------------------------------
 | No execute / import / accept / merge anywhere
 * ------------------------------------------------------------------------- */

test('no route can accept a match or merge a duplicate, and only one route imports', function () {
    // V2 E added exactly ONE state-changing import route, and it writes database
    // records only. Accepting a match and merging a duplicate remain impossible —
    // there is still no route that could perform either.
    $forbidden = ['accept', 'merge', 'import-now', 'commit'];
    $executeRoutes = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        foreach ($forbidden as $needle) {
            expect(str_contains($route->uri(), $needle))->toBeFalse(
                "Route {$route->uri()} looks like it accepts or merges (matched '{$needle}')."
            );
        }

        if (str_contains($route->uri(), 'execute')) {
            $executeRoutes[] = $route;
        }
    }

    expect($executeRoutes)->toHaveCount(1);
    expect($executeRoutes[0]->uri())->toBe('imports/{plan}/execute-ready')
        ->and($executeRoutes[0]->methods())->toContain('POST')
        ->and($executeRoutes[0]->methods())->not->toContain('GET');
});

test('the import plan pages offer no accept, merge or file action', function () {
    // V2 E added one import action (database-only, on the plan page). Everything
    // that would touch a file, a media server or a match decision stays absent.
    foreach (['Index', 'Show'] as $component) {
        $source = file_get_contents(resource_path("js/Pages/Imports/{$component}.tsx"));

        foreach ([
            'Execute full import', 'Import now all', 'Accept match', 'Merge', 'Move files', 'Rename',
            'Sync to Jellyfin', 'Sync to Audiobookshelf', 'Delete',
        ] as $label) {
            expect(str_contains($source, $label))->toBeFalse("Imports/{$component}.tsx offers a '{$label}' action.");
        }

        // The dry-run promise is stated on both pages.
        expect($source)->toContain('IMPORT_SAFETY_NOTE');
    }

    // The plan page's one action is the DB-only import, clearly labelled as such.
    $show = file_get_contents(resource_path('js/Pages/Imports/Show.tsx'));
    expect($show)->toContain('Import ready items into MediaForge')
        ->toContain('DB only')
        ->toContain('INTERNAL_IMPORT_SAFETY_NOTE');

    // And the promises themselves still say what they must.
    expect(file_get_contents(resource_path('js/Components/Imports/ImportPlanStatus.tsx')))
        ->toContain('Dry run only. No media is imported and no files are copied, moved, deleted or renamed.');
    expect(file_get_contents(resource_path('js/Components/Imports/ImportExecutionStatus.tsx')))
        ->toContain('Internal import only. No files are copied, moved, deleted or renamed.');
});

test('the import pages link only to existing, allowed routes', function () {
    foreach (['Index', 'Show'] as $component) {
        $source = file_get_contents(resource_path("js/Pages/Imports/{$component}.tsx"));

        expect($source)->not->toContain('/admin')
            ->not->toContain('/profile')
            ->not->toContain('/downloads')
            ->not->toContain('href="/libraries"')
            ->not->toContain("href='/libraries'")
            ->not->toContain('href="/logout"');
    }
});

test('creating a dry run is POST-only', function () {
    $dryRunRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_contains($route->uri(), 'imports/dry-run'));

    expect($dryRunRoutes)->toHaveCount(1);

    $dryRunRoutes->each(function ($route) {
        expect($route->methods())->toContain('POST')
            ->and($route->methods())->not->toContain('GET');
    });
});

test('a scoped plan cannot be widened by an inconsistent scope payload', function () {
    $user = User::factory()->create();
    [$jellyfin, $jellyfinLibrary] = seedImportSecurityCatalog();
    [$abs, $absLibrary] = seedNormalizationConnector('audiobookshelf', 'ABS-TOKEN');
    seedNormalizationItem($abs, $absLibrary, 'abs-1', 'Dune', 'audiobook');
    normalizeConnector($abs, 'audiobookshelf');

    // A library id from another connector never widens or crosses the scope.
    $this->actingAs($user)->post('/imports/dry-run', [
        'scope' => 'library',
        'connector' => 'jellyfin',
        'library' => $absLibrary->id,
    ])->assertNotFound();

    // An unrecognised scope falls back to the explicit default rather than to a
    // connector-scoped plan built from unvalidated input.
    $this->actingAs($user)->post('/imports/dry-run', ['scope' => 'everything', 'connector' => 'plex'])
        ->assertRedirect();

    expect(MediaImportPlan::query()->sole()->scope_type)->toBe('all');
});

test('a library-scoped plan holds only that library items', function () {
    [$instance, $library] = seedImportSecurityCatalog();
    $second = seedNormalizationLibrary($instance, 'jf-second', 'Second');
    seedNormalizationItem($instance, $second, 'jf-9', 'Elsewhere');
    normalizeConnector($instance);

    $plan = createImportPlan(ImportPlanScope::Library, $instance, $second);

    expect($plan->planned_item_count)->toBe(1)
        ->and(MediaImportPlanItem::query()->where('media_import_plan_id', $plan->id)->sole()->target_title)
        ->toBe('Elsewhere');
});
