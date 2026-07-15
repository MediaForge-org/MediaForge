<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The per-library plan captured for a dry run: what a FUTURE sync WOULD consider
 * for this library. Inspect-only — no media items, no file operations. Holds a
 * denormalized name/external_id so the plan is readable even if the underlying
 * connector_library row is later removed.
 *
 * @property string $id
 * @property string $connector_sync_run_id
 * @property string|null $connector_library_id
 * @property string $external_id
 * @property string $name
 * @property string|null $type
 * @property string $status
 * @property string $planned_action
 * @property array<string, mixed> $summary
 */
class ConnectorSyncRunLibrary extends Model
{
    use HasUlids;

    protected $table = 'connector_sync_run_libraries';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'summary' => 'array',
        ];
    }

    /** @return BelongsTo<ConnectorSyncRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ConnectorSyncRun::class, 'connector_sync_run_id');
    }
}
