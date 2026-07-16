<?php

declare(strict_types=1);

namespace App\Connectors\Jellyfin;

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

    /**
     * Discover libraries via `/Library/MediaFolders`: a lightweight, authenticated
     * endpoint that lists the top-level media folders (libraries) with their id,
     * name and collection type — without enumerating any media item.
     */
    public function discoverLibraries(LibraryDiscoveryRequest $request): LibraryDiscoveryResult
    {
        $url = BaseUrl::normalize($request->baseUrl).'/Library/MediaFolders';

        try {
            $response = $this->http->make()
                ->withHeaders(['X-Emby-Token' => $request->secret ?? ''])
                ->get($url);
        } catch (ConnectionException) {
            return LibraryDiscoveryResult::failure('Could not reach the Jellyfin server. Check the base URL and that the server is running.');
        }

        $status = $response->status();

        if (in_array($status, [401, 403], true)) {
            return LibraryDiscoveryResult::failure("Jellyfin rejected the API key (HTTP {$status}).", $status);
        }

        if (!$response->successful()) {
            return LibraryDiscoveryResult::failure("Jellyfin returned an unexpected response (HTTP {$status}).", $status);
        }

        $libraries = [];

        foreach ($this->itemsArray($response->json('Items')) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $externalId = $this->stringField($item['Id'] ?? null);
            $name = $this->stringField($item['Name'] ?? null);

            if ($externalId === null || $name === null) {
                continue;
            }

            $libraries[] = new DiscoveredLibrary(
                externalId: $externalId,
                name: $name,
                type: $this->stringField($item['CollectionType'] ?? null),
                path: $this->stringField($item['Path'] ?? null),
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
     * Read-only item snapshot via `/Items?ParentId={library}`: a bounded, paged,
     * authenticated listing of ONE page of a library's items with a small, fixed
     * field set. No media is fetched, downloaded or modified — this is a pure read.
     * `StartIndex`/`Limit` select the page (the caller advances the offset across
     * pages); `TotalRecordCount` reports the remote's full size so the caller can
     * page and detect truncation. Redirects are disabled and the timeout is short.
     */
    public function snapshotLibraryItems(CatalogSnapshotRequest $request): CatalogSnapshotResult
    {
        $url = BaseUrl::normalize($request->baseUrl).'/Items';

        try {
            $response = $this->http->make()
                ->withHeaders(['X-Emby-Token' => $request->secret ?? ''])
                ->get($url, [
                    'ParentId' => $request->libraryExternalId,
                    'Recursive' => 'true',
                    'Limit' => $request->limit,
                    'StartIndex' => $request->offset,
                    'EnableImages' => 'false',
                    'EnableUserData' => 'false',
                    'Fields' => 'OriginalTitle,SortName,ProductionYear,DateCreated',
                    'SortBy' => 'SortName',
                    'SortOrder' => 'Ascending',
                ]);
        } catch (ConnectionException) {
            return CatalogSnapshotResult::failure('Could not reach the Jellyfin server. Check the base URL and that the server is running.');
        }

        $status = $response->status();

        if (in_array($status, [401, 403], true)) {
            return CatalogSnapshotResult::failure("Jellyfin rejected the API key (HTTP {$status}).", $status);
        }

        if (!$response->successful()) {
            return CatalogSnapshotResult::failure("Jellyfin returned an unexpected response (HTTP {$status}).", $status);
        }

        $items = [];

        foreach ($this->itemsArray($response->json('Items')) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $externalId = $this->stringField($item['Id'] ?? null);
            $title = $this->stringField($item['Name'] ?? null);

            if ($externalId === null || $title === null) {
                continue;
            }

            $items[] = new SnapshotItem(
                externalId: $externalId,
                title: $title,
                kind: $this->mapKind($this->stringField($item['Type'] ?? null)),
                externalParentId: $this->stringField($item['SeriesId'] ?? null) ?? $this->stringField($item['ParentId'] ?? null),
                sortTitle: $this->stringField($item['SortName'] ?? null),
                originalTitle: $this->stringField($item['OriginalTitle'] ?? null),
                year: $this->intField($item['ProductionYear'] ?? null),
                indexNumber: $this->intField($item['IndexNumber'] ?? null),
                parentIndexNumber: $this->intField($item['ParentIndexNumber'] ?? null),
                runtimeSeconds: $this->ticksToSeconds($item['RunTimeTicks'] ?? null),
            );
        }

        $total = $this->intField($response->json('TotalRecordCount')) ?? count($items);
        $truncated = $total > count($items);

        return CatalogSnapshotResult::success(
            $items,
            'Captured '.count($items).' external '.(count($items) === 1 ? 'item' : 'items').'.',
            $truncated,
            $total,
            $status,
        );
    }

    private function mapKind(?string $type): ExternalMediaKind
    {
        return match (strtolower((string) $type)) {
            'movie' => ExternalMediaKind::Movie,
            'series' => ExternalMediaKind::Series,
            'season' => ExternalMediaKind::Season,
            'episode' => ExternalMediaKind::Episode,
            'audio', 'musicalbum', 'musicartist' => ExternalMediaKind::Music,
            'audiobook' => ExternalMediaKind::Audiobook,
            'book' => ExternalMediaKind::Book,
            'playlist' => ExternalMediaKind::Playlist,
            'folder', 'collectionfolder', 'boxset' => ExternalMediaKind::Folder,
            default => ExternalMediaKind::Unknown,
        };
    }

    private function ticksToSeconds(mixed $ticks): ?int
    {
        // Jellyfin RunTimeTicks are 100-nanosecond units.
        return is_int($ticks) && $ticks > 0 ? intdiv($ticks, 10_000_000) : null;
    }

    /** @return array<int, mixed> */
    private function itemsArray(mixed $items): array
    {
        return is_array($items) ? array_values($items) : [];
    }

    private function stringField(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function intField(mixed $value): ?int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : null);
    }
}
