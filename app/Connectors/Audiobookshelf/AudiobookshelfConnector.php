<?php

declare(strict_types=1);

namespace App\Connectors\Audiobookshelf;

use App\Connectors\Sdk\Catalog\CatalogSnapshotRequest;
use App\Connectors\Sdk\Catalog\CatalogSnapshotResult;
use App\Connectors\Sdk\Catalog\ExternalMediaKind;
use App\Connectors\Sdk\Catalog\SnapshotItem;
use App\Connectors\Sdk\Contracts\ConnectorProvider;
use App\Connectors\Sdk\Diagnostics\DiscoveredLibrary;
use App\Connectors\Sdk\Diagnostics\LibraryDiscoveryRequest;
use App\Connectors\Sdk\Diagnostics\LibraryDiscoveryResult;
use App\Connectors\Sdk\Diagnostics\TestConnectionRequest;
use App\Connectors\Sdk\Diagnostics\TestConnectionResult;
use App\Connectors\Sdk\Http\ConnectorHttpFactory;
use App\Connectors\Sdk\Support\BaseUrl;
use Illuminate\Http\Client\ConnectionException;

/**
 * Audiobookshelf connection test. Probes the authenticated `/api/me` endpoint with
 * the API token as a Bearer credential: this confirms reachability and validates
 * the token, returning the signed-in username without listing or scanning any
 * library. No sync, no scan.
 */
final class AudiobookshelfConnector implements ConnectorProvider
{
    public function __construct(private readonly ConnectorHttpFactory $http) {}

    public function key(): string
    {
        return 'audiobookshelf';
    }

    public function label(): string
    {
        return 'Audiobookshelf';
    }

    public function testConnection(TestConnectionRequest $request): TestConnectionResult
    {
        $url = BaseUrl::normalize($request->baseUrl).'/api/me';

        try {
            $response = $this->http->make()
                ->withToken($request->secret ?? '')
                ->get($url);
        } catch (ConnectionException) {
            return TestConnectionResult::unreachable('Could not reach the Audiobookshelf server. Check the base URL and that the server is running.');
        }

        $status = $response->status();

        if ($response->successful()) {
            $username = $this->stringField($response->json('username'));
            $detail = $username !== null
                ? "Connected to Audiobookshelf as {$username}."
                : 'Connected to Audiobookshelf.';

            return TestConnectionResult::healthy($detail, $status, 'Audiobookshelf');
        }

        if (in_array($status, [401, 403], true)) {
            return TestConnectionResult::authFailed("Audiobookshelf rejected the API token (HTTP {$status}).", $status);
        }

        return TestConnectionResult::degraded("Audiobookshelf returned an unexpected response (HTTP {$status}).", $status);
    }

    /**
     * Discover libraries via `/api/libraries`: the authenticated endpoint that
     * lists the configured libraries (id, name, mediaType, folders) without
     * fetching any book or podcast item.
     */
    public function discoverLibraries(LibraryDiscoveryRequest $request): LibraryDiscoveryResult
    {
        $url = BaseUrl::normalize($request->baseUrl).'/api/libraries';

        try {
            $response = $this->http->make()
                ->withToken($request->secret ?? '')
                ->get($url);
        } catch (ConnectionException) {
            return LibraryDiscoveryResult::failure('Could not reach the Audiobookshelf server. Check the base URL and that the server is running.');
        }

        $status = $response->status();

        if (in_array($status, [401, 403], true)) {
            return LibraryDiscoveryResult::failure("Audiobookshelf rejected the API token (HTTP {$status}).", $status);
        }

        if (!$response->successful()) {
            return LibraryDiscoveryResult::failure("Audiobookshelf returned an unexpected response (HTTP {$status}).", $status);
        }

        $libraries = [];

        foreach ($this->itemsArray($response->json('libraries')) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $externalId = $this->stringField($item['id'] ?? null);
            $name = $this->stringField($item['name'] ?? null);

            if ($externalId === null || $name === null) {
                continue;
            }

            $libraries[] = new DiscoveredLibrary(
                externalId: $externalId,
                name: $name,
                type: $this->stringField($item['mediaType'] ?? null),
                path: $this->firstFolderPath($item['folders'] ?? null),
            );
        }

        return LibraryDiscoveryResult::success(
            $libraries,
            'Discovered '.count($libraries).' '.(count($libraries) === 1 ? 'library' : 'libraries').'.',
            $status,
        );
    }

    public function supportsCatalogSnapshot(): bool
    {
        return true;
    }

    /**
     * Read-only item snapshot via `/api/libraries/{id}/items`: the authenticated,
     * paged endpoint that lists a library's items (id + minimal metadata) without
     * downloading or modifying any audio file. `limit` caps the rows; the response
     * `total` tells us whether the library held more (→ truncated). Redirects are
     * disabled and the timeout is short.
     */
    public function snapshotLibraryItems(CatalogSnapshotRequest $request): CatalogSnapshotResult
    {
        $url = BaseUrl::normalize($request->baseUrl).'/api/libraries/'.rawurlencode($request->libraryExternalId).'/items';

        try {
            $response = $this->http->make()
                ->withToken($request->secret ?? '')
                ->get($url, [
                    'limit' => $request->limit,
                    'page' => 0,
                    'sort' => 'media.metadata.title',
                    'minified' => 1,
                ]);
        } catch (ConnectionException) {
            return CatalogSnapshotResult::failure('Could not reach the Audiobookshelf server. Check the base URL and that the server is running.');
        }

        $status = $response->status();

        if (in_array($status, [401, 403], true)) {
            return CatalogSnapshotResult::failure("Audiobookshelf rejected the API token (HTTP {$status}).", $status);
        }

        if (!$response->successful()) {
            return CatalogSnapshotResult::failure("Audiobookshelf returned an unexpected response (HTTP {$status}).", $status);
        }

        $defaultKind = $request->libraryType === 'podcast' ? ExternalMediaKind::Podcast : ExternalMediaKind::Audiobook;
        $items = [];

        foreach ($this->itemsArray($response->json('results')) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $externalId = $this->stringField($item['id'] ?? null);
            $media = is_array($item['media'] ?? null) ? $item['media'] : [];
            $metadata = is_array($media['metadata'] ?? null) ? $media['metadata'] : [];
            $title = $this->stringField($metadata['title'] ?? null);

            if ($externalId === null || $title === null) {
                continue;
            }

            $items[] = new SnapshotItem(
                externalId: $externalId,
                title: $title,
                kind: $this->mapKind($this->stringField($item['mediaType'] ?? null), $defaultKind),
                sortTitle: $this->stringField($metadata['titleIgnorePrefix'] ?? null),
                year: $this->intField($metadata['publishedYear'] ?? null),
                runtimeSeconds: $this->durationSeconds($media['duration'] ?? null),
            );
        }

        $total = $this->intField($response->json('total')) ?? count($items);
        $truncated = $total > count($items);

        return CatalogSnapshotResult::success(
            $items,
            'Captured '.count($items).' external '.(count($items) === 1 ? 'item' : 'items').'.',
            $truncated,
            $total,
            $status,
        );
    }

    private function mapKind(?string $mediaType, ExternalMediaKind $default): ExternalMediaKind
    {
        return match (strtolower((string) $mediaType)) {
            'podcast' => ExternalMediaKind::Podcast,
            'book' => ExternalMediaKind::Audiobook,
            '' => $default,
            default => $default,
        };
    }

    private function durationSeconds(mixed $duration): ?int
    {
        return is_numeric($duration) && (float) $duration > 0 ? (int) round((float) $duration) : null;
    }

    private function intField(mixed $value): ?int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : null);
    }

    /** @return array<int, mixed> */
    private function itemsArray(mixed $items): array
    {
        return is_array($items) ? array_values($items) : [];
    }

    private function firstFolderPath(mixed $folders): ?string
    {
        if (!is_array($folders)) {
            return null;
        }

        foreach ($folders as $folder) {
            if (is_array($folder)) {
                $path = $this->stringField($folder['fullPath'] ?? null);

                if ($path !== null) {
                    return $path;
                }
            }
        }

        return null;
    }

    private function stringField(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
