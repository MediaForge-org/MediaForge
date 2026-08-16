# Track index

**Prompt count remains 720.** New AI/3D/plugin/storage/frontend decisions are integrated into existing tracks rather than adding new IDs.

Each track contains 20 granular prompts using the same lifecycle: audit → model → boundaries → persistence → application → API → frontend → validation → security → jobs → realtime → observability → migration → fixtures → unit tests → integration tests → E2E → performance → docs → gate.

- **01 Governance, repository audit and execution discipline** (P0) — `P0001`–`P0020` — folder `01-governance-audit/`
- **02 Target monorepo root structure and migration scaffolding** (P0) — `P0021`–`P0040` — folder `02-monorepo-root/`
- **03 Laravel server relocation into apps/server** (P0) — `P0041`–`P0060` — folder `03-server-migration/`
- **04 React/TypeScript web app relocation into apps/web** (P0) — `P0061`–`P0080` — folder `04-web-migration/`
- **05 React Router, human-readable routing and removal of Inertia** (P0) — `P0081`–`P0100` — folder `05-react-router-urls/`
- **06 MediaForge API v1 and BFF boundary** (P0) — `P0101`–`P0120` — folder `06-api-v1/`
- **07 OpenAPI, JSON Schema, events and generated clients** (P0) — `P0121`–`P0140` — folder `07-contracts-codegen/`
- **08 PostgreSQL canonical model and database boundaries** (P0) — `P0141`–`P0160` — folder `08-postgres-core/`
- **09 Authentication, authorization, security and privacy foundation** (P0) — `P0161`–`P0180` — folder `09-security-privacy/`
- **10 MediaForge design system and UI primitives** (P0) — `P0181`–`P0200` — folder `10-design-system/`
- **11 Unified home, navigation and global search shell** (P0) — `P0201`–`P0220` — folder `11-home-nav-search/`
- **12 Library, files, editions and storage model** (P0) — `P0221`–`P0240` — folder `12-library-files-editions/`
- **13 Metadata vault, provenance, matching and review center** (P0) — `P0241`–`P0260` — folder `13-metadata-provenance-review/`
- **14 Acquisition Center domain and source abstraction** (P1) — `P0261`–`P0280` — folder `14-acquisition-center/`
- **15 SABnzbd/qBittorrent intake, staging and import sandbox** (P1) — `P0281`–`P0300` — folder `15-download-import/`
- **16 Unified playback sessions, gateway and stream routing** (P1) — `P0301`–`P0320` — folder `16-playback-gateway/`
- **17 Series, seasons, episodes, orders and timeline features** (P1) — `P0321`–`P0340` — folder `17-series/`
- **18 Movies, cuts, technical editions and extras** (P1) — `P0341`–`P0360` — folder `18-movies/`
- **19 Audiobook works, editions, chapters and storage choices** (P1) — `P0361`–`P0380` — folder `19-audiobooks/`
- **20 Adult private-mode foundation and zero-leak UX** (P1) — `P0381`–`P0400` — folder `20-adult-private/`
- **21 Adult scene/performer/studio catalog, sources and coverage** (P1) — `P0401`–`P0420` — folder `21-adult-catalog/`
- **22 Hierarchical adult tags, attributes, evidence and event model** (P0) — `P0421`–`P0440` — folder `22-adult-taxonomy/`
- **23 Full video/audio analysis, timestamps and multimodal detection** (P2) — `P0441`–`P0460` — folder `23-adult-analysis/`
- **24 Disc, ISO, BDMV, VIDEO_TS and verified-only mapping** (P2) — `P0461`–`P0480` — folder `24-disc-iso/`
- **25 Audio restoration, upscaler and reconstructed editions** (P2) — `P0481`–`P0500` — folder `25-audio-enhancement/`
- **26 Jellyfin-derived video engine integration and fork boundary** (P2) — `P0501`–`P0520` — folder `26-video-engine/`
- **27 Stash-derived adult engine integration and fork boundary** (P2) — `P0521`–`P0540` — folder `27-adult-engine/`
- **28 Audiobookshelf-derived audio engine integration and fork boundary** (P2) — `P0541`–`P0560` — folder `28-audio-engine/`
- **29 Rust MediaTools service and native media plumbing** (P1) — `P0561`–`P0580` — folder `29-rust-media-tools/`
- **30 Background jobs, events, realtime progress and orchestration** (P0) — `P0581`–`P0600` — folder `30-jobs-events-realtime/`
- **31 Music, podcasts and general audio media support** (P2) — `P0601`–`P0620` — folder `31-music-podcasts/`
- **32 Work graph, collections, recommendations and cross-media relations** (P1) — `P0621`–`P0640` — folder `32-work-graph/`
- **33 Desktop, mobile and TV clients plus shared SDKs** (P2) — `P0641`–`P0660` — folder `33-clients/`
- **34 Plugin SDK, metadata providers and automation extensions** (P2) — `P0661`–`P0680` — folder `34-plugins-providers/`
- **35 Observability, performance, backup, restore and resilience** (P1) — `P0681`–`P0700` — folder `35-ops-performance/`
- **36 Docker images, CI, releases, security QA and final integration** (P1) — `P0701`–`P0720` — folder `36-release-qa/`

## 2026-08-17 scope clarifications

No prompt IDs are added; total remains **720**.

- Track 02 now includes pinned, buildable upstream baselines for Jellyfin/Stash/Audiobookshelf plus managed-upstream manifests/tooling.
- Tracks 10–13 include launch-locale i18n and metadata translation/provenance.
- Tracks 14–15 include unified provider search, managed *Arr/Prowlarr/SAB/qBit backends, naming, seeding-safe imports and post-processing.
- Track 24 includes verified series/movie disc extraction/remux handoff.
- Tracks 26–28 complete already-prepared engine cutovers.
- Tracks 30/34/35/36 include backend-event normalisation, provider/translation plugins, update/rollback/resource management and compatibility/localisation release gates.
