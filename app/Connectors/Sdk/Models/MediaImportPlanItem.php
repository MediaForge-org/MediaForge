<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One planned line of an import dry run (V2 D): "IF an import ran, this captured
 * external item would become X".
 *
 * It is never the thing itself. V2 D creates no media_items/editions/files from
 * these rows, performs and plans no file operation, and accepts no match. The
 * `target_*` fields are a LOGICAL identity (kind, title, year, season/episode plus
 * a hashed stable key) and deliberately contain no path — there is nothing here to
 * move, copy, delete or rename. `reasons` holds stable sanitized codes and
 * `source_snapshot` a minimal display echo; never secrets, tokens, raw API
 * payloads, stack traces or local paths.
 *
 * @property string $id
 * @property string $media_import_plan_id
 * @property string|null $connector_catalog_item_id
 * @property string|null $connector_catalog_item_normalization_id
 * @property string $connector_instance_id
 * @property string|null $connector_library_id
 * @property string $planned_kind
 * @property string $planned_action
 * @property string $status
 * @property string|null $target_key
 * @property string $target_title
 * @property string|null $target_parent_key
 * @property int|null $target_year
 * @property int|null $target_season_number
 * @property int|null $target_episode_number
 * @property int $confidence
 * @property list<string> $reasons
 * @property array<string, mixed> $source_snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaImportPlanItem extends Model
{
    use HasUlids;

    protected $table = 'media_import_plan_items';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reasons' => 'array',
            'source_snapshot' => 'array',
            'target_year' => 'integer',
            'target_season_number' => 'integer',
            'target_episode_number' => 'integer',
            'confidence' => 'integer',
        ];
    }

    /** @return BelongsTo<MediaImportPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(MediaImportPlan::class, 'media_import_plan_id');
    }

    /** @return BelongsTo<ConnectorCatalogItem, $this> */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(ConnectorCatalogItem::class, 'connector_catalog_item_id');
    }

    /**
     * The normalized reading this line was planned from (V2 C). The internal import
     * (V2 E) reads runtime and sort title from here — stored state, never the network.
     *
     * @return BelongsTo<ConnectorCatalogItemNormalization, $this>
     */
    public function normalization(): BelongsTo
    {
        return $this->belongsTo(ConnectorCatalogItemNormalization::class, 'connector_catalog_item_normalization_id');
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
