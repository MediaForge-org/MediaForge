<?php

declare(strict_types=1);

namespace App\Core\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * Records one audit entry for a business write. Implementations MUST run inside
 * the same transaction as the write (called from AuditableAction::transact) and
 * MUST mask secrets before persistence.
 */
interface AuditRecorder
{
    public function record(Model $subject, AuditChange $change, Actor $actor): void;
}
