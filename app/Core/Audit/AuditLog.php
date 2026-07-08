<?php

declare(strict_types=1);

namespace App\Core\Audit;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit entry. Written only by the AuditRecorder inside an
 * AuditableAction's transaction. Never updated or deleted.
 *
 * @property string $id
 * @property string|null $correlation_id
 * @property string $actor_type
 * @property string|null $actor_id
 * @property string $actor_label
 * @property string $action
 * @property string $subject_type
 * @property string $subject_id
 * @property array<string, mixed> $changes
 * @property array<string, mixed> $context
 */
class AuditLog extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'audit_log';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
