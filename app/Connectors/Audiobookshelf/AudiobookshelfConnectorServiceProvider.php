<?php

declare(strict_types=1);

namespace App\Connectors\Audiobookshelf;

use App\Connectors\Sdk\Registry\ConnectorRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Audiobookshelf connector with the Connector SDK registry. V1
 * Package C ships the connection test only; sync/diagnostics runtime is later.
 */
final class AudiobookshelfConnectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->make(ConnectorRegistry::class)
            ->register($this->app->make(AudiobookshelfConnector::class));
    }
}
