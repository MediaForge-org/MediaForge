<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Catalog;

/**
 * One attention item found by a read-only catalog snapshot. `code` is a stable
 * machine key, `message` is human-readable and `action` is the recommended next
 * step. Never carries a secret. `blocking` marks a hard prerequisite (vs a softer
 * warning) and drives the raised review task's priority.
 */
final readonly class CatalogIssue
{
    public function __construct(
        public string $code,
        public string $message,
        public string $action,
        public bool $blocking,
    ) {}

    /** @return array{code: string, message: string, action: string, blocking: bool} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'action' => $this->action,
            'blocking' => $this->blocking,
        ];
    }
}
