# MediaForge Milestone / Phase Index

**Active execution system from 2026-09-27.**

The legacy 720 `Pxxxx` prompts are retained only as a historical requirements archive. New work is authorized through `Mx.y` execution units.

Granularity is intentionally uneven: simple milestones can be tiny; high-risk milestones are split more finely. Every unit includes its own implementation and tests instead of one fixed 20-prompt lifecycle.

- **M1:** done
- **Current:** `M2.1`
- Existing V1/V2 implementation is preserved; see `IMPLEMENTED_BASELINE.md`.

## M1 — Governance, architecture and execution foundation

**Status:** done  
**Legacy coverage:** tracks 1

- `M1.1` — Repository baseline and governance contracts — **done**
- `M1.2` — Governance security, runtime and observability hardening — **done**
- `M1.3` — Adapter-first architecture and milestone execution migration — **done, gate**

## M2 — Platform, monorepo, API and frontend transition

**Status:** current  
**Legacy coverage:** tracks 2, 3, 4, 5, 6, 7

- `M2.1` — Target root layout and compatibility scaffolding — **CURRENT**
- `M2.2` — Laravel server relocation
- `M2.3` — React web relocation
- `M2.4` — MediaForge API v1 boundary
- `M2.5` — React Router migration and Inertia retirement
- `M2.6` — OpenAPI, schemas and generated client contracts
- `M2.7` — Platform transition gate — **gate**

## M3 — Canonical PostgreSQL media model

**Status:** planned  
**Legacy coverage:** tracks 8, 12, 32

- `M3.1` — Canonical identity and ownership boundaries
- `M3.2` — Work and MediaItem semantics
- `M3.3` — Edition, File and FileLocation model
- `M3.4` — External mappings and source facts
- `M3.5` — Migration and backfill of existing V1/V2 data
- `M3.6` — Constraints, indexes and query budgets
- `M3.7` — Canonical model gate — **gate**

## M4 — Security, authentication and privacy foundation

**Status:** planned  
**Legacy coverage:** tracks 9

- `M4.1` — Authentication, roles and policy boundaries
- `M4.2` — Secrets, request hardening and privacy isolation
- `M4.3` — Security regression gate — **gate**

## M5 — Design system, application shell and global discovery

**Status:** planned  
**Legacy coverage:** tracks 10, 11

- `M5.1` — Design tokens and reusable UI primitives
- `M5.2` — App shell, navigation and responsive behavior
- `M5.3` — Home, unified search and collections foundation
- `M5.4` — Visual, accessibility and interaction gate — **gate**

## M6 — Library, files, metadata vault and review

**Status:** planned  
**Legacy coverage:** tracks 12, 13

- `M6.1` — Library roots, discovery and non-destructive inventory
- `M6.2` — Stable file identity and relocation reconciliation
- `M6.3` — Metadata source facts, provenance and manual locks
- `M6.4` — Matching, duplicate detection and Review workflows
- `M6.5` — Library health, repair and safe mutation previews
- `M6.6` — Library and metadata gate — **gate**

## M7 — Acquisition, downloads and safe import

**Status:** planned  
**Legacy coverage:** tracks 14, 15

- `M7.1` — Acquisition domain and provider abstraction
- `M7.2` — Prowlarr and provider integration
- `M7.3` — NZBGet download adapter and authored UI
- `M7.4` — qBittorrent adapter and seeding-safe lifecycle
- `M7.5` — Staging, import sandbox and filesystem plan
- `M7.6` — Post-processing, provenance and resumability
- `M7.7` — Acquisition end-to-end gate — **gate**

## M8 — Unified playback and Jellyfin integration

**Status:** planned  
**Legacy coverage:** tracks 16, 26

- `M8.1` — Playback session and stream-routing domain
- `M8.2` — Jellyfin version, health and capability negotiation
- `M8.3` — Richer Jellyfin catalog and technical-media mirror
- `M8.4` — Direct play, remux and transcode preparation
- `M8.5` — Tracks, subtitles, device profiles and audio policy
- `M8.6` — Progress and session reconciliation
- `M8.7` — Unified player UI
- `M8.8` — Jellyfin playback compatibility gate — **gate**

## M9 — Series and movies

**Status:** planned  
**Legacy coverage:** tracks 17, 18

- `M9.1` — Series, seasons, episodes and alternate orders
- `M9.2` — Movies, cuts, extras and technical editions
- `M9.3` — Series/movie product surfaces
- `M9.4` — Series/movie regression gate — **gate**

## M10 — Audiobooks, books and Audiobookshelf integration

**Status:** planned  
**Legacy coverage:** tracks 19, 28

- `M10.1` — Literary Work, book edition and audiobook edition model
- `M10.2` — Audiobookshelf version/capability and rich sync
- `M10.3` — Persistent metadata across rename/move/rescan
- `M10.4` — Audiobook chapters and storage strategies
- `M10.5` — EPUB/PDF reader and reading state
- `M10.6` — Audiobookshelf playback and listening progress bridge
- `M10.7` — Books/audiobooks compatibility gate — **gate**

## M11 — Private scene domain and Scene Tracker integration

**Status:** planned  
**Legacy coverage:** tracks 20, 21, 27

- `M11.1` — Private mode and Adult Zero Leak enforcement
- `M11.2` — Scene Tracker adapter and compatibility
- `M11.3` — Scene, performer and studio canonical model
- `M11.4` — Adult source vault and historical provenance
- `M11.5` — Jellyfin scene playback mapping
- `M11.6` — Private matching, coverage and Review
- `M11.7` — Private authored product surfaces
- `M11.8` — Private-domain privacy and compatibility gate — **gate**

## M12 — Adult taxonomy, event timeline and multimodal analysis

**Status:** planned  
**Legacy coverage:** tracks 22, 23

- `M12.1` — Hierarchical taxonomy and typed attributes
- `M12.2` — Timeline event and evidence model
- `M12.3` — Optional multimodal analysis pipeline
- `M12.4` — Audio events and temporal boundary refinement
- `M12.5` — Verification, review and coverage UX
- `M12.6` — Analysis quality/performance/privacy gate — **gate**

## M13 — Disc, ISO, BDMV and VIDEO_TS

**Status:** planned  
**Legacy coverage:** tracks 24

- `M13.1` — Disc identity, structure and immutable source model
- `M13.2` — Disc probing and structural extraction
- `M13.3` — Verified-only movie/episode mapping
- `M13.4` — Playback/remux handoff
- `M13.5` — Disc verification gate — **gate**

## M14 — Rust MediaTools and audio enhancement

**Status:** planned  
**Legacy coverage:** tracks 25, 29

- `M14.1` — Rust MediaTools service/contracts
- `M14.2` — Probe, hash and fingerprint primitives
- `M14.3` — Loudness, true-peak and playback-audio analysis
- `M14.4` — Audio restoration/upscale derived editions
- `M14.5` — Native media tooling gate — **gate**

## M15 — Jobs, events, realtime, observability and resilience

**Status:** planned  
**Legacy coverage:** tracks 30, 35

- `M15.1` — Durable job model and idempotency
- `M15.2` — Events and realtime progress contracts
- `M15.3` — Scheduler, retry, cancellation and recovery
- `M15.4` — Observability, health and performance budgets
- `M15.5` — Backup, restore and disaster recovery
- `M15.6` — Chaos, restart and resilience gate — **gate**

## M16 — Music, podcasts and cross-media Work graph

**Status:** planned  
**Legacy coverage:** tracks 31, 32

- `M16.1` — Music and podcast canonical models
- `M16.2` — General audio browse and playback
- `M16.3` — Work graph, collections and recommendations
- `M16.4` — Cross-media gate — **gate**

## M17 — Clients, extensions and optional AI

**Status:** planned  
**Legacy coverage:** tracks 33, 34

- `M17.1` — Shared client SDKs and capability surface
- `M17.2` — PWA, TV and 10-foot interaction
- `M17.3` — Desktop/mobile packaging where justified
- `M17.4` — Plugin, provider and theme SDK
- `M17.5` — Optional AI/model registry and semantic capabilities
- `M17.6` — Client/extension compatibility gate — **gate**

## M18 — Distribution, upgrades and final integration

**Status:** planned  
**Legacy coverage:** tracks 36

- `M18.1` — Docker/release topology and optional service profiles
- `M18.2` — CI matrix, security QA, SBOM and signing
- `M18.3` — Upgrade, compatibility and rollback
- `M18.4` — Full-system E2E, performance and restore validation
- `M18.5` — Release-candidate gate — **gate**
