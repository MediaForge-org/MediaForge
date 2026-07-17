<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Connectors\Sdk\Actions\NormalizeConnectorCatalogItems;
use App\Connectors\Sdk\Catalog\CatalogItemQuery;
use App\Connectors\Sdk\Catalog\ExternalMediaKind;
use App\Connectors\Sdk\Catalog\NormalizationIssue;
use App\Connectors\Sdk\CatalogMatchPreview;
use App\Connectors\Sdk\CatalogReadModel;
use App\Connectors\Sdk\ConnectorCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only External Catalog (V2 A/B/C). Renders the stored connector catalog
 * read-model: an overview, per-connector pages, per-library pages and a matching
 * preview, each with search / filter / sort / pagination over captured external
 * items plus their normalized verdict. Every dynamic query clause is allowlisted
 * (see CatalogItemQuery) and every list is bounded. No network calls, no secrets,
 * no media import, no file operations, no accepted matches — the GET pages only
 * render saved state, and the one POST here rebuilds a read-model.
 */
final class CatalogController extends Controller
{
    /** The whole external catalog: summary, connector cards, latest runs, browsable items. */
    public function index(Request $request, ConnectorCatalog $catalog, CatalogReadModel $readModel): Response
    {
        $connectors = $catalog->overview();

        $connectorKey = $this->validConnectorKey($request, $connectors);
        $query = $this->buildQuery($request, $connectorKey, $this->requestedLibraryId($request));
        $overview = $readModel->overview($connectors);

        return Inertia::render('Catalog/Index', [
            'connectors' => $connectors,
            'summary' => $overview['summary'],
            'latestRuns' => $overview['latest_runs'],
            'items' => $readModel->items($query),
            'libraryOptions' => $readModel->libraryOptions($connectorKey),
            'kinds' => ExternalMediaKind::values(),
            'issues' => NormalizationIssue::values(),
            'normalization' => $readModel->normalizationSummary($connectorKey, $query->libraryId),
            'filters' => $this->filtersPayload($query, $connectorKey),
        ]);
    }

    /** One connector: scoped summary, its libraries, latest runs and browsable items. */
    public function connector(Request $request, string $connector, ConnectorCatalog $catalog, CatalogReadModel $readModel): Response
    {
        $view = $catalog->view($connector);
        $instance = $catalog->instance($connector);
        $configured = $view['configured'] === true;

        $query = $this->buildQuery($request, $connector, $this->requestedLibraryId($request));
        $scope = $readModel->connectorScope($instance, $configured);

        return Inertia::render('Catalog/Connector', [
            'connector' => [
                'key' => $view['key'],
                'label' => $view['label'],
                'configured' => $configured,
                'status' => $view['status'],
                'catalog' => $scope['summary'],
            ],
            'libraries' => $scope['libraries'],
            'latestRuns' => $scope['latest_runs'],
            'items' => $readModel->items($query),
            'kinds' => ExternalMediaKind::values(),
            'issues' => NormalizationIssue::values(),
            'normalization' => $readModel->normalizationSummary($connector, $query->libraryId),
            'filters' => $this->filtersPayload($query, $connector),
        ]);
    }

    /** One library: scoped counts, last snapshot, snapshot CTA and browsable items. */
    public function library(Request $request, string $connector, string $library, ConnectorCatalog $catalog, CatalogReadModel $readModel): Response
    {
        $instance = $catalog->instance($connector);
        abort_if($instance === null, 404);

        $model = $readModel->findLibrary($instance->id, $library);
        abort_if($model === null, 404);

        $view = $catalog->view($connector);
        $query = $this->buildQuery($request, $connector, $model->id);

        return Inertia::render('Catalog/Library', [
            'connector' => [
                'key' => $view['key'],
                'label' => $view['label'],
                'configured' => $view['configured'] === true,
                'status' => $view['status'],
            ],
            'library' => [
                'id' => $model->id,
                'name' => $model->name,
                'type' => $model->collection_type,
                'external_id' => $model->external_id,
                'is_enabled' => $model->is_enabled,
                'discovery_status' => $model->discovery_status,
                'last_seen_at' => $model->last_seen_at?->toIso8601String(),
            ],
            'scope' => $readModel->libraryScope($instance, $model),
            'items' => $readModel->items($query),
            'kinds' => ExternalMediaKind::values(),
            'issues' => NormalizationIssue::values(),
            'normalization' => $readModel->normalizationSummary($connector, $model->id),
            'filters' => $this->filtersPayload($query, $connector),
        ]);
    }

    /**
     * V2 C matching preview: read-only suggestions derived from the normalized
     * catalog. There is deliberately no accept/import/merge action here — the
     * import plan arrives in V2 D.
     */
    public function matches(Request $request, ConnectorCatalog $catalog, CatalogMatchPreview $preview, CatalogReadModel $readModel): Response
    {
        $connectors = $catalog->overview();
        $connectorKey = $this->validConnectorKey($request, $connectors);
        $libraryId = $this->requestedLibraryId($request);

        return Inertia::render('Catalog/Matches', [
            'connectors' => array_map(
                static fn (array $connector): array => ['key' => $connector['key'], 'label' => $connector['label']],
                $connectors,
            ),
            'preview' => $preview->build($connectorKey, $libraryId),
            'normalization' => $readModel->normalizationSummary($connectorKey, $libraryId),
            'libraryOptions' => $readModel->libraryOptions($connectorKey),
            'filters' => ['connector' => $connectorKey ?? '', 'library' => $libraryId ?? ''],
        ]);
    }

    /**
     * Rebuild the normalized read-model for every configured connector. POST-only:
     * it writes read-model rows (never media), so it must not be reachable by GET.
     */
    public function normalize(ConnectorCatalog $catalog, NormalizeConnectorCatalogItems $action): RedirectResponse
    {
        $normalized = 0;

        foreach ($catalog->overview() as $connector) {
            $key = is_string($connector['key'] ?? null) ? $connector['key'] : null;
            $instance = $key !== null ? $catalog->instance($key) : null;

            if ($key === null || $instance === null) {
                continue;
            }

            $counts = $action->execute($instance, $key);
            $normalized += $counts['normalized'];
        }

        return back()->with(
            'success',
            "Normalization rebuilt for {$normalized} external ".($normalized === 1 ? 'item' : 'items').'. No media was imported and no match was accepted.',
        );
    }

    /** Rebuild the normalized read-model for one library. POST-only. */
    public function normalizeLibrary(string $connector, string $library, ConnectorCatalog $catalog, CatalogReadModel $readModel, NormalizeConnectorCatalogItems $action): RedirectResponse
    {
        $instance = $catalog->instance($connector);
        abort_if($instance === null, 404);

        $model = $readModel->findLibrary($instance->id, $library);
        abort_if($model === null, 404);

        $counts = $action->execute($instance, $connector, $model);

        return back()->with(
            'success',
            "Normalization rebuilt for {$counts['normalized']} external ".($counts['normalized'] === 1 ? 'item' : 'items').'. No media was imported and no match was accepted.',
        );
    }

    /**
     * Translate the request into an already-validated CatalogItemQuery. Every
     * dynamic clause is constrained to an allowlist here so nothing raw reaches SQL.
     */
    private function buildQuery(Request $request, ?string $connectorKey, ?string $libraryId): CatalogItemQuery
    {
        $sort = $request->string('sort')->toString();
        $direction = strtolower($request->string('direction')->toString());
        $status = $request->string('status')->toString();
        $kind = $request->string('kind')->toString();
        $normalization = $request->string('normalization')->toString();
        $issue = $request->string('issue')->toString();

        return new CatalogItemQuery(
            search: $request->filled('q') ? $request->string('q')->toString() : null,
            connectorKey: $connectorKey,
            libraryId: $libraryId,
            kind: in_array($kind, ExternalMediaKind::values(), true) ? $kind : null,
            status: in_array($status, CatalogItemQuery::STATUSES, true) ? $status : 'present',
            sort: in_array($sort, CatalogItemQuery::SORTS, true) ? $sort : 'title',
            direction: in_array($direction, CatalogItemQuery::DIRECTIONS, true) ? $direction : 'asc',
            page: max(1, $request->integer('page', 1)),
            normalization: in_array($normalization, CatalogItemQuery::NORMALIZATIONS, true) ? $normalization : 'all',
            issue: in_array($issue, NormalizationIssue::values(), true) ? $issue : null,
            duplicates: $request->boolean('duplicates'),
        );
    }

    /**
     * The applied filters, echoed back so the UI selects stay in sync. `connector`
     * is the registry key (not the instance id) to match the frontend option list.
     *
     * @return array<string, string>
     */
    private function filtersPayload(CatalogItemQuery $query, ?string $connectorKey): array
    {
        return [
            'q' => $query->search ?? '',
            'connector' => $connectorKey ?? '',
            'library' => $query->libraryId ?? '',
            'kind' => $query->kind ?? '',
            'status' => $query->status,
            'sort' => $query->sort,
            'direction' => $query->direction,
            'normalization' => $query->normalization,
            'issue' => $query->issue ?? '',
            'duplicates' => $query->duplicates ? '1' : '',
        ];
    }

    /**
     * The requested connector filter key if it is a registered connector, else null.
     *
     * @param  list<array<string, mixed>>  $connectors
     */
    private function validConnectorKey(Request $request, array $connectors): ?string
    {
        $key = $request->string('connector')->toString();

        if ($key === '') {
            return null;
        }

        $keys = array_map(static fn (array $connector): mixed => $connector['key'] ?? null, $connectors);

        return in_array($key, $keys, true) ? $key : null;
    }

    private function requestedLibraryId(Request $request): ?string
    {
        return $request->filled('library') ? $request->string('library')->toString() : null;
    }
}
