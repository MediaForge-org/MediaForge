<?php

declare(strict_types=1);

namespace App\Core\WatchState;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only watch-state history (monthly-partitioned by occurred_at). Only
 * `recorded_at` is DB-managed; occurred_at is set explicitly by the writer.
 *
 * @property string $id
 * @property string $user_id
 * @property string $media_item_id
 * @property string $event_type
 * @property array<string, mixed> $context
 */
class WatchStateEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const CREATED_AT = 'recorded_at';

    protected $table = 'watch_state_events';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position_ms' => 'integer',
            'context' => 'array',
            'occurred_at' => 'datetime',
            'recorded_at' => 'datetime',
        ];
    }
}
