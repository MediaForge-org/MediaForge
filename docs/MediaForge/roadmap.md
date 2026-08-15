# MediaForge Engineering Roadmap

Zurück zur [Masterdatei](MediaForge_Master_Engineering.md). Governance und Frontend-Override: [ADR-0013](adr/0013-react-inertia-typescript-and-roadmap-governance.md).

## Aktueller Status

**Stand August 2026:** V1 ist als lokale Alpha abgeschlossen. V2 ist aktiv; Pakete **V2 A–E** sind umgesetzt, einschließlich des ersten database-only Imports in den kanonischen `media_items`-Katalog. Der reale Lieferstatus wird ausschließlich in [CURRENT_PHASE.md](CURRENT_PHASE.md) gepflegt.

Diese Roadmap beschreibt die langfristige Reihenfolge. Sie darf `CURRENT_PHASE.md` niemals als Ist-Stand überschreiben.

## Produktziel

MediaForge wird langfristig **eine einzige sichtbare Medienanwendung**. Jellyfin, Audiobookshelf und der spätere Stash-derived Adult-Fork werden hinter stabilen Engine-Verträgen integriert. In frühen Phasen bleiben Connectoren der risikoarme Integrationspfad; Bundling/Forks bleiben bewusst spät.

PostgreSQL bleibt dauerhaft der kanonische MediaForge-Persistenzspeicher.

## Usable-Core-Gate

Disc/ISO-Automation, AI-Audio-Upscaling und tiefe Fork-/Bundling-Arbeit dürfen erst beginnen, wenn der normale Kern praktisch brauchbar ist. Das Gate verlangt mindestens:

- stabile Auth/Security und Backup/Restore;
- hochwertige React-/TypeScript-App-Shell;
- kanonisches Media-/Library-/File-Modell;
- normale Movies/Series/Audiobook-Katalog- und Suchflüsse;
- belastbares Matching/Review und Library Health;
- Queue-/Background-Job-Basis;
- reproduzierbare Tests und Migrationen;
- keine offenen kritischen Datenverlust-/Security-Probleme.

Das Gate verhindert, dass spektakuläre Spezialfeatures eine unfertige Basis überdecken.

## Verbindliche Phasen

| Phase | Schwerpunkt | Status |
|---|---|---|
| V0 | Repository, Fundament und Developer Baseline | abgeschlossen |
| V1 | Lokale Core-App und sichere V1-Basis | ausgeliefert (local alpha, V1 A–H) |
| V2 | Connector Suite, Katalog-Snapshots, Normalisierung, Importplan und erster interner Import | **in Arbeit; A–E umgesetzt** |
| V3 | Security Hardening und Privacy Baseline | geplant |
| V4 | React UI/UX Design System und App-Shell | geplant |
| V5 | Internationalisierung und sprachliche Qualität | geplant |
| V6 | Media Model, Library Model, File/Edition Model und Path Mapping | geplant |
| V7 | Metadata Protection Foundation und Never-Touch-Schutz | geplant |
| V8 | Metadata Vault, Source History und Rollback | geplant |
| V9 | Backup, Restore, Disaster Recovery und Blueprints | geplant |
| V10 | Universal Search Local Foundation und Finder UX | geplant |
| V11 | Online-/External-Provider-Suche und Provider Marketplace | geplant |
| V12 | Smart Matching, Review Workbench und Bulk Metadata Review | geplant |
| V13 | Library Integrity, Health Scores und Repair Center — **Usable-Core-Gate** | geplant |
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
| V28 | Disc Detection und Disc Container (ISO/BDMV/VIDEO_TS) | geplant; erst nach Usable-Core-Gate |
| V29 | **Verified-only** Disc Episode Mapping, externe Laufzeitquellen und Menü-Integration | geplant; kein Raten |
| V30 | Enhancement Engines, AI Audio Restoration/Upscaler und Quality Compare | geplant; erst nach Usable-Core-Gate |
| V31 | Adult Privacy Foundation, versteckter Private Mode und Zero Leak | geplant |
| V32 | Adult Metadata Graph, library-driven Sync, Matching Workbench und Adult Media UX | geplant |
| V33 | Plugin SDK, Local AI, Provider Plugins und Metadata Server | geplant |
| V34 | Engine-Forks/Bundling (Jellyfin/ABS/Stash-derived), Ecosystem, Releases und Community-ready Plattform | geplant |

## Fork-Strategie

V34 bleibt bewusst spät. Bis dahin müssen die Engine-Verträge so stabil sein, dass bestehende Jellyfin-/Audiobookshelf-/Stash-Installationen per Adapter funktionieren. V34 darf die Implementierung hinter dem Vertrag austauschen, ohne die MediaForge-UI neu zu erfinden.

Der Adult-Bereich darf in V31/V32 bereits sein kanonisches Datenmodell, UI, Metadatenlogik und Sync-Verhalten entwickeln. Der direkte Stash-Fork wird spätestens in V34 zum integrierten Adult-Engine-Pfad; Vorarbeiten dürfen früher in einem separaten Fork-Repo erfolgen, solange MediaForge-Verträge eingehalten werden.

## Disc-Grundsatz

V29 verwendet keine automatische „wahrscheinlich richtige“ Episodenzuordnung. Eine Playlist wird nur dann automatisch gemappt, wenn die [Disc Verification Policy](modules/disc-verification-policy.md) erfüllt ist. Confidence dient höchstens zur Sortierung eines Reviews, niemals als Autorisierung.

## Adult-Grundsatz

Adult ist im normalen Modus **vollständig unsichtbar**. Kein Menüpunkt und keine gesperrte Home-Kachel. Der Einstieg erfolgt ausschließlich über einen bewusst geschützten Private-Mode-Flow. Details: [adult-enhancement.md](modules/adult-enhancement.md).

## V0-Gate

V0 ist abgeschlossen. Historische Gate-Details bleiben in V1-/Current-Phase-Dokumentation erhalten.
