<?php

declare(strict_types=1);

namespace App\Core\Settings;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single runtime setting override (key → JSON value). The table stores only
 * deltas from code defaults; reads go through SettingsRepository, writes through
 * the UpdateSetting action.
 *
 * @property string $key
 * @property mixed $value
 * @property string|null $updated_by
 */
class Setting extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $table = 'settings';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value' => 'array',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
