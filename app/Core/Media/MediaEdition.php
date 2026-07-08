<?php

declare(strict_types=1);

namespace App\Core\Media;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A concrete edition of a media item (cut, remaster, language, upscale, …).
 *
 * @property string $id
 * @property string $media_item_id
 * @property string $name
 * @property string $edition_kind
 * @property bool $is_primary
 */
class MediaEdition extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'media_editions';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    /** @return BelongsTo<MediaItem, $this> */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'media_item_id');
    }
}
