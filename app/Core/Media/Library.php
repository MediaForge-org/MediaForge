<?php

declare(strict_types=1);

namespace App\Core\Media;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A monitored root path with a media-kind expectation and scan configuration.
 *
 * @property string $id
 * @property string $name
 * @property string $root_path
 * @property string $media_kind
 * @property bool $scan_enabled
 * @property int $scan_interval_min
 */
class Library extends Model
{
    use HasUlids;

    protected $table = 'libraries';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scan_enabled' => 'boolean',
            'settings' => 'array',
            'last_scan_started_at' => 'datetime',
            'last_scan_completed_at' => 'datetime',
        ];
    }

    /** @return HasMany<MediaFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(MediaFile::class, 'library_id');
    }

    /** @return HasMany<MediaItem, $this> */
    public function mediaItems(): HasMany
    {
        return $this->hasMany(MediaItem::class, 'library_id');
    }
}
