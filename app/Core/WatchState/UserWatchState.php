<?php

declare(strict_types=1);

namespace App\Core\WatchState;

use App\Core\Media\MediaItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Current per-user watch state for a consumable item. Written only by the
 * watch-state Core actions (RecordPlaybackProgress, MarkWatched, …).
 *
 * @property string $id
 * @property string $user_id
 * @property string $media_item_id
 * @property string $status
 * @property int|null $position_ms
 * @property int $play_count
 */
class UserWatchState extends Model
{
    use HasUlids;

    protected $table = 'user_watch_states';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position_ms' => 'integer',
            'duration_ms' => 'integer',
            'play_count' => 'integer',
            'first_played_at' => 'datetime',
            'last_played_at' => 'datetime',
            'watched_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<MediaItem, $this> */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'media_item_id');
    }
}
