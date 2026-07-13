<?php

declare(strict_types=1);

namespace App\Connectors\Sdk;

use App\Connectors\Sdk\Diagnostics\ConnectorHealth;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Registry\ConnectorRegistry;
use App\Connectors\Sdk\Secrets\SecretStore;

/**
 * Read model for the connector UI. Produces secret-free view arrays for the
 * overview, the detail pages and the dashboard cards. It exposes whether a secret
 * exists (`secret_configured`) but never the secret itself.
 */
final class ConnectorCatalog
{
    public function __construct(
        private readonly ConnectorRegistry $registry,
        private readonly SecretStore $secrets,
    ) {}

    /** @return list<array<string, mixed>> One entry per registered connector. */
    public function overview(): array
    {
        return array_map(
            fn (string $key): array => $this->view($key),
            $this->registry->keys(),
        );
    }

    /** @return array<string, mixed> */
    public function view(string $key): array
    {
        $provider = $this->registry->get($key);
        $instance = $this->instance($key);

        $secretConfigured = $instance !== null && $this->secrets->has($instance->secrets_ref);
        $configured = $instance !== null && $instance->base_url !== '' && $secretConfigured;

        $health = $instance !== null
            ? ConnectorHealth::from($instance->health_status)
            : ConnectorHealth::Unknown;

        return [
            'key' => $provider->key(),
            'label' => $provider->label(),
            'base_url' => $instance !== null ? $instance->base_url : '',
            'configured' => $configured,
            'secret_configured' => $secretConfigured,
            'status' => $configured ? $health->uiStatus() : 'not_configured',
            'health_status' => $health->value,
            'health_detail' => $instance?->health_detail,
            'last_checked_at' => $instance?->last_checked_at?->toIso8601String(),
            'last_healthy_at' => $instance?->last_healthy_at?->toIso8601String(),
            // Discovery aggregates only — the full library list is added by detail().
            'library_count' => $instance !== null
                ? ConnectorLibrary::query()->where('connector_instance_id', $instance->id)->count()
                : 0,
            'libraries_discovered_at' => $instance?->libraries_discovered_at?->toIso8601String(),
            'last_discovery_error' => $instance?->last_discovery_error,
        ];
    }

    /**
     * The detail view: the aggregate view plus the full discovered-library list.
     * Only the current connector's libraries are loaded (no cross-connector data).
     *
     * @return array<string, mixed>
     */
    public function detail(string $key): array
    {
        $view = $this->view($key);
        $instance = $this->instance($key);

        $view['libraries'] = $instance !== null ? $this->libraries($instance) : [];

        return $view;
    }

    public function instance(string $key): ?ConnectorInstance
    {
        return ConnectorInstance::query()
            ->where('connector_key', $key)
            ->first();
    }

    /** @return array<int, array<string, mixed>> */
    private function libraries(ConnectorInstance $instance): array
    {
        return ConnectorLibrary::query()
            ->where('connector_instance_id', $instance->id)
            ->orderBy('name')
            ->get()
            ->map(static fn (ConnectorLibrary $library): array => [
                'id' => $library->id,
                'external_id' => $library->external_id,
                'name' => $library->name,
                'type' => $library->collection_type,
                'path' => $library->path,
                'is_enabled' => $library->is_enabled,
                'discovery_status' => $library->discovery_status,
                'last_seen_at' => $library->last_seen_at?->toIso8601String(),
            ])
            ->all();
    }
}
