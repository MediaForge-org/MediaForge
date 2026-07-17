<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Catalog;

/**
 * Quality verdict of a normalized external catalog item (V2 C). Derived purely from
 * the issues found, never from the network. The string values match the
 * connector_catalog_item_normalizations.status CHECK constraint.
 */
enum NormalizationStatus: string
{
    /** No issues worth surfacing. */
    case Clean = 'clean';

    /** Usable, but something is missing or odd. */
    case Warning = 'warning';

    /** Too little/too broken to interpret confidently — a human should look. */
    case NeedsReview = 'needs_review';

    /** A structural container (folder/playlist), not media — normalization N/A. */
    case Unsupported = 'unsupported';

    /** Confidence thresholds: >=90 clean, >=60 warning, below that needs review. */
    public static function fromConfidence(int $confidence): self
    {
        return match (true) {
            $confidence >= 90 => self::Clean,
            $confidence >= 60 => self::Warning,
            default => self::NeedsReview,
        };
    }

    /** Whether this verdict should surface as an attention item in the UI. */
    public function needsAttention(): bool
    {
        return $this === self::NeedsReview;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
