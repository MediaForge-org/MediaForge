# Detailed Development Phases and Rough Time Ranges

Diese Datei ist Planungsorientierung, **keine Garantie**. Zeiten beziehen sich auf konzentrierte Entwicklung mit Claude-Unterstützung, guter Testdisziplin und ohne größere Upstream-/Hardware-Blocker.

## Phase A – Target Architecture Foundation

**ca. 2–4 Wochen**

- Monorepo-Zielstruktur;
- `apps/server`, `apps/web`;
- React Router;
- Inertia-Ausbau;
- API v1;
- OpenAPI/Event Contracts;
- Gateway;
- Engine Registry Stubs;
- Rust/Python Skeletons;
- Compose/CI-Umstellung;
- schöne Deep Links;
- V2-Funktionalität unverändert erhalten.

## Phase B – V2 Completion / Canonical Catalog

**ca. 1–2 Wochen**

- aktuelle V2-Pakete sauber abschließen;
- Import-/Mapping-Konsistenz;
- Datenmodelltests;
- keine riskanten File Writes.

## Phase C – Security/Privacy Foundation

**ca. 1–2 Wochen**

- Auth Hardening;
- API Policy;
- Audit;
- Zero-Leak-Architektur vorbereiten;
- Secret/Credential Storage;
- Backup-Baseline.

## Phase D – Premium UI System

**ca. 2–4 Wochen**

- Design Tokens;
- App Shell;
- Navigation;
- Home;
- Cards/Hero/Rows/Tables/Modals;
- Skeleton/Error/Empty States;
- Responsive Desktop/TV-Basis;
- Referenzscreens als Quality Gate.

## Phase E – Canonical Media/File/Edition Model

**ca. 2–4 Wochen**

- Work/MediaItem/Edition/File;
- Series orders;
- Movie cuts;
- Audiobook editions/chapters;
- Adult Scene/File/Release lineage;
- Path mappings;
- sidecars.

## Phase F – Metadata Vault/Search/Matching/Review

**ca. 3–6 Wochen**

- feldgenaue Provenienz;
- Source Facts;
- Date Types;
- Review Center;
- Universal Search;
- Smart Collections foundation;
- Library Health.

**Danach sollte MediaForge als Katalog-/Management-App bereits deutlich brauchbar sein.**

## Phase G – Acquisition & Safe Imports

**ca. 3–6 Wochen**

- SABnzbd/qBittorrent Contracts;
- Intake;
- Staging;
- Import Sandbox;
- Rename/Move;
- quality comparison;
- provenance;
- safe automation.

## Phase H – Unified Playback / Existing Engine Integration

**ca. 3–6 Wochen**

- Video/Audio Engine APIs hinter MediaForge;
- unified player;
- progress;
- handoff;
- stream gateway;
- client/device profiles.

## Phase I – Adult Privacy + Metadata Core

**ca. 3–6 Wochen**

- `/adult/...` protected mode;
- Strict Private URLs optional;
- Scene/Performer/Studio/Coverage;
- library-driven sync;
- source history;
- advanced taxonomy schema.

## Phase J – Adult Stash-derived Engine

**ca. 4–8 Wochen**

- upstream import/fork integration;
- media scanner;
- playback/previews/trickplay;
- PostgreSQL/MediaForge mapping;
- UI integration;
- upstream sync tooling.

## Phase K – Adult Full Analysis

**ca. 6–12+ Wochen**, je nach Modellen/Hardware/Genauigkeitsziel

- full video/audio coverage;
- temporal events;
- audio events;
- attributes;
- evidence;
- exact boundaries;
- review/active learning;
- performance tuning.

## Phase L – Disc/ISO

**ca. 4–8+ Wochen**

- ISO/BDMV/VIDEO_TS detection;
- libbluray/media-tools;
- exact runtimes;
- verified-only external reference mapping;
- episode/watch state;
- menu/external player path.

## Phase M – Audiobook Chapter Intelligence + Audio Enhancement

**ca. 4–8 Wochen** für Chapter/Storage Features, **weitere 4–8+ Wochen** für AI Audio Enhancement

- official chapter discovery;
- edition verification;
- CUE/JSON;
- split/merge storage workflow;
- transcript semantic search;
- restoration worker.

## Phase N – Deep Fork/Bundling and Official Distribution

**ca. 6–12 Wochen**

- Jellyfin/ABS/Adult engines vollständig im Monorepo;
- upstream sync process;
- release pipeline;
- Docker images;
- multi-arch;
- SBOM/signing;
- install/upgrade docs.

## Grobe Gesamtsicht

- **erste deutlich brauchbare MediaForge-Version:** ungefähr 2–4 Monate konzentrierte Entwicklung;
- **große integrierte Vision:** eher 8–14+ Monate;
- hochpräzise AI-/Disc-/Fork-Arbeit kann den Zeitraum verlängern.

Diese Werte werden nach jeder größeren Phase anhand realer Velocity neu geschätzt.
