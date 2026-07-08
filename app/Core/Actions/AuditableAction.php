<?php

declare(strict_types=1);

namespace App\Core\Actions;

use App\Core\Audit\Actor;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use Closure;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for every state-changing use case (architecture/overview.md Rule 6).
 * Actions are the ONLY place business state is written, and the audit entry is
 * written atomically in the same transaction as the change.
 */
abstract class AuditableAction
{
    public function __construct(
        protected readonly AuditRecorder $audit,
        protected readonly DatabaseManager $db,
    ) {}

    /**
     * Run $work in a transaction and record the audit entry atomically.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    protected function transact(Model $subject, AuditChange $change, Closure $work): mixed
    {
        return $this->db->transaction(function () use ($subject, $change, $work) {
            $result = $work();
            $this->audit->record($subject, $change, Actor::current());

            return $result;
        });
    }
}
