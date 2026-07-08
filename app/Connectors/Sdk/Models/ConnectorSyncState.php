<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-instance, per-stream, per-direction sync watermark. The runtime that
 * populates cursors/backoff is V2; the table is foundation.
 *
 * @property string $id
 * @property string $instance_id
 * @property string $stream
 * @property string $direction
 * @property array<string, mixed>|null $cursor
 * @property int $consecutive_failures
 */
class ConnectorSyncState extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'connector_sync_states';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cursor' => 'array',
            'stats' => 'array',
            'last_run_at' => 'datetime',
            'last_success_at' => 'datetime',
            'backoff_until' => 'datetime',
        ];
    }

    /** @return BelongsTo<ConnectorInstance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(ConnectorInstance::class, 'instance_id');
    }
}
