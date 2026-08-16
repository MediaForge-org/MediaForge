# MediaForge Engineering Roadmap

Zurück zur [Masterdatei](MediaForge_Master_Engineering.md). Der reale Ist-Stand wird **nur** in `CURRENT_PHASE.md` gepflegt.

## Neue Architecture Foundation vor weiteren großen UI-/Feature-Schritten

Die Zielarchitektur wurde präzisiert: React Router + API-first, Polyglot-Monorepo, schöne Deep Links, Contracts und Gateway sollen **vor** dem großen Premium-UI-Ausbau eingerichtet werden, damit keine neue Oberfläche auf einer später zu entfernenden Inertia-Grenze aufgebaut wird.

Siehe:

- `architecture/target-monorepo.md`
- `architecture/polyglot-runtime-and-contracts.md`
- `architecture/routing-and-public-urls.md`
- `DEVELOPMENT_PHASES_DETAILED.md`

## Aktueller Status

Stand August 2026: V1 abgeschlossen; V2 aktiv, A–E umgesetzt. Diese Aussage darf nur geändert werden, wenn `CURRENT_PHASE.md` und Code dies beweisen.

## Foundation Gate A

Vor V3/V4-Großumbauten:

- Monorepo-Zielstruktur angelegt;
- API v1/Contracts vorhanden;
- React Router App-Shell;
- Inertia-Migration begonnen/abgeschlossen gemäß Plan;
- Gateway `localhost:8100`;
- Deep Links;
- CI/Compose funktionieren;
- bestehende V2-Funktionalität erhalten.

## Usable-Core-Gate B

Vor Disc/AI Full Analysis/tiefen Spezialfeatures:

- Security/Auth/Backup stabil;
- Premium App Shell;
- Work/MediaItem/Edition/File Modell;
- Search/Metadata/Review/Health;
- normale Filme/Serien/Hörbücher brauchbar;
- Background Jobs/Observability;
- keine kritischen Datenverlustprobleme.

## Phasen

| Phase | Schwerpunkt | Status/Notiz |
|---|---|---|
| V0 | Repository/Developer Baseline | abgeschlossen |
| V1 | Local Core Alpha | abgeschlossen |
| V2 | Connector/Katalog/Normalisierung/erster Import | aktiv; A–E umgesetzt |
| **V2-F** | **Architecture Foundation: API-first Monorepo, React Router, Contracts, Gateway, Deep Links** | **als nächster Architekturblock** |
| V3 | Security Hardening / Privacy Baseline | geplant |
| V4 | Premium React UI/Design System/App Shell | geplant |
| V5 | i18n / locale-aware slugs/content | geplant |
| V6 | Work/MediaItem/Edition/File, Series Orders, Cuts, Chapters | geplant |
| V7 | Metadata Protection / Manual Locks | geplant |
| V8 | Metadata Vault / Field Provenance / Source History | geplant |
| V9 | Backup/Restore/DR | geplant |
| V10 | Universal Search / Finder | geplant |
| V11 | External/Official Source Discovery / Provider Marketplace | geplant |
| V12 | Smart Matching / Review Center / Bulk Review | geplant |
| V13 | Integrity/Health/Repair – **Usable-Core-Gate B** | geplant |
| V14 | Acquisition Foundation / Download Client Contracts | geplant |
| V15 | NZB/Torrent/Magnet Manual Intake / Acquisition Center | geplant |
| V16 | Staging / Import Sandbox / Rename-Move / Provenance | geplant |
| V17 | Quality Intelligence / Source Caps / Upgrades / Editions | geplant |
| V18 | Transcoding/optimized editions/lineage | geplant |
| V19 | Remote Access | geplant |
| V20 | Device API/Profile | geplant |
| V21 | Mobile Client | geplant |
| V22 | Realtime Watch State / Handoff / Cross-Edition Mapping | geplant |
| V23 | Desktop Server Packaging | geplant |
| V24 | Desktop Client | geplant |
| V25 | TV/Desktop Runtime optimization | geplant |
| V26 | High-Fidelity Player / Streaming UX | geplant |
| V27 | Streaming Advisor / GPU / Resource Monitor | geplant |
| V28 | Disc Detection / ISO/BDMV/VIDEO_TS | nach Gate B |
| V29 | Verified-only Disc Mapping / Menüs / episode watch state | nach Gate B |
| V30 | Audiobook Chapter Intelligence + Audio Enhancement foundation | nach Gate B |
| V31 | Adult Privacy Foundation / `/adult` / Zero Leak | geplant |
| V32 | Adult Metadata/Coverage/Taxonomy/Scene Lineage | geplant |
| V33 | Adult Full Analysis / AI Evidence / Smart Tags + Plugin/AI SDK | fortgeschritten |
| V34 | Deep upstream fork/bundling in monorepo + official Docker releases | spät, nach stabilen Contracts |

## P0/P1/P2 Feature Priorität

### P0 – Datenmodell/Contracts früh vorbereiten

- Scene/Event/Attribute/Evidence;
- Field Provenance;
- Scene Lineage;
- Work/Edition/File;
- Movie Cut vs Edition;
- Episode Orders;
- Audiobook Work/Edition/Chapter;
- Work Graph;
- Acquisition/Import Lineage;
- Slug History.

### P1 – nach brauchbarem Core

- Acquisition Center;
- Evidence Viewer;
- Review Center;
- Smart Collections;
- Timeline Skip Segments;
- official Chapter Discovery;
- Transcript Search;
- Cross-Edition Progress.

### P2 – fortgeschritten

- Full Adult AI Analysis at scale;
- Active Learning;
- Speaker/Character Recognition;
- reichhaltige Graph-Visualisierung;
- komplexe Historical Source Archive UI;
- weitere spezialisierte multimodale Modelle.

## Fork-Strategie

Forks liegen langfristig im selben Monorepo unter `engines/`. Der Import/Bundling-Schritt bleibt spät, aber Contracts/Ordner/Upstream-Sync-Regeln werden früh vorbereitet.

## Disc

Confidence sortiert höchstens Review-Kandidaten. Automatisches Episodenmapping nur bei `verified` Evidenz gemäß Disc Verification Policy.

## Adult

Adult ist normal gesperrt vollständig unsichtbar. Nach Unlock sind schöne `/adult/...` URLs Standard; Strict Private URLs optional.

## Zeitplanung

Siehe `DEVELOPMENT_PHASES_DETAILED.md` für grobe Korridore.

## Roadmap-Ergänzungen 2026-08-16

Die Zahl der Claude-Arbeitsschritte bleibt **720**. Neue Anforderungen werden in vorhandene Tracks integriert:

- Track 02: Plugin-/Theme-SDK-Ordner, Artifact-/Reconstruction-Struktur vorbereiten;
- Track 05: React Router Framework Mode und Inertia-Strangler-Migration;
- Track 07: Anatomy/Reconstruction/Plugin/Capability Contracts;
- Track 08: Analysis-/Reconstruction-Metadaten in PostgreSQL, keine großen BLOBs;
- Track 09: private 3D/Evidence/Model-Zugriffe;
- Track 10: Theme Tokens und Custom CSS;
- Track 21/22: Tattoo Coverage, Body Regions und Filter;
- Track 23: optionale Full Analysis + 3D Reconstruction + Tattoo Projection;
- Track 29: Rust Mesh/Projection/Evidence Hotpaths ohne rückwärtsgerichtete Dependency;
- Track 34: Plugin SDK, Theme SDK, Marketplace;
- Track 35: Artifact Store, Quotas/GC, AI Model Registry, GPU Scheduler;
- Track 36: optionale AI Docker Profiles/Model Downloads und vollständige CI-Matrix.

## Roadmap refinement — managed upstreams, acquisition and localisation (2026-08-17)

- **Track 02:** create managed-upstream structure and import/pin buildable Jellyfin/Stash/Audiobookshelf source baselines; record exact release/commit/licence and upstream-sync tooling.
- **Track 07:** contracts for ManagedComponent, provider capabilities, AcquisitionBlueprint/DAG, translation/localisation and canonical upstream-state mapping.
- **Tracks 10–13:** first-class five-locale UI, locale-aware search, localised metadata/provenance and professional translation fallback.
- **Tracks 14–15:** unified Acquisition UX, broad Newznab/Torznab/Prowlarr/Jackett provider layer, *Arr transitional automation, SAB/qBit workflows, naming, hardlinks/seeding, release scoring, Wanted/upgrades and Browser Companion/manual fallback.
- **Tracks 17–21:** normal media/adult workflows consume the unified acquisition model rather than exposing separate Sonarr/Radarr/Whisparr product surfaces.
- **Track 24:** verified ISO/disc episode+extra extraction, remux and hand-off to optional derived codec profiles.
- **Tracks 26–28:** complete internal engine cutovers for the already-imported Jellyfin/Stash/Audiobookshelf baselines.
- **Track 30:** normalised backend events, translation jobs and post-processing DAG execution.
- **Track 34:** indexer/translation/browser-companion/provider plugins and extension contracts.
- **Track 35:** managed component updater/rollback, compatibility matrix, storage forecast, bandwidth/resource scheduler and translation cost/queue controls.
- **Track 36:** managed-upstream compatibility E2E, full acquisition→import/disc/transcode flows and 100% first-class locale release gates.
