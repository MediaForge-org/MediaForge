<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Import;

use App\Connectors\Sdk\Catalog\ExternalMediaKind;

/**
 * The immutable result of planning ONE external catalog item (V2 D).
 *
 * It describes a hypothetical target: a logical identity (kind + title + year +
 * season/episode + a hashed stable key), never a file path and never an executed
 * operation. Producing one of these creates no media item, no edition, no file and
 * touches nothing on disk or on a remote server.
 */
final readonly class PlannedImportItem
{
    /**
     * @param  list<ImportPlanReason>  $reasons
     */
    public function __construct(
        public ExternalMediaKind $kind,
        public ImportPlannedAction $action,
        public ImportPlanItemStatus $status,
        public string $targetTitle,
        public ?string $targetKey,
        public ?string $targetParentKey,
        public ?int $targetYear,
        public ?int $targetSeasonNumber,
        public ?int $targetEpisodeNumber,
        public int $confidence,
        public array $reasons,
    ) {}

    /** @return list<string> The stable reason codes, for storage and the UI. */
    public function reasonCodes(): array
    {
        return array_map(static fn (ImportPlanReason $reason): string => $reason->value, $this->reasons);
    }

    public function hasReason(ImportPlanReason $reason): bool
    {
        return in_array($reason, $this->reasons, true);
    }
}
