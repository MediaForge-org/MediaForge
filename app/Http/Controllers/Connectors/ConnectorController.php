<?php

declare(strict_types=1);

namespace App\Http\Controllers\Connectors;

use App\Connectors\Sdk\Actions\ConnectorConfigInput;
use App\Connectors\Sdk\Actions\DiscoverConnectorLibraries;
use App\Connectors\Sdk\Actions\RunConnectorTest;
use App\Connectors\Sdk\Actions\SaveConnectorConfig;
use App\Connectors\Sdk\Actions\UpdateConnectorLibrarySelection;
use App\Connectors\Sdk\ConnectorCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Connectors\SaveConnectorRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Connector configuration surface. All persistence is delegated to SDK actions
 * (SaveConnectorConfig / RunConnectorTest) so the controller stays free of the
 * database and secrets. The {connector} route param is constrained to the
 * registered keys, so a view/action lookup here is always valid.
 */
final class ConnectorController extends Controller
{
    public function index(ConnectorCatalog $catalog): Response
    {
        return Inertia::render('Connectors/Index', [
            'connectors' => $catalog->overview(),
        ]);
    }

    public function show(string $connector, ConnectorCatalog $catalog): Response
    {
        return Inertia::render('Connectors/Show', [
            'connector' => $catalog->detail($connector),
        ]);
    }

    public function update(SaveConnectorRequest $request, string $connector, SaveConnectorConfig $action): RedirectResponse
    {
        $action->execute(new ConnectorConfigInput(
            key: $connector,
            baseUrl: $request->string('base_url')->toString(),
            secret: $request->filled('secret') ? $request->string('secret')->toString() : null,
            clearSecret: $request->boolean('clear_secret'),
        ));

        return redirect('/connectors/'.$connector)->with('success', 'Connector configuration saved.');
    }

    public function test(string $connector, ConnectorCatalog $catalog, RunConnectorTest $action): RedirectResponse
    {
        if ($catalog->view($connector)['configured'] !== true) {
            return back()->with('error', 'Add a base URL and API key before testing the connection.');
        }

        $result = $action->execute($connector);

        return back()->with(
            $result->health->isHealthy() ? 'success' : 'error',
            $result->detail,
        );
    }

    public function discover(string $connector, ConnectorCatalog $catalog, DiscoverConnectorLibraries $action): RedirectResponse
    {
        if ($catalog->view($connector)['configured'] !== true) {
            return back()->with('error', 'Configure and connect the connector before discovering libraries.');
        }

        $result = $action->execute($connector);

        return back()->with($result->ok ? 'success' : 'error', $result->detail);
    }

    public function updateLibrary(Request $request, string $connector, string $library, UpdateConnectorLibrarySelection $action): RedirectResponse
    {
        $action->execute($connector, $library, $request->boolean('enabled'));

        return back()->with('success', 'Library selection updated.');
    }
}
