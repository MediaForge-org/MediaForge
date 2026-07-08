<?php

declare(strict_types=1);

namespace App\Core\Artifacts;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A file MediaForge generated (M4B, CUE, upscale, backup, …). Registered via the
 * RegisterArtifact action; `input_signature` is the idempotency anchor.
 *
 * @property string $id
 * @property string $artifact_type
 * @property string $source_type
 * @property string $source_id
 * @property string $path
 * @property int $size_bytes
 * @property string $status
 */
class Artifact extends Model
{
    use HasUlids;

    protected $table = 'artifacts';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'params' => 'array',
            'size_bytes' => 'integer',
        ];
    }
}
