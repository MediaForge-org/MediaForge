<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Connectors\Sdk\Actions\CreateMediaImportPlan;
use App\Connectors\Sdk\ConnectorCatalog;
use App\Connectors\Sdk\Import\ImportPlanScope;
use App\Connectors\Sdk\ImportPlanReadModel;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Models\MediaImportPlan;
use App\Connectors\Sdk\Registry\ConnectorRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Import plan / import dry run (V2 D).
 *
 * The GET pages render STORED plan rows only: no network call, no snapshot run, no
 * normalization rebuild. The single POST creates a new dry run — a plan describing
 * what a LATER import would create.
 *
 * V2 D performs no import. It writes no media_items, media_editions or media_files,
 * copies/moves/deletes/renames no file, changes nothing on Jellyfin or
 * Audiobookshelf, and accepts no match. There is deliberately no execute/import/
 * accept/merge endpoint here for the UI to call.
 */
final class ImportPlanController extends Controller
{
    /** Import plans overview: the latest dry run plus recent plans. */
    public function index(ConnectorCatalog $catalog, ImportPlanReadModel $readModel): Response
    {
        $overview = $readModel->overview();

        return Inertia::render('Imports/Index', [
            'summary' => $overview['summary'],
            'latestPlan' => $overview['latestPlan'],
            'plans' => $overview['plans'],
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
    public function show(string $plan, ImportPlanReadModel $readModel): Response
    {
        $model = MediaImportPlan::query()
            ->with(['instance:id,connector_key', 'library:id,name'])
            ->find($plan);

        abort_if($model === null, 404);

        return Inertia::render('Imports/Show', $readModel->planDetail($model));
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
