<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Http;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;

/**
 * Builds the hardened HTTP client every connection test uses: a short timeout so
 * a dead server cannot hang the request, redirects disabled (a probed server
 * must not bounce us elsewhere), and JSON accepted. Concrete connectors add their
 * own auth header and base URL on top.
 */
final class ConnectorHttpFactory
{
    private const CONNECT_TIMEOUT_SECONDS = 3;

    private const TIMEOUT_SECONDS = 5;

    public function __construct(private readonly HttpFactory $http) {}

    public function make(): PendingRequest
    {
        return $this->http
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::TIMEOUT_SECONDS)
            ->withoutRedirecting()
            ->acceptJson();
    }
}
