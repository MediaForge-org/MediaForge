<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Diagnostics;

/**
 * Immutable input to a connector's library discovery: the validated base URL and
 * the decrypted secret. The secret lives only for the duration of the call and is
 * never persisted or logged from here.
 */
final readonly class LibraryDiscoveryRequest
{
    public function __construct(
        public string $baseUrl,
        public ?string $secret,
    ) {}
}
