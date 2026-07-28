<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Models;

use App\Core\Media\MediaItem;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What the internal import did with ONE plan line (V2 E), and why.
 *
 * Every line the execution looked at gets exactly one row: created, linked to an
 * already-imported record, skipped with a reason, or failed. A refusal is recorded
 * as explicitly as a success — that is the point of the table.
 *
 * `reason_codes` holds stable sanitized enum codes only; `title`/`media_kind` are
 * denormalized display copies so the run stays readable after a plan is deleted.
 * Never a secret, a raw API payload, a stack trace or a local path.
 *
 * @property string $id
 * @property string $media_import_execution_id
 * @property string|null $media_import_plan_item_id
 * @property string|null $media_item_id
 * @property string|null $connector_catalog_item_id
 * @property string $title
 * @property string $media_kind
 * @property string $action
 * @property string $status
 * @property list<string> $reason_codes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaImportExecutionItem extends Model
{
    use HasUlids;

    protected $table = 'media_import_execution_items';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reason_codes' => 'array',
        ];
    }

    /** @return BelongsTo<MediaImportExecution, $this> */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(MediaImportExecution::class, 'media_import_execution_id');
    }

    /** @return BelongsTo<MediaImportPlanItem, $this> */
    public function planItem(): BelongsTo
    {
        return $this->belongsTo(MediaImportPlanItem::class, 'media_import_plan_item_id');
    }

    /** @return BelongsTo<MediaItem, $this> */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'media_item_id');
    }

    /** @return BelongsTo<ConnectorCatalogItem, $this> */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(ConnectorCatalogItem::class, 'connector_catalog_item_id');
    }
}
