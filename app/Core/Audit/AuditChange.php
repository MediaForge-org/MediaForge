<?php

declare(strict_types=1);

namespace App\Core\Audit;

/**
 * Describes a single audited change: the action verb plus the (optional) field
 * diff and context. Secrets are masked by the recorder before persistence.
 */
final readonly class AuditChange
{
    /**
     * @param  array<string, mixed>  $changes  field => value, or field => [old, new]
     * @param  array<string, mixed>  $context  extra diagnostic context
     */
    public function __construct(
        public string $action,
        public array $changes = [],
        public array $context = [],
    ) {}
}
