<?php

declare(strict_types=1);

namespace App\Core\Review;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A "automation is unsure, a human decides" task. Resolved by the owning
 * module's action, never by a generic resolver.
 *
 * @property string $id
 * @property string $task_type
 * @property string $subject_type
 * @property string $subject_id
 * @property string $status
 * @property string $priority
 * @property array<string, mixed> $evidence
 * @property array<string, mixed>|null $resolution
 * @property string $created_by
 * @property string|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ReviewTask extends Model
{
    use HasUlids;

    protected $table = 'review_tasks';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'resolution' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
