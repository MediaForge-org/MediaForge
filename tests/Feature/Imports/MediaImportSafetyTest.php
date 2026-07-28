<?php

declare(strict_types=1);

use App\Connectors\Sdk\Models\MediaExternalMapping;
use App\Connectors\Sdk\Models\MediaImportExecution;
use App\Connectors\Sdk\Models\MediaImportExecutionItem;
use App\Core\Audit\AuditLog;
use App\Core\Media\MediaEdition;
use App\Core\Media\MediaFile;
use App\Core\Media\MediaItem;
use App\Core\Review\ReviewTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
});

/** Every source file V2 E introduced or drives the import through. */
function importExecutionSources(): array
{
    return [
        app_path('Connectors/Sdk/Actions/ExecuteMediaImportPlan.php'),
        app_path('Connectors/Sdk/Actions/CreateMediaImportReviewTasks.php'),
        app_path('Connectors/Sdk/Import/ImportPlanItemGate.php'),
        app_path('Connectors/Sdk/Import/ImportableMediaKind.php'),
        app_path('Connectors/Sdk/Import/ImportEligibility.php'),
        app_path('Connectors/Sdk/MediaImportReadModel.php'),
        app_path('Connectors/Sdk/Models/MediaExternalMapping.php'),
        app_path('Connectors/Sdk/Models/MediaImportExecution.php'),
        app_path('Connectors/Sdk/Models/MediaImportExecutionItem.php'),
        app_path('Http/Controllers/ImportPlanController.php'),
    ];
}

/** The tables V2 E introduced, plus the catalog table it now populates. */
function importedTables(): array
{
    return ['media_items', 'media_external_mappings', 'media_import_executions', 'media_import_execution_items'];
}

/* ---------------------------------------------------------------------------
 | No file operations, anywhere
 * ------------------------------------------------------------------------- */

test('the internal import performs no file operation and writes to no disk', function () {
    Storage::fake('local');
    seedImportableCatalog();

    $execution = executeImportPlan(createImportPlan());

    expect($execution->imported_count)->toBe(4)
        // Records were created, yet not a single byte was written anywhere
        // MediaForge could reach.
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('the import source contains no filesystem or shell call at all', function () {
    $forbidden = [
        'File::copy', 'File::move', 'File::delete', 'File::put',
        'Storage::put', 'Storage::delete', 'Storage::move', 'Storage::copy', 'Storage::disk',
        'unlink(', 'rename(', 'copy(', 'rmdir(', 'mkdir(', 'fopen(', 'file_put_contents(',
        'move_uploaded_file(', 'shell_exec', 'proc_open', 'passthru', 'system(',
    ];

    foreach (importExecutionSources() as $path) {
        $code = File::get($path);

        foreach ($forbidden as $needle) {
            expect(str_contains($code, $needle))->toBeFalse("{$path} references {$needle}.");
        }
    }
});

test('no imported table has a file path column', function () {
    // Read the REAL schema rather than the migration text, so a comment mentioning
    // "path" can never make this pass or fail by accident.
    $pathish = ['path', 'file', 'filename', 'directory', 'disk', 'mount', 'storage'];

    foreach (importedTables() as $table) {
        $columns = DB::table('information_schema.columns')
            ->where('table_name', $table)
            ->pluck('column_name');

        expect($columns)->not->toBeEmpty("Table {$table} does not exist.");

        foreach ($columns as $column) {
            foreach ($pathish as $needle) {
                expect(str_contains(strtolower((string) $column), $needle))
                    ->toBeFalse("Table {$table} has a path-like column '{$column}'.");
            }
        }
    }
});

test('the internal import creates no media files and no editions', function () {
    seedImportableCatalog();

    executeImportPlan(createImportPlan());

    expect(MediaItem::query()->count())->toBe(4)
        // V2 E deliberately introduces neither of these.
        ->and(MediaEdition::query()->count())->toBe(0)
        ->and(MediaFile::query()->count())->toBe(0);
});

test('an imported media item stores no path in its metadata', function () {
    seedImportableCatalog();

    executeImportPlan(createImportPlan());

    foreach (MediaItem::query()->get() as $item) {
        expect(array_keys($item->metadata))
            ->toEqualCanonicalizing(['connector', 'source_kind', 'import_plan_id']);
        expect(str_contains((string) json_encode($item->metadata), '/'))->toBeFalse();
    }
});

/* ---------------------------------------------------------------------------
 | Nothing leaves the machine
 * ------------------------------------------------------------------------- */

test('the internal import makes no remote request of any kind', function () {
    seedImportableCatalog();

    executeImportPlan(createImportPlan());

    // No write, no scan trigger, no library refresh — no request at all.
    Http::assertNothingSent();
});

test('rendering the import pages makes no remote request', function () {
    $user = User::factory()->create();
    seedImportableCatalog();
    $plan = createImportPlan();
    $execution = executeImportPlan($plan);

    foreach (['/imports', "/imports/{$plan->id}", "/imports/runs/{$execution->id}", '/dashboard', '/catalog'] as $path) {
        $this->actingAs($user)->get($path)->assertOk();
    }

    Http::assertNothingSent();
});

test('rendering a run does not re-import anything', function () {
    $user = User::factory()->create();
    seedImportableCatalog();
    $execution = executeImportPlan(createImportPlan());

    $before = MediaItem::query()->count();

    $this->actingAs($user)->get("/imports/runs/{$execution->id}")->assertOk();
    $this->actingAs($user)->get("/imports/runs/{$execution->id}")->assertOk();

    expect(MediaItem::query()->count())->toBe($before)
        ->and(MediaImportExecution::query()->count())->toBe(1);
});

/* ---------------------------------------------------------------------------
 | Secrets stay out of everything the import produces
 * ------------------------------------------------------------------------- */

test('nothing the import writes carries the connector token', function () {
    seedImportableCatalog('EXECUTION-DO-NOT-LEAK');

    $execution = executeImportPlan(createImportPlan());

    expect(json_encode($execution->toArray()))->not->toContain('EXECUTION-DO-NOT-LEAK');

    foreach (MediaItem::query()->get() as $item) {
        expect(json_encode($item->toArray()))->not->toContain('EXECUTION-DO-NOT-LEAK');
    }

    foreach (MediaExternalMapping::query()->get() as $mapping) {
        expect(json_encode($mapping->toArray()))->not->toContain('EXECUTION-DO-NOT-LEAK');
    }

    foreach (MediaImportExecutionItem::query()->get() as $line) {
        expect(json_encode($line->toArray()))->not->toContain('EXECUTION-DO-NOT-LEAK');
    }
});

test('the audit and review evidence carry no token', function () {
    [$instance, $library] = seedNormalizationConnector('jellyfin', 'AUDIT-EXEC-LEAK');
    seedNormalizationItem($instance, $library, 'jf-1', 'Mystery', 'unknown');
    normalizeConnector($instance);

    executeImportPlan(createImportPlan());

    $task = ReviewTask::query()->where('task_type', 'media_import_execution')->sole();
    expect(json_encode($task->evidence))->not->toContain('AUDIT-EXEC-LEAK');

    $serialized = AuditLog::query()->get()
        ->map(fn (AuditLog $log): string => json_encode($log->changes).json_encode($log->context))
        ->implode('');

    expect($serialized)->not->toContain('AUDIT-EXEC-LEAK');
});

test('the import pages never render a stored secret', function () {
    $user = User::factory()->create();
    seedImportableCatalog('PAGE-EXEC-LEAK');
    $plan = createImportPlan();
    $execution = executeImportPlan($plan);

    foreach (['/imports', "/imports/{$plan->id}", "/imports/runs/{$execution->id}", '/dashboard', '/catalog'] as $path) {
        $this->actingAs($user)->get($path)
            ->assertOk()
            ->assertDontSee('PAGE-EXEC-LEAK', false);
    }
});

test('the execution stores no raw API payload', function () {
    seedImportableCatalog();

    $execution = executeImportPlan(createImportPlan());

    expect(array_keys($execution->summary))->toEqualCanonicalizing([
        'plan_id', 'scope', 'connector', 'candidate_count', 'cap', 'reasons', 'note',
    ]);

    foreach (MediaImportExecutionItem::query()->get() as $line) {
        expect($line->reason_codes)->each->toBeString();
    }
});

/* ---------------------------------------------------------------------------
 | No accept / merge / remote write anywhere
 * ------------------------------------------------------------------------- */

test('no route can accept a match, merge a duplicate or push to a media server', function () {
    $forbidden = ['accept', 'merge', 'import-now', 'commit', 'refresh', 'scan', 'push'];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        foreach ($forbidden as $needle) {
            expect(str_contains($route->uri(), $needle))->toBeFalse(
                "Route {$route->uri()} looks like it accepts/merges/pushes (matched '{$needle}')."
            );
        }
    }
});

test('executing an import is POST-only and guarded', function () {
    $executeRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_contains($route->uri(), 'execute'));

    // Exactly ONE execute route exists in the whole application.
    expect($executeRoutes)->toHaveCount(1);

    $route = $executeRoutes->first();
    expect($route->uri())->toBe('imports/{plan}/execute-ready')
        ->and($route->methods())->toContain('POST')
        ->and($route->methods())->not->toContain('GET')
        ->and($route->gatherMiddleware())->toContain('auth');
});

test('the import pages offer no move, rename, merge, accept or remote-sync action', function () {
    foreach (['Index', 'Show', 'Run'] as $component) {
        $source = file_get_contents(resource_path("js/Pages/Imports/{$component}.tsx"));

        foreach ([
            'Move files', 'Rename', 'Accept match', 'Accept suggestion', 'Merge', 'Delete',
            'Sync to Jellyfin', 'Sync to Audiobookshelf', 'Import now all', 'Execute full import',
        ] as $label) {
            expect(str_contains($source, $label))->toBeFalse("Imports/{$component}.tsx offers a '{$label}' action.");
        }
    }

    // The one import action is the database-only one, and it is a POST.
    $show = file_get_contents(resource_path('js/Pages/Imports/Show.tsx'));
    expect($show)->toContain('Import ready items into MediaForge')
        ->toContain('/execute-ready')
        ->toContain('INTERNAL_IMPORT_SAFETY_NOTE');

    expect(substr_count($show, 'router.post'))->toBe(1);
});

test('the safety promise is stated on every import surface', function () {
    expect(file_get_contents(resource_path('js/Components/Imports/ImportExecutionStatus.tsx')))
        ->toContain('Internal import only. No files are copied, moved, deleted or renamed.');

    foreach (['Index', 'Show'] as $component) {
        expect(file_get_contents(resource_path("js/Pages/Imports/{$component}.tsx")))
            ->toContain('INTERNAL_IMPORT_SAFETY_NOTE');
    }

    expect(file_get_contents(resource_path('js/Pages/Imports/Run.tsx')))
        ->toContain('No files were touched');
});

test('the import pages link only to existing, allowed routes', function () {
    foreach (['Index', 'Show', 'Run'] as $component) {
        $source = file_get_contents(resource_path("js/Pages/Imports/{$component}.tsx"));

        expect($source)->not->toContain('/admin')
            ->not->toContain('/profile')
            ->not->toContain('/downloads')
            ->not->toContain('href="/libraries"')
            ->not->toContain("href='/libraries'")
            ->not->toContain('href="/logout"');
    }
});

test('the run page does not link to a media item detail route that does not exist', function () {
    $source = file_get_contents(resource_path('js/Pages/Imports/Run.tsx'));

    // media_item_id is carried in the props for provenance, but it must not become
    // a link while there is no route to land on.
    expect($source)->not->toContain('href={`/media/')
        ->not->toContain('/media-items/');

    $mediaRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'media'));

    expect($mediaRoutes)->toBeEmpty();
});
