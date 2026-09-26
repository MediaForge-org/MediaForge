# Adapter and Capability Contracts

Status: **binding target architecture**
Updated: **2026-09-27**

The filename is retained for compatibility with existing references, but the old deep-fork
"engine contract" assumption is superseded.

## 1. Goal

Decouple MediaForge UI/domain/catalog from concrete specialist services.

A Jellyfin/ABS/Scene-Tracker version change should normally affect the adapter/compatibility layer,
not MediaForge IDs or public routes.

## 2. Base adapter contract

```text
key()
label()
version()
supportedVersionRange()
capabilities()
health()
diagnostics()
```

## 3. Catalog capability

```text
listLibraries()
listItems(cursor, filters)
getItem(externalId)
refresh()
```

Results are normalized before becoming MediaForge canonical state.

## 4. Playback capability

Where supported:

```text
preparePlayback(mediaRef, deviceProfile, preferences)
startSession()
stopSession()
listTracks()
selectTrack()
```

Provider-specific transport/session details remain inside the adapter DTO/translation layer.

## 5. Progress capability

```text
readProgress()
writeProgress()  # optional; only with explicit ownership/conflict policy
```

MediaForge keeps cross-service identity separate from raw provider runtime state.

## 6. Artwork capability

```text
getArtworkReference()
```

Prefer upstream URLs/references or controlled cache/artifact policies rather than DB blobs.

## 7. Event/sync capability

```text
pollChanges(cursor)
handleWebhook(event)
refreshItem(externalId)
```

Not every provider supports all forms.

Events trigger reconciliation; they do not bypass normalization/provenance rules.

## 8. Download client capability

```text
health()
add()
pause()
resume()
cancel()
remove()
status()
history()
listFiles()
```

NZBGet/qBittorrent implement normalized download capabilities.

## 9. MediaTools capability

Rust MediaTools remains a MediaForge-native contract, not an upstream-server adapter:

```text
probeFile()
analyzeDiscStructure()
generateSidecar()
splitAudioChapters()
```

## 10. Capability-first UI

UI asks MediaForge for capabilities.

Forbidden pattern:

```text
if provider == jellyfin ...
```

Normal product pages operate on MediaForge DTOs.

## 11. IDs

Provider IDs remain external mappings.

Core foreign keys reference MediaForge IDs.

## 12. Errors

Normalize provider errors:

```text
INTEGRATION_UNAVAILABLE
CAPABILITY_NOT_SUPPORTED
AUTH_FAILED
RATE_LIMITED
INCOMPATIBLE_VERSION
UPSTREAM_RESPONSE_INVALID
PLAYBACK_PREPARATION_FAILED
```

Diagnostic details must be sanitized.

## 13. Contract versioning

Breaking adapter/public DTO changes require contract versioning/migration.

Supported upstream version ranges are tracked separately from MediaForge API versions.

## 14. Tests

Required per adapter/capability:

- fixtures;
- auth/health;
- version detection;
- pagination;
- response normalization;
- failure mapping;
- compatibility range;
- representative playback/session smoke tests where applicable.
