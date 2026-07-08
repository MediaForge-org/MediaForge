<?php

declare(strict_types=1);

namespace App\Core\Media;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $media_item_id
 * @property string $person_id
 * @property string $role
 */
class Credit extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'credits';

    protected $guarded = ['id'];

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    /** @return BelongsTo<MediaItem, $this> */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'media_item_id');
    }
}
