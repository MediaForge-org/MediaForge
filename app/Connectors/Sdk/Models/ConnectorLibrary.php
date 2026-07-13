<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A library exposed by a configured connector, captured at discovery time. Holds
 * library-level metadata only — never media items, secrets or raw API payloads.
 *
 * @property string $id
 * @property string $connector_instance_id
 * @property string $provider_key
 * @property string $external_id
 * @property string $name
 * @property string|null $collection_type
 * @property string|null $path
 * @property bool $is_enabled
 * @property string $discovery_status
 * @property Carbon|null $last_seen_at
 * @property array<string, mixed> $metadata
 */
class ConnectorLibrary extends Model
{
    use HasUlids;

    protected $table = 'connector_libraries';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<ConnectorInstance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(ConnectorInstance::class, 'connector_instance_id');
    }
}
