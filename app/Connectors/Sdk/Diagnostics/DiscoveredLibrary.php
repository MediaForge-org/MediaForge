<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Diagnostics;

/**
 * One normalized library from a discovery response. Library-level metadata only:
 * a stable external id, a display name, an optional type and path. `metadata`
 * carries only small, non-sensitive extras — never tokens or raw API payloads.
 */
final readonly class DiscoveredLibrary
{
    /** @param array<string, scalar> $metadata */
    public function __construct(
        public string $externalId,
        public string $name,
        public ?string $type = null,
        public ?string $path = null,
        public array $metadata = [],
    ) {}
}
