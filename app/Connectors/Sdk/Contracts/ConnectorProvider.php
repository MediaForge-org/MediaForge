<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Contracts;

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
}
