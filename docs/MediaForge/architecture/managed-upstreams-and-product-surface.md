# Managed Upstreams and the MediaForge Product Surface

Status: **binding target architecture**  
Updated: 2026-08-17

## 1. Product rule

MediaForge owns the product surface. Integrated upstream applications provide backend capabilities.
Their native UIs are administrative/developer fallbacks, not the normal MediaForge experience.

A user should be able to perform day-to-day media management without knowing which upstream backend provides a capability.

```text
User
  -> MediaForge Web / future desktop/mobile/TV clients
  -> MediaForge API / control plane
  -> capability adapters
       -> internal MediaForge-derived engines
       -> unchanged managed upstream services
```

MediaForge must never devolve into a launcher containing links to a collection of unrelated dashboards.

## 2. Two upstream classes

### 2.1 MediaForge-derived engines

These projects are imported early into the monorepo as pinned upstream baselines and are intentionally transformed into internal MediaForge engines over time:

- Jellyfin -> Video Engine;
- Stash -> Adult Engine;
- Audiobookshelf -> Audio Engine.

Their normal product UIs, user/account concepts and canonical databases are transitional references only. The MediaForge frontend, identity model, canonical PostgreSQL data and API remain authoritative.

### 2.2 Unmodified managed upstream services

These applications remain upstream products and should not accumulate MediaForge source patches unless a later ADR explicitly changes the rule:

- SABnzbd -> Usenet download backend;
- qBittorrent -> torrent download/seeding backend;
- Prowlarr -> indexer/tracker abstraction and definition ecosystem;
- Sonarr -> transitional series automation backend;
- Radarr -> transitional movie automation backend;
- Whisparr -> transitional Adult acquisition/monitoring backend.

For managed services MediaForge owns:

- lifecycle (install/start/stop/restart/health/logs);
- version pinning and compatibility matrix;
- update discovery and tested rollout;
- API credentials and secure configuration;
- normalised statuses/events;
- MediaForge UI and workflows.

The upstream application owns its specialised backend implementation.

## 3. Early source import for Jellyfin, Stash and Audiobookshelf

The source baselines are brought into the monorepo during Track 02, not first discovered in Tracks 26-28.

Target layout:

```text
engines/
├── video/
│   ├── upstream/      # pinned Jellyfin source baseline
│   └── mediaforge/    # adapters, patches and MediaForge-specific integration
├── adult/
│   ├── upstream/      # pinned Stash source baseline
│   └── mediaforge/
└── audio/
    ├── upstream/      # pinned Audiobookshelf source baseline
    └── mediaforge/
```

At import time each baseline records:

- official repository URL;
- release/tag;
- exact commit SHA;
- licence files and notices;
- build instructions;
- upstream compatibility notes.

For Jellyfin the initial long-lived MediaForge baseline should prefer Jellyfin 12.x stable once available. At the actual Track-02 import point, Claude must verify the then-current official stable releases for all three upstreams instead of relying on a hard-coded version from planning documents.

## 4. Upstream sync discipline

`tools/upstream-sync/` owns repeatable import/update tooling. An upstream update is never silently merged.

```text
new upstream release
 -> fetch/tag verification
 -> licence/provenance validation
 -> build upstream baseline
 -> MediaForge adapter compatibility tests
 -> contract tests
 -> E2E smoke tests
 -> explicit version-pin update
```

Large upstream trees should remain as close to upstream as practical. MediaForge-specific behaviour belongs in adapters/patch layers with documented reasons so future sync remains possible.

## 5. Managed-upstream layout

Managed applications do not need copied source trees in normal MediaForge Git history. Store manifests and integration artefacts instead:

```text
platform/managed-upstreams/
├── sabnzbd/
├── qbittorrent/
├── prowlarr/
├── sonarr/
├── radarr/
└── whisparr/
```

Each manifest records at minimum:

```text
component_key
upstream_repo
version/tag
image/reference
immutable digest/checksum
licence
compatibility range
update channel
health contract
required capabilities
```

## 6. MediaForge-only normal UI

Normal pages use MediaForge components and concepts:

```text
Acquisition
├── Search
├── Wanted
├── Releases
├── Downloads
├── Queue
├── History
├── Upgrades
├── Import
└── Sources
```

The normal UI must not expose provider implementation details unless they are relevant to debugging/provenance.

Example normalised queue item:

```text
Title: Supernatural S05E04
Media kind: Episode
Source: configured torrent provider
Automation backend: Sonarr (implementation detail)
Download backend: qBittorrent
Progress: 74%
Speed: 31.2 MB/s
Seed state: ratio 0.42 / target 1.50
Destination: Series/Supernatural/Season 05
```

## 7. Native UI fallback

For managed upstream services, an `Advanced native UI` action may be exposed to administrators for functionality MediaForge has not yet surfaced or for diagnosis.

Rules:

- never make the native UI the primary product navigation;
- proxy it through a protected MediaForge admin route where practical;
- do not leak credentials in URLs;
- preserve CSRF/origin/cookie protections;
- clearly label the view as an advanced upstream interface.

## 8. Sonarr/Radarr/Whisparr transition

These are initially valuable backends because they already implement wanted monitoring, release selection, quality upgrades, failed-download handling and import automation.

Long-term target:

```text
Phase A: MediaForge UI -> *Arr backends -> Prowlarr -> SAB/qBit
Phase B: MediaForge owns naming/provenance/release scoring; *Arr still monitor/grab
Phase C: MediaForge owns Wanted/Upgrade/Search/Download orchestration
Phase D: Sonarr/Radarr/Whisparr are optional migration/compatibility providers
```

Do not prematurely reimplement mature automation, but do not allow *Arr data models to become MediaForge's canonical product model.

## 9. Prowlarr/SAB/qBittorrent long-term role

Prowlarr, SABnzbd and qBittorrent may remain long-term managed components because they provide specialised, actively maintained functionality that is not itself MediaForge's differentiator.

MediaForge must still own:

- source configuration UX;
- unified search;
- release scoring;
- queue aggregation;
- naming/import policy;
- seeding policy;
- provenance;
- storage/resource policy;
- notifications and audit.

## 10. Compatibility gate

A managed component update is accepted only after the relevant compatibility suite proves:

- process/container starts;
- health endpoint/API authentication works;
- required API capabilities are still available;
- event/status normalisation works;
- MediaForge UI paths remain functional;
- representative download/indexer/automation smoke flows still pass;
- rollback to the previous pinned version remains possible.
