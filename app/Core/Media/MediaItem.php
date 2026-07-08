<?php

declare(strict_types=1);

namespace App\Core\Media;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Canonical catalog entry (typed, self-hierarchical). Not populated in V1 (no
 * ingest/scan yet); the model is part of the foundation contract.
 *
 * @property string $id
 * @property string|null $library_id
 * @property string $media_type
 * @property string|null $parent_id
 * @property string $title
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
