<?php

declare(strict_types=1);

namespace App\Connectors\Jellyfin;

use App\Connectors\Sdk\Registry\ConnectorRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Jellyfin connector with the Connector SDK registry. V1 Package C
 * ships the connection test only; sync/diagnostics runtime is later.
 */
final class JellyfinConnectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->make(ConnectorRegistry::class)
            ->register($this->app->make(JellyfinConnector::class));
    }
}
