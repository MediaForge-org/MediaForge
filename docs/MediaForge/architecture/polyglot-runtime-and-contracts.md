# Runtime, APIs and Adapter Contracts

Status: **binding target architecture**
Updated: **2026-09-27**

## Target runtime

```text
React / TypeScript
        |
        | MediaForge API v1
        v
Laravel / PHP
   |           \
   |            \ versioned adapters
   v             +----------------+----------------+
PostgreSQL       |                |                |
Redis            v                v                v
             Jellyfin       Audiobookshelf    Scene Tracker
             external          external          external

Optional MediaForge-native services:
  Rust MediaTools
  Python AI
```

The old requirement for internal C# Jellyfin-derived, Go Stash-derived and Node Audiobookshelf-derived
runtime trees is superseded.

## Browser -> MediaForge

The browser talks to MediaForge only.

Provider APIs are never a frontend contract.

## MediaForge -> external services

Adapters normalize:

- identity/mappings;
- libraries/catalog;
- version/capabilities;
- health;
- artwork references;
- playback/session operations where supported;
- progress;
- refresh/events/sync.

A provider capability may be absent without changing the MediaForge domain model.

## Catalog vs live runtime

Normal browsing reads MediaForge PostgreSQL.

Live upstream calls are reserved for actions that actually require live service state, such as:

- starting playback;
- testing a connection;
- explicit refresh;
- current session operations.

Do not fan out to every upstream on page render.

## Jobs/events

MediaForge jobs operate on MediaForge state and adapter operations.

External events/webhooks are treated as triggers/evidence and reconciled against upstream state;
they are not trusted as the sole source of canonical identity.

Redis remains technical queue/cache/realtime infrastructure.

## Playback

Video:

```text
MediaForge UI
 -> MediaForge playback endpoint
 -> JellyfinAdapter
 -> Jellyfin session/stream decision
 -> client receives usable stream/session information
```

Laravel must not become a large media-byte proxy.

Audiobooks/podcasts follow the same principle with Audiobookshelf.

## Progress

Distinguish:

- upstream raw runtime/listening/watch state;
- MediaForge normalized cross-service/user-facing projection.

Bidirectional write-back requires an explicit conflict policy.
Never create accidental multi-master semantics.

## Rust/Python

Rust and Python remain valid for MediaForge-owned capabilities:

- probing/hashing/disc/timeline/FFmpeg hotpaths;
- ML inference/embeddings/analysis.

They communicate through contracts and do not replace specialist upstream server APIs.

## Contract tests

Every adapter should have:

- recorded/synthetic fixtures;
- supported-version matrix;
- auth/health test;
- pagination/catalog test;
- failure normalization test;
- capability contract test;
- representative playback test when applicable.

## Failure boundaries

If Jellyfin is down:
- MediaForge catalog remains browsable;
- video playback is unavailable/degraded.

If Audiobookshelf is down:
- synchronized books/audiobooks remain browsable;
- specialist playback is unavailable/degraded.

If Scene Tracker is down:
- synchronized metadata remains available;
- enrichment/matching waits.

Provider failure must not become full MediaForge failure.
