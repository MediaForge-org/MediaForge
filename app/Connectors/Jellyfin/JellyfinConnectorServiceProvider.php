<?php

declare(strict_types=1);

namespace App\Connectors\Jellyfin;

use Illuminate\Support\ServiceProvider;

/**
 * Registers the Jellyfin connector manifest, client and diagnostics with the
 * Connector SDK registry. Implemented in the connector milestone (M6).
 */
final class JellyfinConnectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
