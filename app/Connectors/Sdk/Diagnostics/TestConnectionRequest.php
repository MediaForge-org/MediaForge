<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Diagnostics;

/**
 * Immutable input to a connector's testConnection: the validated base URL and the
 * decrypted secret. The secret lives only for the duration of the call and is
 * never persisted or logged from here.
 */
final readonly class TestConnectionRequest
{
    public function __construct(
        public string $baseUrl,
        public ?string $secret,
    ) {}
}
