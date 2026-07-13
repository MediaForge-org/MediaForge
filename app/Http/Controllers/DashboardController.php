<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Connectors\Sdk\ConnectorCatalog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The authenticated dashboard shell. Surfaces the V1 foundation status plus a
 * secret-free connector health summary so the operator sees connector state at a
 * glance without opening the connector pages.
 */
final class DashboardController extends Controller
{
    public function index(ConnectorCatalog $catalog): Response
    {
        return Inertia::render('Dashboard', [
            'status' => 'V1 foundation',
            'connectors' => $catalog->overview(),
        ]);
    }
}
