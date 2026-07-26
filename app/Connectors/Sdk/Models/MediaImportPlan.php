<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One import DRY RUN over the normalized connector catalog (V2 D).
 *
 * A plan says what a LATER import WOULD create. It is a PLAN row, not a media row:
 * no media_item, media_edition or media_file is ever created from it in V2 D, no
 * file is copied, moved, deleted or renamed, nothing changes on
 * Jellyfin/Audiobookshelf and no match is accepted. `summary` carries sanitized
 * counts and reason codes only — never secrets, tokens, raw API payloads, stack
 * traces or local paths.
 *
 * @property string $id
 * @property string $scope_type
 * @property string|null $connector_instance_id
 * @property string|null $connector_library_id
 * @property string $status
 * @property int $source_item_count
 * @property int $planned_item_count
 * @property int $ready_count
 * @property int $warning_count
 * @property int $blocked_count
 * @property int $skipped_count
 * @property int $review_count
 * @property int $duplicate_count
 * @property int $unsupported_count
 * @property array<string, mixed> $summary
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaImportPlan extends Model
{
    use HasUlids;

    protected $table = 'media_import_plans';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'source_item_count' => 'integer',
            'planned_item_count' => 'integer',
            'ready_count' => 'integer',
            'warning_count' => 'integer',
            'blocked_count' => 'integer',
            'skipped_count' => 'integer',
            'review_count' => 'integer',
            'duplicate_count' => 'integer',
            'unsupported_count' => 'integer',
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

    /** @return HasMany<MediaImportPlanItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MediaImportPlanItem::class, 'media_import_plan_id');
    }
}
