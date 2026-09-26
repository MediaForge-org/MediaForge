# MediaForge Engineering Roadmap

`CURRENT_PHASE.md` remains the authority for implemented status.

Updated target architecture: **2026-09-27 — external specialist services through versioned adapters**.

The old mandatory deep-fork/cutover destination is superseded by ADR-0028.

## Current status

V1 complete. V2 A–E implemented according to `CURRENT_PHASE.md`.

Existing V2 connector/catalog/import work is retained and becomes the foundation of the adapter model.

## Phase A — Architecture foundation

- land adapter-first ADR and source-of-truth matrix;
- formalize API-first boundary;
- React Router migration plan;
- adapter capability/version contracts;
- compatibility fixtures;
- keep current V2 behavior green;
- no upstream source-tree imports.

## Phase B — Unified canonical catalog

- finish V2 consistency;
- external mappings;
- Work/MediaItem/Edition/File semantics;
- file/location identity;
- source facts / field provenance;
- canonical/manual locks;
- unified search;
- collections;
- review and health.

## Phase C — Media client core

MediaForge product surfaces:

- Home;
- Movies;
- TV;
- Scenes;
- Performers;
- Studios;
- Music;
- Audiobooks;
- Books;
- Podcasts;
- Search;
- Collections.

The source backend must not dominate the UX.

## Phase D — Playback adapters

### Jellyfin

- playback preparation/session integration;
- direct play/remux/transcode negotiation;
- subtitles/audio tracks;
- device capabilities;
- technical media mirror;
- progress reconciliation.

### Audiobookshelf

- audiobook/podcast playback;
- chapters;
- progress;
- book/ebook capabilities where supported.

PHP does not become a bulk media proxy.

## Phase E — PWA / TV / client polish

- responsive PWA;
- remote/focus navigation;
- 10-foot mode;
- fullscreen player;
- native packaging only when it provides real value.

## Phase F — Library intelligence

- field provenance;
- metadata history/rollback;
- safe matching;
- review center;
- library health/repair;
- dedup/fingerprinting;
- Work graph;
- cross-edition relationships.

## Phase G — Acquisition

- Prowlarr/Newznab/Torznab/provider adapters;
- NZBGet;
- qBittorrent;
- requests/wanted state;
- release scoring;
- staging;
- safe import;
- naming/move/hardlink;
- seeding preservation;
- upgrade policy;
- provenance;
- resumable post-processing DAG.

Sonarr/Radarr/Whisparr remain optional/transitional integrations, not canonical models.

## Phase H — Scene Tracker / private domain

- versioned Scene Tracker adapter;
- scene/performer/studio/source mappings;
- Jellyfin local-playback mapping;
- Adult zero-leak privacy;
- source history;
- advanced taxonomy;
- local filename/curated fallback.

Stash may be an optional adapter, not a required fork.

## Phase I — Advanced media

- Disc/ISO/BDMV/VIDEO_TS;
- verified-only mapping;
- remux;
- optimized editions;
- AV1/H.265 where justified;
- lineage;
- audiobook chapter intelligence;
- audio enhancement.

## Phase J — Extensibility and optional advanced systems

- plugin SDK;
- theme SDK/custom CSS;
- metadata/acquisition/analyzer providers;
- Rust MediaTools growth;
- AI analysis;
- semantic search;
- artifact/model store;
- GPU scheduler;
- optional 3D/reconstruction/research features.

These must not complicate the stable core install.

## Deployment/release maturity

Ongoing across phases:

- default MediaForge + PostgreSQL + Redis;
- connect existing Jellyfin/ABS;
- optional separate Jellyfin/ABS Compose containers;
- integration version matrix;
- contract/E2E compatibility tests;
- backups/restore validation;
- multi-arch images/SBOM/signing;
- upgrade/rollback policy.

## Historical 720-prompt system

The 720 IDs remain useful as lifecycle-sized work units.

Reinterpret affected tracks:

- Track 02: adapter/compatibility/platform preparation, not Jellyfin/Stash/ABS source import.
- Track 07: capability/adapter contracts.
- Tracks 14–15: acquisition remains valid.
- Tracks 26–28: external playback/library adapter maturity, not fork cutover.
- Adult tracks: Scene Tracker + Jellyfin split, optional Stash adapter.
- Track 29: Rust MediaTools remains.
- Track 36: integration compatibility/release gates.

A dedicated prompt rewrite should synchronize affected numbered prompts before those tracks execute.

## Gates

### Foundation gate

Before large UI/client expansion:

- API/adapter target documented;
- current V2 functionality preserved;
- contract tests in place;
- no direct upstream DB access;
- React Router/API migration path proven.

### Usable-core gate

Before Disc/full AI/3D:

- auth/security/backup stable;
- canonical catalog;
- normal movies/TV/audiobooks/books usable;
- playback stable;
- metadata provenance/review/search/health;
- background jobs/observability;
- no critical data-loss behavior.

## Long-term principle

MediaForge is **one product**.

Jellyfin, Audiobookshelf, Scene Tracker, NZBGet, qBittorrent and other integrations are capabilities,
not separate user journeys.
