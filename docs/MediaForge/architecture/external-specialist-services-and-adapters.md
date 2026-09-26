# External Specialist Services and Adapter Architecture

Status: **binding target architecture**  
Date: **2026-09-27**  
ADR: `../adr/0028-external-specialist-services-via-adapters.md`

## Product model

MediaForge owns one unified product experience and one canonical MediaForge catalog.

Mature specialist media servers remain independently updateable upstream services.

```text
MediaForge clients
      |
MediaForge API
      |
domain services
      |
 +----+-------------------+
 |                        |
PostgreSQL          adapter registry
MediaForge          |    |    |
catalog             v    v    v
                 JF     ABS   Scene Tracker
```

## Hard boundaries

1. no direct upstream database access;
2. no migration of upstream internal DBs into MediaForge;
3. no upstream IDs as MediaForge primary identity;
4. no provider DTOs in the public MediaForge domain/UI;
5. no live fan-out requirement for ordinary browsing;
6. no media-byte storage in PostgreSQL;
7. no mandatory deep fork without a later evidence-backed ADR.

## Source-of-truth

MediaForge owns canonical cross-service identity and product state.

Upstreams own specialist runtime state.

See `postgresql-source-of-truth.md` and `ARCHITECTURE_REVIEW_2026-09-27.md`.

## Sync

Adapters synchronize bounded/incremental snapshots into PostgreSQL.

Page rendering reads local state.

Upstream outages degrade live capabilities without erasing synchronized metadata.

## Playback

MediaForge orchestrates.
Jellyfin/ABS handle specialist playback/runtime work.
PHP should not proxy large media bytes unnecessarily.

## Compatibility

Every adapter tracks detected version, supported range, capabilities and health.

Major upstream releases require compatibility tests.

## Deployment

Connect existing services or optionally start compatible Jellyfin/ABS images as separate Compose
containers.

Scene Tracker remains a separate product/service.

## Current code

The existing `app/Connectors/Sdk` plus Jellyfin/Audiobookshelf connectors are the current implementation
foundation and should be evolved rather than discarded.
