<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A configured instance of a connector type (e.g. one Jellyfin server). Secrets
 * are never stored here — only `secrets_ref` into the encrypted secret store.
 *
 * @property string $id
 * @property string $connector_key
 * @property string $name
 * @property string $base_url
 * @property array<string, mixed> $settings
 * @property string $secrets_ref
 * @property bool $enabled
 * @property string $conflict_strategy
 * @property string $health_status
 * @property string|null $health_detail
 * @property Carbon|null $last_healthy_at
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $libraries_discovered_at
 * @property string|null $last_discovery_error
 */
class ConnectorInstance extends Model
{
    use HasUlids;

    protected $table = 'connector_instances';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'enabled' => 'boolean',
            'last_healthy_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'libraries_discovered_at' => 'datetime',
        ];
    }

    /** @return HasMany<ConnectorSyncState, $this> */
    public function syncStates(): HasMany
    {
        return $this->hasMany(ConnectorSyncState::class, 'instance_id');
    }

    /** @return HasMany<ConnectorLibrary, $this> */
    public function libraries(): HasMany
    {
        return $this->hasMany(ConnectorLibrary::class, 'connector_instance_id');
    }
}
