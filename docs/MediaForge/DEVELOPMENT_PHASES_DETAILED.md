# Detailed Development Phases and Rough Time Ranges

Planning guidance only. Updated **2026-09-27** for the adapter-first architecture.

## Phase A — Adapter/API architecture foundation

**~2–4 weeks**

- ADR/source-of-truth update;
- API v1 foundation;
- React Router transition boundary;
- adapter capability/version contracts;
- compatibility fixtures;
- gateway/deep links;
- preserve V2;
- no upstream source imports.

## Phase B — Canonical catalog completion

**~2–4 weeks**

- V2 consistency;
- Work/MediaItem/Edition/File decision/backfill;
- external mappings;
- stable file/location identity;
- source facts/field provenance;
- review/search/collections/health.

## Phase C — Security / backup / reliability

**~1–3 weeks**

- auth/API hardening;
- privacy baseline;
- audit;
- backup/restore;
- integration secret policy;
- sync/job observability.

## Phase D — Premium client core

**~3–6 weeks**

- API-first React product shell;
- Home;
- Movies/TV;
- Audiobooks/Books/Podcasts;
- Search/Collections;
- polished loading/error/offline states;
- responsive foundation.

## Phase E — Jellyfin + Audiobookshelf rich adapters/playback

**~3–6 weeks**

- richer catalog sync;
- version/capability compatibility;
- technical media mirror;
- Jellyfin playback sessions/tracks/subtitles/device profiles;
- ABS playback/chapters/progress;
- resilient progress reconciliation.

## Phase F — Scene Tracker / private scene product

**~3–6 weeks**

- Scene Tracker versioned adapter;
- scene/performer/studio/source mapping;
- Jellyfin local playback mapping;
- zero-leak private domain;
- source history/local fallback.

No mandatory Stash fork.

## Phase G — Acquisition

**~3–6 weeks**

- NZBGet/qBittorrent/Prowlarr;
- requests/intake;
- release scoring;
- staging;
- safe import;
- naming/hardlinks/seeding;
- provenance;
- retry/recovery/DAG.

## Phase H — TV/PWA/client polish

**~2–5 weeks**

- PWA;
- remote/focus navigation;
- 10-foot mode;
- fullscreen playback;
- casting/native features when justified.

## Phase I — Advanced media

**~4–10+ weeks**

- Disc/ISO;
- verified mapping;
- remux/optimized editions;
- audiobook chapter intelligence;
- audio enhancement;
- advanced lineage.

## Phase J — Optional AI/extensibility/research

**open-ended**

- plugin/theme SDK;
- Rust MediaTools optimization;
- AI event analysis;
- semantic search;
- artifact/model registry;
- GPU scheduling;
- optional 3D/reconstruction/tattoo projection.

## Distribution maturity

Runs alongside the phases:

- official MediaForge images;
- optional Jellyfin/ABS Compose profiles as separate containers;
- compatibility matrices;
- integration/E2E gates;
- SBOM/signing;
- rollback-tested upgrades.

## Rough overall view

Removing mandatory deep-fork work should reduce long-term maintenance and implementation risk.

The schedule still depends heavily on playback/API compatibility, catalog migration complexity,
Disc/AI scope and UI quality targets. Re-estimate after each major gate.
