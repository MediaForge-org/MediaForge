<?php

declare(strict_types=1);

namespace App\Core\Media;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Canonical catalog entry (typed, self-hierarchical).
 *
 * Empty throughout V1 by design; the first internal import (V2 E) is what finally
 * populates it, from an approved import plan. A row here is a DATABASE RECORD and
 * nothing more: it holds no file path, owns no file, and creating one copies,
 * moves, deletes or renames nothing. `source` says where it came from and
 * `created_by_import_execution_id` which run created it.
 *
 * @property string $id
 * @property string|null $library_id
 * @property string $media_type
 * @property string|null $parent_id
 * @property int|null $sort_index
 * @property string $title
 * @property string|null $sort_title
 * @property string|null $original_title
 * @property int|null $year
 * @property int|null $runtime_ms
 * @property int|null $season_number
 * @property int|null $episode_number
 * @property string $presence
 * @property string $source
 * @property string|null $created_by_import_execution_id
 * @property array<string, mixed> $metadata
 */
class MediaItem extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'media_items';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sort_index' => 'integer',
            'year' => 'integer',
            'release_date' => 'date',
            'runtime_ms' => 'integer',
            'metadata_locked_fields' => 'array',
            // V2 E import provenance.
            'season_number' => 'integer',
            'episode_number' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Library, $this> */
    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class, 'library_id');
    }

    /** @return BelongsTo<MediaItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<MediaItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<MediaEdition, $this> */
    public function editions(): HasMany
    {
        return $this->hasMany(MediaEdition::class, 'media_item_id');
    }
}
