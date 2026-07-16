<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Catalog;

/**
 * Immutable, already-validated filter/sort/pagination input for browsing captured
 * external catalog items (V2 B). The controller builds this from the request using
 * strict allowlists (sort column, direction, status, kind) so the read model can
 * trust every field and never interpolates raw request input into a query.
 *
 * `connectorKey` is the registry key (not a resolved instance id) on purpose: a
 * registered connector that has no configured instance must scope the result to
 * NOTHING, whereas a null instance id would silently drop the filter and show every
 * connector's items.
 */
final readonly class CatalogItemQuery
{
    /** Columns a caller may sort by — mirrored by the read model's allowlist. */
    public const SORTS = ['title', 'last_seen_at', 'year', 'media_kind'];

    public const DIRECTIONS = ['asc', 'desc'];

    /** present = captured & currently present, missing = vanished, all = both. */
    public const STATUSES = ['present', 'missing', 'all'];

    public const PER_PAGE = 24;

    public function __construct(
        public ?string $search = null,
        public ?string $connectorKey = null,
        public ?string $libraryId = null,
        public ?string $kind = null,
        public string $status = 'present',
        public string $sort = 'title',
        public string $direction = 'asc',
        public int $page = 1,
        public int $perPage = self::PER_PAGE,
    ) {}
}
