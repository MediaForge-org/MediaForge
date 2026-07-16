<?php

declare(strict_types=1);

namespace App\Connectors\Sdk;

use App\Connectors\Sdk\Models\ConnectorCatalogItem;
use App\Connectors\Sdk\Models\ConnectorCatalogSnapshotRun;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Models\ConnectorSyncRun;
use App\Connectors\Sdk\Models\ConnectorSyncRunLibrary;
use App\Connectors\Sdk\Models\ConnectorSyncState;
use App\Connectors\Sdk\Registry\ConnectorRegistry;
use App\Connectors\Sdk\Secrets\EncryptedSecretStore;
use App\Connectors\Sdk\Secrets\SecretStore;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Connector SDK: the ConnectorRegistry, the encrypted secret
 * store and the shared sync/diagnostics infrastructure. Concrete connectors
 * (Jellyfin, Audiobookshelf) register themselves against the registry from
 * their own service providers.
 */
final class ConnectorSdkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectorRegistry::class);

        $this->app->bind(SecretStore::class, static fn (Application $app): EncryptedSecretStore => new EncryptedSecretStore(
            $app->make(DatabaseManager::class)->connection(),
            $app->make(Encrypter::class),
        ));
    }

    public function boot(): void
    {
        // Merge the connector morph aliases into the map (Core registers its own).
        Relation::enforceMorphMap([
            'connector_instance' => ConnectorInstance::class,
            'connector_sync_state' => ConnectorSyncState::class,
            'connector_library' => ConnectorLibrary::class,
            'connector_sync_run' => ConnectorSyncRun::class,
            'connector_sync_run_library' => ConnectorSyncRunLibrary::class,
            'connector_catalog_snapshot_run' => ConnectorCatalogSnapshotRun::class,
            'connector_catalog_item' => ConnectorCatalogItem::class,
        ]);
    }
}
