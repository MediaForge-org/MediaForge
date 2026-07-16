<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Connectors\Sdk\CatalogReadModel;
use App\Connectors\Sdk\ConnectorCatalog;
use App\Connectors\Sdk\ReviewCenterCatalog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The authenticated dashboard shell. Surfaces the V1 foundation status plus a
 * secret-free connector health summary so the operator sees connector state at a
 * glance without opening the connector pages.
 */
final class DashboardController extends Controller
{
    public function index(ConnectorCatalog $catalog, ReviewCenterCatalog $reviewCenter, CatalogReadModel $catalogReadModel): Response
    {
        $connectors = $catalog->overview();
        $openTaskCount = $reviewCenter->openTaskCount();

        return Inertia::render('Dashboard', [
            'status' => 'V1 foundation',
            'connectors' => $connectors,
            'syncSummary' => $this->syncSummary($connectors),
            'reviewSummary' => [
                'status' => $reviewCenter->status($connectors, $openTaskCount),
                'open_task_count' => $openTaskCount,
            ],
            'catalogSummary' => $catalogReadModel->dashboardSummary($connectors),
        ]);
    }

    /**
     * Aggregate the connectors' stored sync-foundation state for the dashboard.
     * Pure array math over data already loaded — no extra queries, no network.
     *
     * @param  list<array<string, mixed>>  $connectors
     * @return array<string, mixed>
     */
    private function syncSummary(array $connectors): array
    {
        $selectedLibraries = 0;
        $attention = 0;
        $lastDryRunAt = null;
        $ready = 0;

        foreach ($connectors as $connector) {
            /** @var array<string, mixed> $sync */
            $sync = is_array($connector['sync'] ?? null) ? $connector['sync'] : [];

            $selectedCount = $sync['selected_count'] ?? 0;
            $selectedLibraries += is_numeric($selectedCount) ? (int) $selectedCount : 0;

            if (($sync['status'] ?? null) === 'attention_required') {
                $attention++;
            }

            if (($sync['status'] ?? null) === 'last_dry_run_completed') {
                $ready++;
            }

            $finishedAt = is_array($sync['last_run'] ?? null) ? ($sync['last_run']['finished_at'] ?? null) : null;

            if (is_string($finishedAt) && ($lastDryRunAt === null || $finishedAt > $lastDryRunAt)) {
                $lastDryRunAt = $finishedAt;
            }
        }

        return [
            'selected_libraries' => $selectedLibraries,
            'attention_count' => $attention,
            'ready_count' => $ready,
            'last_dry_run_at' => $lastDryRunAt,
        ];
    }
}
