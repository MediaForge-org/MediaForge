<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Models;

use App\Core\Media\MediaItem;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Links one internal MediaForge media item to the external item a connector
 * reported (V2 E).
 *
 * This is the import's IDENTITY record and its idempotency guarantee: the unique
 * (connector_instance_id, external_id) means one external item can own at most one
 * internal item, so a repeated import links what exists instead of duplicating it.
 *
 * It lives under the connector SDK, not under App\Core, on purpose: it names
 * connector tables, and Core must never depend on connectors (see
 * tests/Arch/BoundariesTest.php). The consequence is that MediaItem has no
 * `externalMappings()` relation — the traversal is one-way, from here into Core.
 *
 * Stores identifiers and timestamps only: never a secret, a token, a raw API
 * payload or a file path.
 *
 * @property string $id
 * @property string $media_item_id
 * @property string $connector_instance_id
 * @property string|null $connector_library_id
 * @property string|null $connector_catalog_item_id
 * @property string|null $connector_catalog_item_normalization_id
 * @property string $external_id
 * @property string|null $external_parent_id
 * @property string $source_type
 * @property string|null $source_kind
 * @property Carbon|null $imported_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaExternalMapping extends Model
{
    use HasUlids;

    protected $table = 'media_external_mappings';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MediaItem, $this> */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'media_item_id');
    }

    /** @return BelongsTo<ConnectorInstance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(ConnectorInstance::class, 'connector_instance_id');
    }

    /** @return BelongsTo<ConnectorLibrary, $this> */
    public function library(): BelongsTo
    {
        return $this->belongsTo(ConnectorLibrary::class, 'connector_library_id');
    }

    /** @return BelongsTo<ConnectorCatalogItem, $this> */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(ConnectorCatalogItem::class, 'connector_catalog_item_id');
    }
}
