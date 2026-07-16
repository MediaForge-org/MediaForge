<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Contracts;

use App\Connectors\Sdk\Catalog\CatalogSnapshotRequest;
use App\Connectors\Sdk\Catalog\CatalogSnapshotResult;
use App\Connectors\Sdk\Diagnostics\LibraryDiscoveryRequest;
use App\Connectors\Sdk\Diagnostics\LibraryDiscoveryResult;
use App\Connectors\Sdk\Diagnostics\TestConnectionRequest;
use App\Connectors\Sdk\Diagnostics\TestConnectionResult;

/**
 * A connector type (Jellyfin, Audiobookshelf). Concrete implementations live in
 * their own namespace and register themselves with the ConnectorRegistry. The SDK
 * only ever depends on this contract, never on a concrete connector.
 */
interface ConnectorProvider
{
    /** Stable key, e.g. "jellyfin". Matches connector_instances.connector_key. */
    public function key(): string;

    /** Human label, e.g. "Jellyfin". */
    public function label(): string;

    /**
     * Probe the server with a short timeout. MUST NOT throw for network/HTTP
     * failures — it maps them to a TestConnectionResult with a sanitized message
     * and never leaks the secret.
     */
    public function testConnection(TestConnectionRequest $request): TestConnectionResult;

    /**
     * Discover the libraries the server exposes (library-level only — no media
     * items). Like testConnection it MUST NOT throw for network/HTTP failures; it
     * returns a LibraryDiscoveryResult with a sanitized message and never leaks
     * the secret. Uses a short timeout with redirects disabled.
     */
    public function discoverLibraries(LibraryDiscoveryRequest $request): LibraryDiscoveryResult;

    /**
     * Whether this provider can take a read-only item snapshot of a library yet
     * (V2 A capability flag). When false, the snapshot action records an explicit
     * "not supported" run and raises a review task instead of calling the network.
     */
    public function supportsCatalogSnapshot(): bool;

    /**
     * Read a bounded, read-only list of external items from one library. This is a
     * pure READ — it imports no media, creates no local records and touches no
     * files. Like the other probes it MUST NOT throw for network/HTTP failures; it
     * returns a CatalogSnapshotResult with a sanitized message and never leaks the
     * secret. Uses a short timeout with redirects disabled and honours the request
     * limit (setting `truncated` when the library holds more items).
     */
    public function snapshotLibraryItems(CatalogSnapshotRequest $request): CatalogSnapshotResult;
}
