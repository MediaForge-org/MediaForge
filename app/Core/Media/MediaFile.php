<?php

declare(strict_types=1);

namespace App\Core\Media;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inventory of physical files (table `files`). Never soft-deleted — carries a
 * lifecycle status (present|missing|removed) instead.
 *
 * @property string $id
 * @property string $library_id
 * @property string $path
 * @property int $size_bytes
 * @property string $status
 */
class MediaFile extends Model
{
    use HasUlids;

    protected $table = 'files';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_container_dir' => 'boolean',
            'size_bytes' => 'integer',
            'mtime' => 'datetime',
            'missing_since' => 'datetime',
            'candidate_confidence' => 'float',
        ];
    }

    /** @return BelongsTo<Library, $this> */
    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class, 'library_id');
    }
}
