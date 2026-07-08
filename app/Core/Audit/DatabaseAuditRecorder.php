<?php

declare(strict_types=1);

namespace App\Core\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

/**
 * Writes audit entries to the append-only `audit_log`. The secret denylist is
 * the last line of defence against logging credentials (security.md): any key
 * matching *token* / *secret* / *password* / *key* has its value masked,
 * recursively, in both `changes` and `context`.
 */
final class DatabaseAuditRecorder implements AuditRecorder
{
    private const DENY_PATTERNS = ['token', 'secret', 'password', 'api_key', 'apikey', 'authorization', 'credential'];

    private const MASK = '***';

    public function record(Model $subject, AuditChange $change, Actor $actor): void
    {
        $key = $subject->getKey();

        AuditLog::query()->create([
            'correlation_id' => $this->correlationId(),
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'actor_label' => $actor->label,
            'action' => $change->action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => is_scalar($key) ? (string) $key : '',
            'changes' => $this->mask($change->changes),
            'context' => $this->mask($change->context),
        ]);
    }

    private function correlationId(): ?string
    {
        $value = Context::get('correlation_id');

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function mask(array $data): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $masked[$key] = self::MASK;

                continue;
            }

            $masked[$key] = is_array($value) ? $this->mask($value) : $value;
        }

        return $masked;
    }

    private function isSensitive(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::DENY_PATTERNS as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
