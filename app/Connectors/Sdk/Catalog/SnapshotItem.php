<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Catalog;

/**
 * One normalized external item from a snapshot response. Read-only display fields
 * only: a stable external id, a title, a classified kind and a few optional
 * numbers. `metadata` carries only small, non-sensitive extras — never tokens, raw
 * API payloads or full local file paths.
 */
final readonly class SnapshotItem
{
    /** @param array<string, scalar> $metadata */
    public function __construct(
        public string $externalId,
        public string $title,
        public ExternalMediaKind $kind,
        public ?string $externalParentId = null,
        public ?string $sortTitle = null,
        public ?string $originalTitle = null,
        public ?int $year = null,
        public ?int $indexNumber = null,
        public ?int $parentIndexNumber = null,
        public ?int $runtimeSeconds = null,
        public ?string $externalUpdatedAt = null,
        public array $metadata = [],
    ) {}
}
