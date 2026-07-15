<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A single sync-foundation "dry run" over a configured connector. A dry run reads
 * STORED discovery/health state only: it performs no network calls, imports no
 * media and touches no files. `summary` holds counts + issue codes — never a
 * secret, never a raw API payload.
 *
 * @property string $id
 * @property string $connector_instance_id
 * @property string $mode
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property array<string, mixed> $summary
 * @property string|null $error_message
 * @property string $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ConnectorSyncRun extends Model
{
    use HasUlids;

    protected $table = 'connector_sync_runs';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ConnectorInstance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(ConnectorInstance::class, 'connector_instance_id');
    }

    /** @return HasMany<ConnectorSyncRunLibrary, $this> */
    public function libraries(): HasMany
    {
        return $this->hasMany(ConnectorSyncRunLibrary::class, 'connector_sync_run_id');
    }
}
