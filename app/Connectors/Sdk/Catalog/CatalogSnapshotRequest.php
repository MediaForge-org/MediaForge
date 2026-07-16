<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Catalog;

/**
 * Immutable input to a connector's read-only catalog snapshot: the validated base
 * URL, the decrypted secret, the target library's external id/type and a hard item
 * limit. The secret lives only for the duration of the call and is never persisted
 * or logged from here.
 */
final readonly class CatalogSnapshotRequest
{
    public function __construct(
        public string $baseUrl,
        public ?string $secret,
        public string $libraryExternalId,
        public ?string $libraryType,
        public int $limit,
    ) {}
}
