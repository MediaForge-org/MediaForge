<?php

declare(strict_types=1);

namespace App\Connectors\Jellyfin;

use App\Connectors\Sdk\Contracts\ConnectorProvider;
use App\Connectors\Sdk\Diagnostics\TestConnectionRequest;
use App\Connectors\Sdk\Diagnostics\TestConnectionResult;
use App\Connectors\Sdk\Http\ConnectorHttpFactory;
use App\Connectors\Sdk\Support\BaseUrl;
use Illuminate\Http\Client\ConnectionException;

/**
 * Jellyfin connection test. Probes the authenticated `/System/Info` endpoint with
 * the API key as `X-Emby-Token` (Jellyfin's Emby-compatible header): this both
 * confirms reachability and validates the key, and returns the server name and
 * version without touching any media library. No sync, no scan.
 */
final class JellyfinConnector implements ConnectorProvider
{
    public function __construct(private readonly ConnectorHttpFactory $http) {}

    public function key(): string
    {
        return 'jellyfin';
    }

    public function label(): string
    {
        return 'Jellyfin';
    }

    public function testConnection(TestConnectionRequest $request): TestConnectionResult
    {
        $url = BaseUrl::normalize($request->baseUrl).'/System/Info';

        try {
            $response = $this->http->make()
                ->withHeaders(['X-Emby-Token' => $request->secret ?? ''])
                ->get($url);
        } catch (ConnectionException) {
            // Message intentionally dropped: it can echo the host but never the key.
            return TestConnectionResult::unreachable('Could not reach the Jellyfin server. Check the base URL and that the server is running.');
        }

        $status = $response->status();

        if ($response->successful()) {
            $name = $this->stringField($response->json('ServerName'));
            $version = $this->stringField($response->json('Version'));
            $identity = trim(($name ?? 'Jellyfin').' '.($version ?? ''));

            return TestConnectionResult::healthy("Connected to {$identity}.", $status, $name, $version);
        }

        if (in_array($status, [401, 403], true)) {
            return TestConnectionResult::authFailed("Jellyfin rejected the API key (HTTP {$status}).", $status);
        }

        return TestConnectionResult::degraded("Jellyfin returned an unexpected response (HTTP {$status}).", $status);
    }

    private function stringField(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
