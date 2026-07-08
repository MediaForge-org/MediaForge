<?php

declare(strict_types=1);

namespace App\Core\Provider;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps a MediaForge entity to an external identity (ADR-0003). Polymorphic; no
 * FK to the target entity by design.
 *
 * @property string $id
 * @property string $entity_type
 * @property string $entity_id
 * @property string $provider
 * @property string $external_id
 * @property float $confidence
 * @property string $source
 */
class ProviderId extends Model
{
    use HasUlids;

    protected $table = 'provider_ids';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
