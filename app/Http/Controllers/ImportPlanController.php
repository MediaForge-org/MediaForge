<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Connectors\Sdk\Actions\CreateMediaImportPlan;
use App\Connectors\Sdk\Actions\ExecuteMediaImportPlan;
use App\Connectors\Sdk\ConnectorCatalog;
use App\Connectors\Sdk\Import\ImportExecutionStatus;
use App\Connectors\Sdk\Import\ImportPlanScope;
use App\Connectors\Sdk\ImportPlanReadModel;
use App\Connectors\Sdk\MediaImportReadModel;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Models\MediaImportExecution;
use App\Connectors\Sdk\Models\MediaImportPlan;
use App\Connectors\Sdk\Registry\ConnectorRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Import plan / import dry run (V2 D) and its internal execution (V2 E).
 *
 * The GET pages render STORED rows only: no network call, no snapshot run, no
 * normalization rebuild, no plan creation and no import. Two POSTs change state —
 * one creates a dry run, one executes a plan's READY lines into the MediaForge
 * database.
 *
 * The execution is DATABASE-ONLY. It copies, moves, deletes or renames no file,
 * stores no file path, creates no media_files row, writes nothing to Jellyfin or
 * Audiobookshelf (no scan, no library refresh), accepts no match and merges no
 * duplicate. There is deliberately no endpoint that could do any of those.
 */
final class ImportPlanController extends Controller
{
    /** Import plans overview: the latest dry run, recent plans and recent runs. */
    public function index(ConnectorCatalog $catalog, ImportPlanReadModel $readModel, MediaImportReadModel $imports): Response
    {
        $overview = $readModel->overview();

        /** @var list<array<string, mixed>> $plans */
        $plans = $overview['plans'];
        $planIds = [];

        foreach ($plans as $plan) {
            if (is_string($plan['id'] ?? null)) {
                $planIds[] = $plan['id'];
            }
        }

        return Inertia::render('Imports/Index', [
            'summary' => $overview['summary'],
            'latestPlan' => $overview['latestPlan'],
            'plans' => $plans,
            // V2 E: which plans have already been imported, and what the runs did.
            'executionCounts' => $imports->executionCountsByPlan($planIds),
            'executions' => $imports->recentExecutions(),
            'internalMedia' => $imports->internalMediaSummary(),
            'connectors' => array_map(
                static fn (array $connector): array => [
                    'key' => is_string($connector['key'] ?? null) ? $connector['key'] : '',
                    'label' => is_string($connector['label'] ?? null) ? $connector['label'] : '',
                    'configured' => ($connector['configured'] ?? false) === true,
                ],
                $catalog->overview(),
            ),
        ]);
    }

    /** One import dry run in full: header, planned targets and every outcome list. */
    public function show(string $plan, ImportPlanReadModel $readModel, MediaImportReadModel $imports): Response
    {
        $model = $this->findPlan($plan);

        return Inertia::render('Imports/Show', [
            ...$readModel->planDetail($model),
            // V2 E: has this plan been imported, and may it be?
            'executions' => $imports->executionsForPlan($model),
            'importableKinds' => $imports->importableKinds(),
        ]);
    }

    /**
     * One internal import run in full. Reads stored execution rows only — it never
     * re-runs the import and never touches a file.
     */
    public function run(string $run, MediaImportReadModel $imports): Response
    {
        $execution = MediaImportExecution::query()->with('plan')->find($run);

        abort_if($execution === null, 404);

        return Inertia::render('Imports/Run', $imports->executionDetail($execution));
    }

    /**
     * Execute a plan's READY lines into the MediaForge database. POST-only: it is
     * the one route in the application that creates media records, so it must never
     * be reachable by GET.
     *
     * Database only. Nothing is copied, moved, deleted or renamed, nothing is sent
     * to Jellyfin or Audiobookshelf, and needs-review / blocked / skipped /
     * duplicate / unsupported lines are refused, not imported.
     */
    public function executeReady(Request $request, string $plan, ExecuteMediaImportPlan $action): RedirectResponse
    {
        $model = $this->findPlan($plan);
        $user = $request->user();

        $execution = $action->execute($model, $user !== null ? 'user:'.$user->id : null);

        if ($execution->status === ImportExecutionStatus::Failed->value) {
            return to_route('imports.runs.show', ['run' => $execution->id])->with(
                'error',
                'The internal import was rolled back and nothing was created. No files were touched.',
            );
        }

        return to_route('imports.runs.show', ['run' => $execution->id])->with(
            'success',
            $this->executionMessage($execution),
        );
    }

    private function executionMessage(MediaImportExecution $execution): string
    {
        if ($execution->status === ImportExecutionStatus::Empty->value) {
            return 'No ready items to import. Nothing was created and no files were touched.';
        }

        $imported = $execution->imported_count;
        $linked = $execution->already_existing_count;
        $skipped = $execution->skipped_count;

        return "Imported ready items into MediaForge database: {$imported} created, {$linked} already imported, {$skipped} skipped. No files were touched.";
    }

    private function findPlan(string $plan): MediaImportPlan
    {
        $model = MediaImportPlan::query()
            ->with(['instance:id,connector_key', 'library:id,name'])
            ->find($plan);

        abort_if($model === null, 404);

        return $model;
    }

    /**
     * Create an import dry run for a scope (all / one connector / one library).
     * POST-only: it writes plan rows, so it must never be reachable by GET.
     */
    public function store(
        Request $request,
        ConnectorRegistry $registry,
        CreateMediaImportPlan $action,
    ): RedirectResponse {
        $scope = ImportPlanScope::tryFrom($request->string('scope')->toString()) ?? ImportPlanScope::All;

        [$instance, $library] = $this->resolveScope($request, $registry, $scope);

        $user = $request->user();

        $plan = $action->execute(
            $scope,
            $instance,
            $library,
            $user !== null ? 'user:'.$user->id : null,
        );

        return to_route('imports.show', ['plan' => $plan->id])->with(
            'success',
            "Import dry run created for {$plan->planned_item_count} external ".
            ($plan->planned_item_count === 1 ? 'item' : 'items').
            '. Nothing was imported and no file was copied, moved, deleted or renamed.',
        );
    }

    /**
     * Resolve and validate the requested scope. An unknown connector, an
     * unconfigured connector or a library that does not belong to it is a 404 —
     * never a silently widened scope.
     *
     * @return array{0: ConnectorInstance|null, 1: ConnectorLibrary|null}
     */
    private function resolveScope(Request $request, ConnectorRegistry $registry, ImportPlanScope $scope): array
    {
        if ($scope === ImportPlanScope::All) {
            return [null, null];
        }

        $key = $request->string('connector')->toString();
        abort_unless($registry->has($key), 404);

        $instance = ConnectorInstance::query()->where('connector_key', $key)->first();
        abort_if($instance === null, 404);

        if ($scope === ImportPlanScope::Connector) {
            return [$instance, null];
        }

        $library = ConnectorLibrary::query()
            ->where('connector_instance_id', $instance->id)
            ->where('id', $request->string('library')->toString())
            ->first();

        abort_if($library === null, 404);

        return [$instance, $library];
    }
}
