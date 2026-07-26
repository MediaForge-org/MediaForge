<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Import;

/**
 * What an import dry run was asked to look at (V2 D). The string values match the
 * media_import_plans.scope_type CHECK constraint.
 */
enum ImportPlanScope: string
{
    /** Every configured connector's captured catalog. */
    case All = 'all';

    /** One connector instance. */
    case Connector = 'connector';

    /** One discovered library of one connector instance. */
    case Library = 'library';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $scope): string => $scope->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::All => 'All connectors',
            self::Connector => 'One connector',
            self::Library => 'One library',
        };
    }
}
