<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Sync;

/**
 * One attention item found by a dry run. `code` is a stable machine key, `message`
 * is human-readable and `action` is the recommended next step (e.g. "select
 * libraries"). Never carries a secret. `blocking` marks a hard prerequisite (vs a
 * softer warning) and drives the raised review task's priority.
 */
final readonly class SyncIssue
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
