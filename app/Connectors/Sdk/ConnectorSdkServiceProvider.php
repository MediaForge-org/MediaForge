<?php

declare(strict_types=1);

namespace App\Connectors\Sdk;

use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorSyncState;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Connector SDK: the ConnectorRegistry, the encrypted secret
 * store and the shared sync/diagnostics infrastructure. Concrete connectors
 * (Jellyfin, Audiobookshelf) register their manifests against the registry
 * from their own service providers. Filled out in the connector milestone (M6).
 */
final class ConnectorSdkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ConnectorRegistry + SecretStore bindings are registered here in M6.
    }

    public function boot(): void
    {
        // Merge the connector morph aliases into the map (Core registers its own).
        Relation::enforceMorphMap([
            'connector_instance' => ConnectorInstance::class,
            'connector_sync_state' => ConnectorSyncState::class,
        ]);
    }
}
