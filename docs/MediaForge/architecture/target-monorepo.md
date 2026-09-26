# Target Monorepo Architecture

Status: **binding target architecture**
Updated: **2026-09-27**

This document supersedes the older plan that placed copied Jellyfin/Stash/Audiobookshelf source
trees under `engines/`.

## 1. Product/repository boundary

MediaForge remains one GitHub repository and one visible product.

The repository contains **MediaForge-owned code, contracts, integration adapters, platform manifests
and optional MediaForge-native services**.

Mature specialist servers normally remain separate upstream projects and processes.

```text
MediaForge repository
├── MediaForge server
├── MediaForge web/client code
├── adapter implementations
├── canonical contracts/models
├── optional Rust MediaTools
├── optional Python AI
└── platform/Compose integration

External/upstream specialist services
├── Jellyfin
├── Audiobookshelf
├── Scene Tracker
├── NZBGet
├── qBittorrent
├── Prowlarr
└── optional/transitional *Arr services
```

## 2. Target root structure

```text
MediaForge/
├── apps/
│   ├── server/                 # Laravel control plane / API / domain
│   ├── web/                    # React 19 + TypeScript + React Router Framework Mode
│   ├── desktop/                # later
│   ├── mobile/                 # later
│   └── tv/                     # later
│
├── services/
│   ├── media-tools/            # Rust, MediaForge-native native media tooling
│   └── ai/                     # Python, optional ML/AI
│
├── packages/
│   ├── contracts/              # OpenAPI / JSON Schema / events / adapter contracts
│   ├── sdk/
│   ├── media-model/
│   ├── localization/
│   ├── design-tokens/
│   ├── ui-web/
│   └── icons/
│
├── platform/
│   ├── docker/
│   ├── compose/
│   ├── gateway/
│   ├── database/
│   ├── integrations/           # manifests/version matrices for Jellyfin/ABS/etc.
│   ├── managed-upstreams/      # NZBGet/qBit/Prowlarr/*Arr manifests
│   ├── observability/
│   └── releases/
│
├── tests/
│   ├── e2e/
│   ├── integration/
│   ├── contracts/
│   ├── performance/
│   └── fixtures/
│
├── tools/
│   ├── codegen/
│   ├── compatibility/
│   ├── migrations/
│   ├── release/
│   └── dev/
│
└── docs/
```

There is no required `engines/video`, `engines/audio` or `engines/adult` copied-upstream tree.

## 3. Why the monorepo still matters

- server/web/contracts change atomically;
- generated SDKs and contract tests share one commit;
- adapter compatibility fixtures live next to product code;
- Rust/Python services remain coordinated with the control plane;
- Compose/release artifacts are built from one MediaForge revision.

A monorepo does **not** imply importing the source of every service MediaForge integrates with.

## 4. `apps/server`

Laravel owns:

- auth/users/roles/sessions;
- MediaForge canonical catalog and ULIDs;
- Work/MediaItem/Edition/File model;
- external mappings;
- synchronized external snapshots;
- provenance/manual locks/review;
- search/collections/preferences;
- adapter registry/capabilities/version compatibility;
- acquisition orchestration;
- audit/settings/health/backup coordination;
- MediaForge API v1.

Provider-specific adapter implementations remain behind contracts and do not leak into Core.

## 5. `apps/web`

Target:

- React 19;
- TypeScript;
- React Router Framework Mode;
- Vite;
- MediaForge API v1;
- capability-driven UI.

Inertia is transitional.

The frontend must not call Jellyfin, Audiobookshelf or Scene Tracker APIs directly.

## 6. Integration adapters

Adapters run inside or beside the MediaForge server boundary as appropriate.

Conceptually:

```text
apps/server/app/Integrations/
├── Contracts/
├── Jellyfin/
├── Audiobookshelf/
├── SceneTracker/
└── ...
```

The existing `app/Connectors` tree is the current foundation and should be evolved incrementally,
not renamed/moved merely for aesthetics.

## 7. External specialist services

### Jellyfin

Owns specialist playback/streaming/transcoding/session/device functionality and its internal DB.

### Audiobookshelf

Owns audiobook/podcast specialist playback/runtime functionality and its internal DB.

### Scene Tracker

Separate metadata/community product with a versioned integration API and its own database.

### Acquisition backends

NZBGet, qBittorrent and Prowlarr remain strong long-term candidates for managed external components.
Sonarr/Radarr/Whisparr may remain transitional/optional automation adapters.

## 8. MediaForge-native services

Rust MediaTools and Python AI remain valid because they are MediaForge-specific capabilities rather
than copied upstream media servers.

They communicate through versioned contracts and never mutate unrelated domain tables directly.

## 9. PostgreSQL

MediaForge PostgreSQL owns MediaForge state, not upstream internal state.

External IDs are mappings.
External snapshots are mirrors/source facts.
Media bytes remain outside PostgreSQL.

## 10. No cross-database integration

Forbidden:

```text
Laravel -> Jellyfin DB
Laravel -> Audiobookshelf DB
Laravel -> Scene Tracker DB
Scene Tracker -> MediaForge DB
```

Allowed:

```text
MediaForge -> versioned adapter -> supported upstream API
```

## 11. Deployment

Existing-server mode:

```text
MediaForge + PostgreSQL + Redis
          |
          +-> external Jellyfin
          +-> external Audiobookshelf
          +-> external Scene Tracker API
```

All-in-one convenience mode may add Jellyfin/Audiobookshelf as **separate** Compose containers.

## 12. Migration from the current repository

1. Land architecture supersession/ADR.
2. Preserve V2 connector/catalog/import code.
3. Formalize adapter capability/version contracts.
4. Create API v1 and React Router migration boundary.
5. Move server/web into target folders only when safe.
6. Expand Jellyfin/ABS adapters.
7. Add Scene Tracker adapter.
8. Implement playback through upstream APIs.
9. Continue acquisition/advanced MediaForge features.
10. Never make copied upstream source import a prerequisite.

## 13. Definition of Done for the architecture foundation

- current V2 behavior preserved;
- API v1 contract-tested;
- server/web separable;
- connector/adapters capability-driven;
- local catalog browsable during upstream outage;
- no upstream DB access;
- Jellyfin/ABS version compatibility surfaced;
- optional bundled services remain separate containers;
- old fork plan clearly superseded.
