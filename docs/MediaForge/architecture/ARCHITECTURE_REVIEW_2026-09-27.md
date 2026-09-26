# MediaForge Architecture Review — External Specialist Services / Adapter Model

Status: **review output; documentation-only architecture change**
Date: **2026-09-27**
Repository evidence reviewed against GitHub `main` at commit `9c3d6a6cab4787604d2adbfc93f6c5812a70d815`.

> This review does not claim to include uncommitted local working-tree changes.
> `CURRENT_PHASE.md` remains the authority for implemented product status.

## Executive summary

MediaForge does **not** need to restart or throw away the work already completed.

The current codebase is already much closer to the new adapter-first architecture than the older
fork-oriented target documents suggest:

- the shipped application is a Laravel 12 modular application with React 19/Inertia today;
- Jellyfin and Audiobookshelf are already accessed through connector contracts and public HTTP APIs;
- the connector SDK isolates provider implementations from Core and from each other;
- catalog snapshots, normalization, import planning, review tasks, import execution and external mappings
  already persist into MediaForge PostgreSQL;
- production/dev Compose already treat Jellyfin and Audiobookshelf as separate containers/services;
- MediaForge already renders from stored PostgreSQL state rather than doing network fan-out on every page;
- `media_external_mappings` already provides a strong per-connector-instance external identity bridge.

The major conflict is therefore mostly in **future target documentation**, not current code.

The old plan to copy/import Jellyfin, Stash and Audiobookshelf source trees and later transform them
into `engines/video`, `engines/adult` and `engines/audio` should be superseded.

The preferred long-term boundary is:

```text
MediaForge Web / future clients
            |
            v
      MediaForge API
            |
            v
 MediaForge domain services
      |             |
      v             v
 PostgreSQL      adapter registry
 canonical       |    |     |
 catalog          |    |     |
                 v    v     v
             Jellyfin ABS Scene Tracker
               APIs   API     API
```

Specialist upstream databases remain owned by those upstream systems. MediaForge never migrates,
queries or mutates those databases directly.

## 1. Current architecture discovered

### Runtime/code reality

Current shipped code remains a single Laravel application with:

- Laravel 12 / PHP 8.4;
- React 19 + TypeScript + Inertia;
- PostgreSQL 17;
- Redis;
- Docker Compose;
- Jellyfin connector;
- Audiobookshelf connector;
- encrypted connector credentials;
- library discovery;
- catalog snapshots;
- catalog normalization;
- match preview;
- import plans;
- database-only internal import;
- review/audit/health foundations.

The current REST API surface is still mostly a future boundary; `routes/api.php` has the `/api/v1`
namespace but the current Web UI is Inertia-backed.

### Current connector boundary

`App\Connectors\Sdk\Contracts\ConnectorProvider` already creates a meaningful adapter seam:

- stable provider key/label;
- `testConnection()`;
- `discoverLibraries()`;
- capability flag for catalog snapshot;
- `snapshotLibraryItems()`.

Concrete Jellyfin/Audiobookshelf connectors live in separate namespaces and architecture tests enforce:

- Core does not depend on Connectors;
- Connector SDK does not depend on concrete providers;
- concrete Jellyfin and Audiobookshelf connectors do not depend on one another;
- controllers do not contain persistence.

This is a strong foundation for the new adapter model.

### Current catalog/import pipeline

The current pipeline is already local-catalog-first:

```text
upstream public API
 -> connector snapshot
 -> connector_catalog_items
 -> normalization
 -> match preview
 -> import plan
 -> database-only execution
 -> media_items
 -> media_external_mappings
```

That model should be expanded, not replaced.

## 2. What already matches the new direction

### KEEP

- Laravel control plane / domain orchestration.
- React + TypeScript and the planned API-first migration.
- PostgreSQL as MediaForge-owned canonical state.
- Connector SDK/registry and concrete provider isolation.
- Encrypted secret store.
- Explicit connection tests and health state.
- Library discovery and selected-library state.
- Stored external catalog snapshots.
- Normalization and review.
- Database-only import planning/execution.
- `media_external_mappings`.
- `provider_ids`.
- `media_items`.
- `media_editions`.
- `files` and `edition_files`.
- review tasks, audit log and import lineage.
- local browsing from stored PostgreSQL state.
- Docker Compose separation.
- optional bundled Jellyfin/Audiobookshelf as separate containers.
- Work/Edition/File ambition.
- acquisition, NZBGet/qBittorrent/Prowlarr integration.
- Rust MediaTools and optional Python AI.
- plugin/theme SDK.
- Adult zero-leak/privacy boundary.
- disc/ISO, advanced media and AI ideas as later optional capabilities.

## 3. What conflicts with the new direction

### REWRITE

The following target assumptions conflict and should be rewritten:

- Jellyfin-derived internal Video Engine.
- Audiobookshelf-derived internal Audio Engine.
- Stash-derived internal Adult Engine.
- Track-02 source-baseline import of all three projects.
- `engines/video`, `engines/audio`, `engines/adult` as copied upstream source trees.
- engine-cutover phases whose goal is replacing upstream runtime ownership.
- plans to migrate a Stash-derived engine database to PostgreSQL.
- C#/Go/Node runtime requirements that exist only because of the fork plan.

### DEPRECATE / SUPERSEDE

- ADR-0025's decision to import Jellyfin/Stash/Audiobookshelf source baselines.
- the fork-specific part of ADR-0014.
- old roadmap language describing deep fork/bundling as the destination.
- `modules/adult-engine-target.md` as a Stash-fork target.

Historical ADR text should remain visible but be marked superseded.

### REMOVE FROM FUTURE TARGET, NOT FROM HISTORY

- copied upstream source trees as a normal MediaForge repository responsibility;
- upstream-sync tooling whose only purpose is maintaining those deep forks;
- source-level engine cutover as a required milestone.

There is currently no imported Jellyfin/Stash/Audiobookshelf engine tree on `main`, so there is no
large code deletion required.

## 4. PostgreSQL model — what can be reused

### Strong reusable foundations

- `media_items` — canonical MediaForge item identity/hierarchy.
- `media_editions` — edition/cut/remaster/language/quality/upscale variants.
- `files` + `edition_files` — physical representation boundary.
- `provider_ids` — external provider IDs are mappings rather than primary identity.
- `connector_instances` — one configured external service instance.
- `connector_libraries` — discovered external libraries.
- `connector_sync_states` — cursor/state foundation.
- `connector_catalog_snapshot_runs` / `connector_catalog_items` — durable external mirror.
- `connector_catalog_item_normalizations` — normalized mirror/projection.
- `media_external_mappings` — external item -> canonical MediaForge item bridge.
- import plans / executions / execution items — deterministic import lineage.
- `review_tasks` — ambiguity/human decision boundary.
- `audit_log` — ownership/change trace.

### Concepts that are still missing or incomplete

1. **Explicit Work identity**
   - Current `media_items` carries work-like identity for many types but there is no generic cross-media
     Work table in the implemented migrations.
   - Do not add one blindly. First define exact Work-vs-MediaItem semantics and migration/backfill rules.

2. **File vs FileLocation**
   - Current `files` still contains `path`.
   - Long-term move/rename identity would be cleaner with stable File/content identity plus revisioned locations.

3. **Field-level metadata provenance**
   - Current schema has `metadata_locked_fields`, provider IDs and import metadata, but not yet the full
     source-fact/canonical-choice/history model described by the roadmap.

4. **Capability/version snapshots**
   - Connector health exists; explicit supported-version ranges and capability snapshots are not yet a
     first-class implemented model.

5. **Unified progress ownership**
   - `user_watch_states` is useful and should remain.
   - Raw Jellyfin/ABS playback progress is upstream technical state.
   - MediaForge may keep a normalized cross-service projection/resume state, but bidirectional write-back
     must use an explicit conflict/ownership policy.

### Avoid

Do not create duplicate tables merely because a new diagram uses new nouns.
Existing connector/catalog/import tables should be evolved in place where semantics match.

## 5. Jellyfin connector assessment

The current Jellyfin connector already behaves like an adapter:

- `/System/Info` for authenticated health/version;
- `/Library/MediaFolders` for library discovery;
- `/Items` for bounded catalog snapshots;
- sanitized network/auth failures;
- provider code behind `ConnectorProvider`.

### Required evolution

Add capabilities incrementally rather than replacing it:

- explicit server version + supported range;
- capability negotiation;
- richer catalog DTOs;
- image references;
- technical media information;
- playback preparation/session operations;
- tracks/subtitles/device profiles;
- progress read/write policy;
- event/webhook/polling reconciliation;
- library refresh where explicitly authorized.

Jellyfin DTOs must not leak through the MediaForge domain/UI.

## 6. Audiobookshelf connector assessment

The same connector seam is reusable.

Required later capabilities include:

- explicit version/compatibility;
- richer audiobook/book/podcast catalog;
- chapters;
- image references;
- playback/session URLs;
- listening progress mirror/write-back policy;
- library refresh/event reconciliation.

MediaForge should retain useful synchronized metadata even if ABS later renames/moves an item or changes
its local identifier.

## 7. Scene Tracker integration

Scene Tracker is a **separate product and separate database**.

MediaForge integrates through a versioned Scene Tracker API for:

- scenes;
- performers;
- studios;
- tags/taxonomy;
- source provenance;
- matching/discovery data.

MediaForge must not query Scene Tracker PostgreSQL directly.

For local adult playback, Jellyfin can remain the specialist playback/server boundary while Scene Tracker
supplies external metadata. Stash may remain a future optional external adapter if useful, but it is not a
required internal MediaForge engine.

## 8. Docker architecture

The current Compose topology is already strongly aligned.

### Existing-server install

```text
MediaForge
PostgreSQL
Redis

external:
Jellyfin
Audiobookshelf
Scene Tracker API (optional)
```

### All-in-one convenience install

```text
MediaForge
PostgreSQL
Redis
Jellyfin container
Audiobookshelf container
```

They remain separate containers with separate internal persistence.

No Docker-in-Docker.
No giant container.
No database merging.

Scene Tracker normally remains separately deployed/external because it is its own product.

## 9. Proposed final component diagram

```text
                          MediaForge clients
                  Web / PWA / TV / desktop / mobile
                                  |
                                  v
                         MediaForge API v1
                                  |
                    +-------------+-------------+
                    |                           |
                    v                           v
            MediaForge domain              PostgreSQL
               services                canonical MediaForge
                    |                       catalog/state
                    |
          +---------+----------+-------------------------+
          |                    |                         |
          v                    v                         v
   JellyfinAdapter      AudiobookshelfAdapter      SceneTrackerAdapter
          |                    |                         |
          v                    v                         v
      Jellyfin             Audiobookshelf           Scene Tracker
     own DB/files             own DB/files             own DB
          |
          +---- playback / transcoding / sessions / technical media

MediaForge optional native services:
  Rust MediaTools
  Python AI

MediaForge managed acquisition backends:
  Prowlarr -> NZBGet / qBittorrent
  optional/transitional *Arr integrations
```

## 10. Source-of-truth matrix

| Concern | Owner |
|---|---|
| MediaForge canonical IDs | MediaForge PostgreSQL |
| unified catalog identity | MediaForge PostgreSQL |
| Work/Edition/File relationships | MediaForge PostgreSQL |
| external mappings | MediaForge PostgreSQL |
| field provenance/manual locks | MediaForge PostgreSQL |
| review/audit | MediaForge PostgreSQL |
| collections/preferences/unified search | MediaForge PostgreSQL |
| acquisition/import lineage | MediaForge PostgreSQL |
| Jellyfin playback/transcoding/session mechanics | Jellyfin |
| Jellyfin internal library/runtime DB | Jellyfin |
| ABS playback/chapter/runtime mechanics | Audiobookshelf |
| ABS internal DB | Audiobookshelf |
| Scene Tracker community/source metadata | Scene Tracker |
| Scene Tracker internal DB | Scene Tracker |
| raw media bytes | filesystem/upstream service |
| AI/derived large artifacts | artifact/object/filesystem store |
| queue/cache/locks | Redis (technical only) |

## 11. Sync model

Browsing must not depend on upstream availability.

```text
explicit refresh / scheduler / upstream event / startup reconciliation
                           |
                           v
                      adapter read
                           |
                           v
                    external snapshot
                           |
                           v
                      normalize
                           |
                           v
                 compare/reconcile/match
                           |
                           v
                MediaForge PostgreSQL
                           |
                           v
                    MediaForge API/UI
```

Rules:

- incremental cursors where supported;
- bounded pagination;
- last-known-good data remains browsable;
- upstream disappearance changes availability, not canonical identity;
- no direct cross-database access;
- no live fan-out on ordinary page render;
- ambiguous remaps go to Review;
- manual locks win over automatic enrichment.

## 12. Adapter contracts

Prefer capability-oriented contracts over provider conditionals.

Common integration surface:

```text
identity:
  key()
  version()
  capabilities()
  health()

catalog:
  listLibraries()
  listItems(cursor/filter)
  fetchItem()
  refresh()

artwork:
  getArtworkReference()

playback:
  preparePlayback()
  startSession()
  stopSession()
  listTracks()
  selectTrack()

progress:
  readProgress()
  writeProgress()     # only if policy allows

events/sync:
  cursor()
  pollChanges()
  subscribe/webhook() # only if upstream supports it reliably
```

Not every adapter implements every capability.

The frontend consumes MediaForge DTOs, never upstream DTOs.

## 13. Client architecture

Keep the planned API-first transition:

```text
React 19 + TypeScript
        |
MediaForge API v1
        |
domain services
        |
adapters
```

Inertia remains transitional.

Do not create separate Jellyfin/ABS/Scene-Tracker pages as the final UX.
Backend identity is shown only where relevant for provenance, diagnostics or integration settings.

## 14. Development migration plan

### Step 1 — architecture supersession
- land ADR-0028;
- rewrite target docs;
- mark fork ADRs superseded;
- add prompt-system supersession note;
- keep runtime code unchanged.

### Step 2 — preserve and formalize current adapters
- rename/generalize connector concepts only where it improves semantics;
- add capability/version contracts;
- add compatibility fixtures/tests;
- avoid churn for working V2 code.

### Step 3 — complete canonical catalog foundation
- finish V2 consistency;
- decide Work-vs-MediaItem model;
- add provenance/source-fact model;
- add stable file/location identity where justified.

### Step 4 — API-first client boundary
- establish API v1;
- React Router app shell;
- migrate Inertia flows incrementally;
- same domain services underneath.

### Step 5 — richer Jellyfin/ABS synchronization
- technical media mirrors;
- chapters/images;
- incremental sync;
- resilient offline browsing.

### Step 6 — playback
- Jellyfin playback session adapter;
- ABS playback adapter;
- unified MediaForge player/UX;
- progress reconciliation.

### Step 7 — Scene Tracker
- versioned API adapter;
- scene/performer/studio/source mapping;
- Jellyfin local playback mapping;
- zero-leak private domain.

### Step 8 — acquisition and advanced features
- keep NZBGet/qBittorrent/Prowlarr architecture;
- safe import/lineage;
- later Disc/AI/Rust services.

## 15. Historical 720-prompt plan

Most lifecycle structure remains useful:

- audit/model/boundaries/persistence/application/API/frontend/validation/security/jobs/realtime/
  observability/migration/fixtures/tests/performance/docs/gate;
- dependency graph;
- green-gate discipline;
- contract-first implementation.

The provider/fork assumptions need reinterpretation:

- Track 02: adapter/compatibility/Compose manifests, **not source-baseline imports**.
- Track 07: adapter/capability contracts remain highly relevant.
- Tracks 26–28: external API integration/playback/compatibility maturity, **not engine cutover**.
- Adult tracks: Scene Tracker metadata + Jellyfin playback + optional external adapters, not mandatory Stash fork.
- Track 29 Rust MediaTools remains useful.
- Track 36 external-service compatibility E2E remains useful.

A dedicated prompt-system rewrite should update affected Pxxxx files after this architecture documentation
lands. Do not silently execute old fork-specific wording in the meantime.

## 16. Risks

1. **Adapter APIs can change.**
   - mitigate with supported-version ranges, fixtures and contract tests.

2. **Upstream outage.**
   - local snapshots keep browsing available; runtime capability is degraded explicitly.

3. **Multi-master progress conflicts.**
   - define ownership and reconciliation before bidirectional writes.

4. **Mirrored metadata becoming stale.**
   - store source timestamps, sync cursors and last-known availability.

5. **Over-normalization.**
   - keep raw/source facts where useful; canonical projection should not erase provenance.

6. **Source-specific leakage into domain/UI.**
   - stable MediaForge DTOs and capability contracts.

7. **Prompt plan still contains fork-era language.**
   - temporary supersession document now; systematic Pxxxx rewrite next.

## 17. Recommendation

The architecture change should be accepted.

It reduces:

- upstream sync burden;
- licensing/source-tree complexity;
- build/runtime language surface;
- deep-fork maintenance;
- upgrade friction.

It preserves the parts of MediaForge that are actually differentiating:

- unified product UX;
- canonical PostgreSQL catalog;
- provenance;
- Work/Edition/File;
- search;
- review;
- acquisition;
- advanced library intelligence;
- optional AI/media tooling.

Most importantly, it aligns with the code that already exists instead of fighting it.
