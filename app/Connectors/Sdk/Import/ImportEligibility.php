<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Import;

/**
 * The verdict on whether ONE plan line may be imported internally (V2 E).
 *
 * Either it is importable as a known kind, or it carries the exact skip action and
 * reason codes that explain the refusal. There is no third, silent option.
 */
final readonly class ImportEligibility
{
    /**
     * @param  list<ImportExecutionReason>  $reasons
     */
    private function __construct(
        public bool $importable,
        public ?ImportableMediaKind $kind,
        public ?ImportExecutionAction $skipAction,
        public array $reasons,
    ) {}

    public static function importable(ImportableMediaKind $kind): self
    {
        return new self(true, $kind, null, [ImportExecutionReason::ImportedFromPlan]);
    }

    /**
     * @param  list<ImportExecutionReason>  $reasons
     */
    public static function skip(ImportExecutionAction $action, array $reasons): self
    {
        return new self(false, null, $action, $reasons);
    }

    /** @return list<string> The stable reason codes, for storage and the UI. */
    public function reasonCodes(): array
    {
        return array_map(static fn (ImportExecutionReason $reason): string => $reason->value, $this->reasons);
    }
}
