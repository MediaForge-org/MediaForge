<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Catalog;

/**
 * Immutable input to a connector's read-only catalog snapshot of ONE remote page:
 * the validated base URL, the decrypted secret, the target library's external
 * id/type, the per-page item `limit` and the `offset` of the first item to read.
 * The caller (RunConnectorCatalogSnapshot) drives multi-page reads by advancing the
 * offset; the provider reads exactly one bounded page. The secret lives only for
 * the duration of the call and is never persisted or logged from here.
 */
final readonly class CatalogSnapshotRequest
{
    public function __construct(
        public string $baseUrl,
        public ?string $secret,
        public string $libraryExternalId,
        public ?string $libraryType,
        public int $limit,
        public int $offset = 0,
    ) {}
}
