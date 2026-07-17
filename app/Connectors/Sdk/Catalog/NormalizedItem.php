<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Catalog;

/**
 * The computed normalization of one external catalog item (V2 C) — the pure result
 * of CatalogItemNormalizer, before it is persisted. Carries only interpreted display
 * fields plus a quality verdict; never a secret, a raw payload or a local path.
 */
final readonly class NormalizedItem
{
    /** @param list<NormalizationIssue> $issues */
    public function __construct(
        public ExternalMediaKind $kind,
        public string $title,
        public ?string $sortTitle,
        public ?string $originalTitle,
        public ?int $releaseYear,
        public ?int $seasonNumber,
        public ?int $episodeNumber,
        public ?string $parentTitle,
        public ?int $runtimeSeconds,
        public int $confidence,
        public NormalizationStatus $status,
        public array $issues,
    ) {}

    /** @return list<string> The stable issue codes, for storage and filtering. */
    public function issueCodes(): array
    {
        return array_map(static fn (NormalizationIssue $issue): string => $issue->value, $this->issues);
    }
}
