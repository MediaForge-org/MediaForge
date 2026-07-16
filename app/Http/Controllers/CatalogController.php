<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Connectors\Sdk\CatalogReadModel;
use App\Connectors\Sdk\ConnectorCatalog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * V2 A: read-only External Catalog overview. Aggregates each connector's stored
 * catalog snapshot state plus the latest snapshot runs and captured external items.
 * No network calls, no secrets — this only renders saved read-model state. No media
 * is imported and no file is ever touched.
 */
final class CatalogController extends Controller
{
    public function index(ConnectorCatalog $catalog, CatalogReadModel $readModel): Response
    {
        $connectors = $catalog->overview();
        $overview = $readModel->overview($connectors);

        return Inertia::render('Catalog/Index', [
            'connectors' => $connectors,
            'summary' => $overview['summary'],
            'latestRuns' => $overview['latest_runs'],
            'latestItems' => $overview['latest_items'],
        ]);
    }
}
