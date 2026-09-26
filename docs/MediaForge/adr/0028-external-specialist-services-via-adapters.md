# ADR-0028 — External specialist media services through versioned adapters

**Status:** Accepted target architecture  
**Date:** 2026-09-27  
**Supersedes:** ADR-0025's Jellyfin/Stash/Audiobookshelf source-import/fork decision and the
fork-specific runtime assumptions in ADR-0014.

## Context

MediaForge already has working Jellyfin and Audiobookshelf connectors, a local PostgreSQL catalog,
normalization/import/review foundations, and Docker topologies that keep specialist servers in
separate containers.

The previous target architecture planned to import Jellyfin, Stash and Audiobookshelf source trees
into the MediaForge monorepo and progressively transform them into internal engines. That approach
would substantially increase source-sync, licensing, build, compatibility and release complexity.

The product does not need deep forks merely to provide one MediaForge UI and canonical catalog.

## Decision

MediaForge integrates mature specialist media servers through **versioned public API adapters** by
default.

MediaForge does not deep-fork Jellyfin, Audiobookshelf, Stash or similar servers unless a later ADR
demonstrates a hard requirement that cannot reasonably be solved through supported public APIs.

### MediaForge owns

- unified product/UI;
- public MediaForge API;
- canonical MediaForge IDs;
- PostgreSQL catalog and cross-service mappings;
- metadata provenance and manual locks;
- collections/preferences/search;
- review/audit;
- acquisition orchestration and import lineage;
- integration configuration, compatibility and health;
- optional MediaForge-native Rust/Python services.

### Specialist services own

Jellyfin:
- video/music playback where used;
- streaming/transcoding;
- subtitles/audio tracks;
- playback sessions/device profiles;
- its internal database and technical runtime state.

Audiobookshelf:
- audiobook/podcast playback;
- chapter/audio-specific runtime functionality;
- its internal database and technical runtime state.

Scene Tracker:
- separate scene metadata/community product;
- performers/studios/scenes/tags/source provenance/community data;
- its own PostgreSQL database.

### Database boundary

MediaForge PostgreSQL is canonical only for **MediaForge-owned state**.

MediaForge may synchronize/mirror normalized external facts but:

- never migrates an upstream internal database into MediaForge;
- never directly queries or writes an upstream internal database;
- never uses upstream IDs as MediaForge primary identity;
- never stores media bytes/transcodes/HLS segments as PostgreSQL blobs.

### Deployment

Two supported styles:

1. connect existing Jellyfin/Audiobookshelf installations;
2. optionally start compatible upstream images as separate Compose containers.

Bundled services remain independent containers with independent persistence.
No Docker-in-Docker and no giant all-services container.

Scene Tracker normally remains separately deployed because it is a distinct product.

### Adapter boundary

UI -> MediaForge API -> domain services -> adapter -> upstream public API.

Provider DTOs and provider-specific branches must not spread through the frontend/domain.
Capabilities are negotiated explicitly.

### Failure behavior

The local normalized catalog remains browsable when an upstream service is unavailable.
Only capabilities that require that service (for example playback) degrade.

### Compatibility

Adapters track:

- detected upstream version;
- supported version range;
- capability set;
- health/compatibility status.

Major upstream releases are gated by contract/integration tests.

## Consequences

### Positive

- far less fork maintenance;
- upstream updates remain independent;
- less licensing/source-history complexity;
- smaller monorepo and build matrix;
- current connector/catalog work becomes the foundation rather than temporary code;
- easier support for additional external systems.

### Costs

- MediaForge depends on supported upstream APIs;
- capability gaps may require upstream contributions or narrowly scoped compatibility work;
- offline functionality requires deliberate local snapshot/mirror design;
- write-back/progress ownership must be explicit to avoid multi-master conflicts.

## Non-goals

This ADR does not:

- remove Work/Edition/File;
- remove PostgreSQL;
- remove advanced acquisition, Disc, AI, Rust MediaTools or plugin plans;
- merge Scene Tracker into MediaForge;
- forbid all future forks forever.

A future fork requires a new evidence-backed ADR.
