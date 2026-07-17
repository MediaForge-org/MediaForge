<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The normalized read-model of one captured external catalog item (V2 C). It
 * interprets the connector's reported fields into a consistent shape plus a quality
 * verdict (status/confidence/issues) so data problems and match candidates become
 * visible.
 *
 * This is a CONNECTOR READ-MODEL, NOT a MediaForge media item: it never becomes a
 * media_item/edition/file, no file is touched, nothing is imported and no match is
 * accepted. `issues`/`normalized_data` carry only small sanitized values — never
 * secrets, tokens, raw API payloads or local paths.
 *
 * @property string $id
 * @property string $connector_catalog_item_id
 * @property string $connector_instance_id
 * @property string|null $connector_library_id
 * @property string $normalized_kind
 * @property string $normalized_title
 * @property string|null $normalized_sort_title
 * @property string|null $normalized_original_title
 * @property int|null $release_year
 * @property int|null $season_number
 * @property int|null $episode_number
 * @property string|null $parent_title
 * @property int|null $runtime_seconds
 * @property int $confidence
 * @property string $status
 * @property list<string> $issues
 * @property array<string, mixed> $normalized_data
 * @property Carbon|null $normalized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ConnectorCatalogItemNormalization extends Model
{
    use HasUlids;

    protected $table = 'connector_catalog_item_normalizations';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'issues' => 'array',
            'normalized_data' => 'array',
            'release_year' => 'integer',
            'season_number' => 'integer',
            'episode_number' => 'integer',
            'runtime_seconds' => 'integer',
            'confidence' => 'integer',
            'normalized_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ConnectorCatalogItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ConnectorCatalogItem::class, 'connector_catalog_item_id');
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
}
