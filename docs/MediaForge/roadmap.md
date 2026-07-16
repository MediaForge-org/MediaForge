# MediaForge Engineering Roadmap

Zurück zur [Masterdatei](MediaForge_Master_Engineering.md). Governance und Frontend-Override: [ADR-0013](adr/0013-react-inertia-typescript-and-roadmap-governance.md).

## Aktueller Status

Die frühere grobe V1–V3-Roadmap ist seit 2026-07-11 **superseded** und nicht mehr arbeitssteuernd. Der finale Master-Prompt ersetzt sie durch 35 interne Engineering-Phasen von V0 bis V34. Diese Phasen sind keine automatischen GitHub-Release-Nummern.

**Stand 2026-07-16:** Das V0-Gate ist grün. Die Engineering-Phase **V1 (Lokale Core-App und sichere V1-Basis)** wurde als lokale Alpha in acht Paketen (V1 A–H) ausgeliefert — Details und Readiness in [CURRENT_PHASE.md](CURRENT_PHASE.md) und [V1_READINESS.md](V1_READINESS.md). V1 ist local alpha, **nicht** production-ready. Alle Phasen ab **V2** bleiben geplant/gesperrt; die untenstehende Phasentabelle bleibt die verbindliche ADR-Governance und wird davon nicht verändert.

## Verbindliche Phasen

| Phase | Schwerpunkt | Status |
|---|---|---|
| V0 | Repository, Fundament und Developer Baseline | abgeschlossen |
| V1 | Lokale Core-App und sichere V1-Basis | ausgeliefert (local alpha, V1 A–H) |
| V2 | Connector Suite, Dashboard und bestehende Dienste übernehmen | nächste Phase |
| V3 | Security Hardening und Privacy Baseline | geplant |
| V4 | React UI/UX Design System und App-Shell | geplant |
| V5 | Internationalisierung und sprachliche Qualität | geplant |
| V6 | Media Model, Library Model und Path Mapping | geplant |
| V7 | Metadata Protection Foundation und Never-Touch-Schutz | geplant |
| V8 | Metadata Vault, Source History und Rollback | geplant |
| V9 | Backup, Restore, Disaster Recovery und Blueprints | geplant |
| V10 | Universal Search Local Foundation und Finder UX | geplant |
| V11 | Online-/External-Provider-Suche und Provider Marketplace | geplant |
| V12 | Smart Matching, Review Workbench und Bulk Metadata Review | geplant |
| V13 | Library Integrity, Health Scores und Repair Center | geplant |
| V14 | Download-Client-Erkennung und externe Download-Dienste | geplant |
| V15 | Manueller NZB-/Torrent-Intake und Import Sandbox | geplant |
| V16 | Import-, Rename- und Move-Engine sowie Naming | geplant |
| V17 | Source-Capped Downloadqualität und Quality Ladder | geplant |
| V18 | Server-Transcoding, optimierte Versionen und Lineage | geplant |
| V19 | Remote Access und Overlay Networks | geplant |
| V20 | Mobile API, Device Tokens und Device Profiles | geplant |
| V21 | React-Native-Mobile-App Alpha und Offline Downloads | geplant |
| V22 | Realtime Watch-State und Playback Handoff | geplant |
| V23 | Desktop Server App als Docker-Alternative | geplant |
| V24 | Electron Desktop Client | geplant |
| V25 | Tauri Desktop Client | geplant |
| V26 | High-Fidelity Playback Client und Do-Not-Disturb Streaming | geplant |
| V27 | Streaming Advisor, Resource Monitor und GPU Manager | geplant |
| V28 | Disc Detection und Disc Container | geplant |
| V29 | Menu-Aware Disc Episode Mapping | geplant |
| V30 | Enhancement Engines und Quality Compare | geplant |
| V31 | Adult Privacy Foundation, Safe Mode und Zero Leak | geplant |
| V32 | Adult Metadata Graph, Matching Workbench und Metadatenrecherche | geplant |
| V33 | Plugin SDK, Local AI, Provider Plugins und Metadata Server | geplant |
| V34 | Forks, Ecosystem, Releases und Community-ready Plattform | geplant |

## V0-Gate

V0 ist erst abgeschlossen, wenn Laravel lokal startet, Composer- und NPM-Lockfiles reproduzierbar sind, React/Inertia/TypeScript baut, PostgreSQL und Redis erreichbar sind, Root- und Dev-Compose gültig sind, Tests/Pint/PHPStan grün sind, das Setup dokumentiert ist und keine Secrets oder lokalen Artefakte getrackt werden.

Vor diesem Gate werden keine V1-Funktionen und keine Teile späterer Phasen implementiert.
