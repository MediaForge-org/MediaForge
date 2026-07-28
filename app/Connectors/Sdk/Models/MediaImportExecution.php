<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One run of the first internal import over a V2 D plan (V2 E).
 *
 * It is a DATABASE-ONLY import. The run creates MediaForge records and their
 * external mappings; it copies, moves, deletes or renames no file, stores no file
 * path, creates no media_files row, and writes nothing to Jellyfin or
 * Audiobookshelf (no scan, no library refresh). It accepts no match and merges no
 * duplicate.
 *
 * `summary` carries sanitized counts and reason codes only — never secrets, tokens,
 * raw API payloads, stack traces or local paths.
 *
 * @property string $id
 * @property string $media_import_plan_id
 * @property string $status
 * @property int $imported_count
 * @property int $skipped_count
 * @property int $already_existing_count
 * @property int $failed_count
 * @property array<string, mixed> $summary
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaImportExecution extends Model
{
    use HasUlids;

    protected $table = 'media_import_executions';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'imported_count' => 'integer',
            'skipped_count' => 'integer',
            'already_existing_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    /** @return BelongsTo<MediaImportPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(MediaImportPlan::class, 'media_import_plan_id');
    }

    /** @return HasMany<MediaImportExecutionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MediaImportExecutionItem::class, 'media_import_execution_id');
    }
}
