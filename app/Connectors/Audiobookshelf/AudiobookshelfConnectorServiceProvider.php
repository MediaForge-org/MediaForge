<?php

declare(strict_types=1);

namespace App\Connectors\Audiobookshelf;

use Illuminate\Support\ServiceProvider;

/**
 * Registers the Audiobookshelf connector manifest, client and diagnostics with
 * the Connector SDK registry. Implemented in the connector milestone (M6).
 */
final class AudiobookshelfConnectorServiceProvider extends ServiceProvider
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
