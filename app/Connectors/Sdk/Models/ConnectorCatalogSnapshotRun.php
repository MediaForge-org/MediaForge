<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A single read-only catalog snapshot of one connector library (V2 A). A snapshot
 * READS external items and stores them as a read-only connector read-model: it
 * imports no media, creates no media_items/editions/files, and touches no files.
 * `summary` holds counts + sanitized notes — never a secret, never a raw payload.
 *
 * @property string $id
 * @property string $connector_instance_id
 * @property string|null $connector_library_id
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int $items_seen_count
 * @property int $items_stored_count
 * @property int $warnings_count
 * @property int $errors_count
 * @property array<string, mixed> $summary
 * @property string|null $error_message
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ConnectorCatalogSnapshotRun extends Model
{
    use HasUlids;

    protected $table = 'connector_catalog_snapshot_runs';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'items_seen_count' => 'integer',
            'items_stored_count' => 'integer',
            'warnings_count' => 'integer',
            'errors_count' => 'integer',
        ];
    }

    /** @return BelongsTo<ConnectorInstance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(ConnectorInstance::class, 'connector_instance_id');
    }

    /** @return BelongsTo<ConnectorLibrary, $this> */
    public function library(): BelongsTo
    {
        return $this->belongsTo(ConnectorLibrary::class, 'connector_library_id');
    }

    /** @return HasMany<ConnectorCatalogItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ConnectorCatalogItem::class, 'snapshot_run_id');
    }
}
