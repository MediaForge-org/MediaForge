<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

use App\Connectors\Sdk\Diagnostics\LibraryDiscoveryRequest;
use App\Connectors\Sdk\Diagnostics\LibraryDiscoveryResult;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Registry\ConnectorRegistry;
use App\Connectors\Sdk\Secrets\SecretStore;
use App\Core\Actions\AuditableAction;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use Illuminate\Database\DatabaseManager;
use RuntimeException;

/**
 * Discovers a connector's libraries and persists them. The network probe happens
 * OUTSIDE the transaction; only the upsert and audit are transactional. Existing
 * libraries are updated in place (selection preserved), vanished ones are flagged
 * `missing` rather than deleted, and a failed probe never wipes what we have.
 */
final class DiscoverConnectorLibraries extends AuditableAction
{
    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
        private readonly SecretStore $secrets,
        private readonly ConnectorRegistry $registry,
    ) {
        parent::__construct($audit, $db);
    }

    public function execute(string $key): LibraryDiscoveryResult
    {
        $provider = $this->registry->get($key);

        $instance = ConnectorInstance::query()->where('connector_key', $key)->first();

        if ($instance === null) {
            throw new RuntimeException("Connector {$key} is not configured.");
        }

        $result = $provider->discoverLibraries(new LibraryDiscoveryRequest(
            $instance->base_url,
            $this->secrets->get($instance->secrets_ref),
        ));

        $count = $result->ok ? count($result->libraries) : 0;

        $this->transact(
            $instance,
            new AuditChange(
                'connector.libraries_discovered',
                ['count' => $count],
                ['connector' => $key, 'http_status' => $result->httpStatus, 'ok' => $result->ok],
            ),
            function () use ($instance, $key, $result): void {
                if (!$result->ok) {
                    $instance->last_discovery_error = $result->detail;
                    $instance->save();

                    return;
                }

                $now = now();
                $seen = [];

                foreach ($result->libraries as $library) {
                    ConnectorLibrary::query()->updateOrCreate(
                        ['connector_instance_id' => $instance->id, 'external_id' => $library->externalId],
                        [
                            'provider_key' => $key,
                            'name' => $library->name,
                            'collection_type' => $library->type,
                            'path' => $library->path,
                            'discovery_status' => 'present',
                            'last_seen_at' => $now,
                            'metadata' => $library->metadata,
                        ],
                    );

                    $seen[] = $library->externalId;
                }

                ConnectorLibrary::query()
                    ->where('connector_instance_id', $instance->id)
                    ->whereNotIn('external_id', $seen)
                    ->update(['discovery_status' => 'missing']);

                $instance->libraries_discovered_at = $now;
                $instance->last_discovery_error = null;
                $instance->save();
            },
        );

        return $result;
    }
}
