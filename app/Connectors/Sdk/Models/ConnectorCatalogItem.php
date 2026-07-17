<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One external item captured from a connector library by a read-only snapshot
 * (V2 A). This is a CONNECTOR READ-MODEL, NOT a MediaForge media item — it never
 * becomes a media_item/edition/file and no file is ever touched. Holds only small,
 * sanitized display fields (never secrets, tokens, raw payloads, or full paths).
 *
 * @property string $id
 * @property string $connector_instance_id
 * @property string|null $connector_library_id
 * @property string|null $snapshot_run_id
 * @property string $external_id
 * @property string|null $external_parent_id
 * @property string $media_kind
 * @property string $title
 * @property string|null $sort_title
 * @property string|null $original_title
 * @property int|null $year
 * @property int|null $index_number
 * @property int|null $parent_index_number
 * @property int|null $runtime_seconds
 * @property Carbon|null $external_updated_at
 * @property Carbon|null $first_seen_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $missing_since
 * @property bool $is_present
 * @property array<string, mixed> $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ConnectorCatalogItem extends Model
{
    use HasUlids;

    protected $table = 'connector_catalog_items';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'year' => 'integer',
            'index_number' => 'integer',
            'parent_index_number' => 'integer',
            'runtime_seconds' => 'integer',
            'is_present' => 'boolean',
            'external_updated_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'missing_since' => 'datetime',
        ];
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

    /** @return BelongsTo<ConnectorCatalogSnapshotRun, $this> */
    public function snapshotRun(): BelongsTo
    {
        return $this->belongsTo(ConnectorCatalogSnapshotRun::class, 'snapshot_run_id');
    }

    /** The V2 C normalized read-model of this item (one row, rebuilt in place). */
    /** @return HasOne<ConnectorCatalogItemNormalization, $this> */
    public function normalization(): HasOne
    {
        return $this->hasOne(ConnectorCatalogItemNormalization::class, 'connector_catalog_item_id');
    }
}
