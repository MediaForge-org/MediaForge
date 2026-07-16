<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Catalog;

/**
 * Outcome of a read-only catalog snapshot. `detail` is a human-readable,
 * secret-free message safe to store and show. On failure `items` is empty and the
 * caller MUST NOT wipe previously captured items. `supported` is false when the
 * provider cannot snapshot items yet (capability fallback). `truncated` is true
 * when the library held more items than the requested limit; `totalSeen` is the
 * remote's reported total when known.
 */
final readonly class CatalogSnapshotResult
{
    /** @param list<SnapshotItem> $items */
    public function __construct(
        public bool $ok,
        public bool $supported,
        public string $detail,
        public array $items = [],
        public bool $truncated = false,
        public ?int $totalSeen = null,
        public ?int $httpStatus = null,
    ) {}

    /** @param list<SnapshotItem> $items */
    public static function success(array $items, string $detail, bool $truncated = false, ?int $totalSeen = null, ?int $httpStatus = null): self
    {
        return new self(true, true, $detail, $items, $truncated, $totalSeen, $httpStatus);
    }

    public static function failure(string $detail, ?int $httpStatus = null): self
    {
        return new self(false, true, $detail, [], false, null, $httpStatus);
    }

    /** The provider cannot snapshot items yet — modelled explicitly, never faked. */
    public static function unsupported(string $detail): self
    {
        return new self(false, false, $detail);
    }
}
