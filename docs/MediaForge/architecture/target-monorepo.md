# Target Monorepo Architecture

Status: **verbindliche Zielarchitektur**
Gilt für: neue Architekturarbeit ab August 2026
Historischer Ist-Stand bleibt in `CURRENT_PHASE.md` maßgeblich.

## 1. Ziel

MediaForge wird als **ein einziges GitHub-Repository und ein einziges sichtbares Produkt** entwickelt. Das Repository darf mehrere Programmiersprachen enthalten. Die Grenze wird nach fachlicher Verantwortung gezogen, nicht nach Sprache.

Der Benutzer installiert und öffnet **MediaForge**. Jellyfin-, Stash- und Audiobookshelf-derived Komponenten sind interne Engines. Ihre ursprünglichen Web-UIs sind höchstens Developer-/Fallback-Werkzeuge, aber keine normale Produktoberfläche.

## 2. Root-Struktur

```text
MediaForge/
├── apps/
│   ├── server/                 # PHP 8.4 + Laravel – Control Plane / API
│   ├── web/                    # React 19 + TypeScript + React Router Framework Mode + Vite
│   ├── desktop/                # später: Desktop-Client
│   ├── mobile/                 # später: Mobile-Client
│   └── tv/                     # später: TV-Client
│
├── engines/
│   ├── video/                  # Jellyfin-derived – C#/.NET
│   ├── adult/                  # Stash-derived – Go
│   └── audio/                  # Audiobookshelf-derived – Node/TypeScript
│
├── services/
│   ├── media-tools/            # Rust – native Media-Pipeline / FFI / Worker
│   └── ai/                     # Python – ML/AI Inference und Training
│
├── packages/
│   ├── contracts/              # OpenAPI, JSON Schema, Events
│   ├── sdk/                    # generierte/handgeschriebene Clients
│   ├── media-model/            # gemeinsame kanonische Begriffe/Schema-Artefakte
│   ├── localization/           # UI-i18n, Glossare, Translation Memory, Locale-QA
│   ├── design-tokens/          # Farben, Typo, Spacing, Motion
│   ├── ui-web/                 # wiederverwendbare React-Komponenten
│   └── icons/                  # MediaForge Icon-Set
│
├── platform/
│   ├── docker/                 # Dockerfiles je Runtime-Komponente
│   ├── compose/                # dev/prod/test Compose
│   ├── gateway/                # Reverse Proxy / Routing
│   ├── database/               # PostgreSQL Bootstrap / Backup / Maintenance
│   ├── managed-upstreams/      # SAB/qBit/Prowlarr/*Arr manifests + compatibility
│   ├── observability/          # Logs, Metrics, Traces, Health
│   └── releases/               # Release-/Image-/SBOM-Automation
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
│   ├── upstream-sync/
│   ├── migrations/
│   ├── release/
│   └── dev/
│
├── docs/
├── compose.yaml
├── Makefile
├── README.md
├── CONTRIBUTING.md
└── LICENSE
```

## 3. Warum ein Monorepo

Das Monorepo ist eine bewusste Produktivitätsentscheidung:

- Claude sieht Server, Frontend, Engine-Verträge und Worker gleichzeitig.
- Eine Vertragsänderung kann in einem Pull Request über PHP, TypeScript, Go, C#, Rust und Python aktualisiert werden.
- Cross-Engine-E2E-Tests laufen aus einem Checkout.
- UI und Backend können nicht still auseinanderlaufen.
- Docker-/Release-Artefakte werden aus einem konsistenten Commit gebaut.
- Architekturentscheidungen liegen an einer Stelle.

Das Monorepo bedeutet **nicht**, dass alle Prozesse zu einer Binary verschmelzen. Die Engines bleiben getrennte Prozesse, damit Lizenz-, Runtime-, Fehler- und Update-Grenzen sauber bleiben.

## 4. Apps

### `apps/server`

Laravel ist das MediaForge Control Plane und besitzt vor allem:

- Authentifizierung, Benutzer, Rollen, Sessions;
- kanonischen Katalog und MediaForge-ULIDs;
- Bibliotheken, Editionen, Dateien, Source-/Provider-Mappings;
- Suche/Filter-API;
- Collections und Work Graph;
- Metadata Vault, Provenienz, Review;
- Engine Registry und Capability Discovery;
- Acquisition-Orchestrierung;
- Queue-/Job-Steuerung;
- Privacy/Adult Mode;
- Audit, Settings, Health und Backup-Koordination;
- öffentliche API v1.

Laravel **transcodiert kein Video**, decodiert keine Blu-ray und führt keine großen ML-Modelle aus.

### `apps/web`

Das Web-Frontend ist eine echte React-App:

- React 19;
- TypeScript;
- React Router Framework Mode;
- Vite;
- Tailwind + MediaForge Design System;
- MediaForge API v1;
- WebSocket/SSE für Live-Status.

**Inertia ist keine Zielarchitektur mehr.** Es darf während der Migration kurzfristig existieren, aber neue Ziel-UI-Funktionen werden nicht an Inertia gebunden.

### spätere Clients

Desktop/Mobile/TV sprechen dieselbe API und dieselben Playback-/Event-Verträge. Keine Fachfunktion darf nur deshalb ausschließlich im Web verfügbar sein, weil sie direkt an einen Inertia-Controller gekoppelt wurde.

## 5. Engines

### `engines/video`

Jellyfin-derived C#/.NET-Engine für:

- Streaming;
- Transcoding;
- Codec-/Client-Profile;
- Untertitel;
- Video-/TV-Playback;
- Live-TV optional;
- technische Video-Library-Fähigkeiten.

MediaForge besitzt UI und kanonischen Katalog. Engine-IDs sind nur Mappings.

### `engines/adult`

Stash-derived Go-Engine für:

- Adult-File-Scan;
- Scene/Performer/Studio Media-Core;
- FFmpeg-Integration;
- Fingerprints;
- Thumbnails/Preview/Trickplay;
- Adult Streaming;
- lokale Scene-Media-Pipeline.

Die detailliertere Taxonomie, Provenienz, Full-Analysis-Timeline und MediaForge-spezifische Metadatenlogik werden über MediaForge Contracts integriert.

### `engines/audio`

Audiobookshelf-derived Node/TypeScript-Engine für:

- Hörbuch-/Podcast-Playback;
- Kapitel und Audiofiles;
- Listen-State;
- Audio-spezifische Bibliotheksfunktionen.

## 6. Services

### `services/media-tools` – Rust

Neue native MediaForge-eigene Systemkomponenten werden standardmäßig in Rust gebaut, sofern keine bestehende Library einen zwingenden anderen Weg vorgibt.

Aufgaben können sein:

- Filesystem-Scanning;
- Hashing;
- Fingerprint-Orchestrierung;
- genaue PTS-/Timeline-Verarbeitung;
- FFmpeg/libbluray/libdvdnav-Anbindung;
- Thumbnail/Trickplay-Pipeline-Helfer;
- Disc-Strukturanalyse;
- Sidecar-Generatoren;
- schnelle Transformationen und IPC.

C/C++-Libraries dürfen über FFI verwendet werden. Eigener C++-Code ist **kein Pflichtbestandteil**.

### `services/ai` – Python

Python wird nur dort eingesetzt, wo das ML-Ökosystem klar überlegen ist:

- Audio Event Detection;
- Visual/Temporal Event Detection;
- Multimodal Fusion;
- Speech-to-Text;
- Embeddings;
- Audio Restoration;
- spätere personalisierte/fine-tuned Modelle.

## 7. Packages und Contracts

`packages/contracts` ist eine der wichtigsten Monorepo-Grenzen.

### API Contract

```text
packages/contracts/api/mediaforge-v1.openapi.yaml
```

### Engine Contract

```text
packages/contracts/engines/engine-v1.openapi.yaml
```

### Events

```text
packages/contracts/events/
├── playback.started.schema.json
├── playback.progress.schema.json
├── library.scan.progress.schema.json
├── analysis.progress.schema.json
├── acquisition.progress.schema.json
└── engine.health.schema.json
```

Alle Sprachen verwenden daraus generierte oder contract-getestete Clients. Keine Engine darf still ein abweichendes Feld erfinden.

## 8. Datenbank

PostgreSQL bleibt dauerhaft MediaForge Source of Truth für:

- MediaItem/Work/Edition/File;
- Scene/Performer/Studio Canonical IDs;
- Audiobook Work/Edition/Chapter;
- Serien/Episoden/Orders;
- Source Facts und Provenienz;
- Tags/Taxonomy/Events;
- Acquisition/Import Lineage;
- Collections/Work Graph;
- Playback/Progress-Mappings;
- Privacy/Auth/Audit.

Engine-interne Persistenz darf während Fork-/Migration bestehen, ist aber niemals Eigentümer der MediaForge-Identität.

## 9. Kein direkter DB-Zugriff zwischen Komponenten

Verboten:

```text
Laravel -> direkt in Jellyfin DB schreiben
Adult Engine -> MediaForge Tabellen direkt verändern
AI Worker -> PostgreSQL Business-Tabellen selbst mutieren
```

Stattdessen:

```text
Engine/Worker -> Contract/API/Event -> MediaForge Server -> PostgreSQL
```

Ausnahmen für rein technische, bewusst definierte Shared Stores müssen als ADR dokumentiert werden.

## 10. Upstream-Forks im Monorepo

Die Forks sollen im selben GitHub-Repository liegen, aber ihre Upstream-Historie und Lizenzgrenzen müssen nachvollziehbar bleiben.

Bevorzugte Strategie:

- dedizierte Upstream-Remotes;
- kontrollierte subtree/vendor-history-Imports;
- keine undurchsichtigen Git-Submodules als normale Developer-Abhängigkeit;
- `engines/<name>/UPSTREAM.md` mit Upstream-URL, Commit, Lizenz, Importdatum und Sync-Prozess;
- MediaForge-spezifische Integrationsschicht möglichst in klar markierten Verzeichnissen.

Beispiel:

```text
engines/adult/
├── ... upstream-derived Stash code ...
└── mediaforge/
    ├── integration/
    ├── events/
    ├── api/
    └── compatibility/
```

## 11. Migration vom heutigen Repo

Die Umstrukturierung ist ein eigener Architecture-Foundation-Schritt:

1. Contracts und Zielordner anlegen.
2. API v1 definieren.
3. React Router App-Shell aufbauen.
4. bestehende React-Seiten schrittweise aus `resources/js` nach `apps/web` verschieben.
5. Laravel nach `apps/server` verschieben, ohne fachliche Funktionalität zu verlieren.
6. Inertia-Endpunkte durch API-/Router-Flows ersetzen.
7. Gateway einführen.
8. Stub Engine Registry und Contract Tests anlegen.
9. Rust/Python Service-Skeletons anlegen.
10. Compose/CI auf Monorepo umstellen.

Währenddessen bleibt `CURRENT_PHASE.md` die Wahrheit über den ausgelieferten Funktionsumfang.

## 12. Definition of Done

Die Foundation gilt als abgeschlossen, wenn:

- Web-App ohne Inertia-Seitenabhängigkeit navigiert;
- API v1 contract-getestet ist;
- server/web getrennt buildbar sind;
- Gateway `localhost:8100` bereitstellt;
- PostgreSQL/Redis funktionieren;
- Engine Registry mindestens Stubs/Health unterstützt;
- Docker Compose und CI grün sind;
- keine existierende V2-Katalogfunktion verloren ging;
- schöne Deep Links direkt im Browser geladen/reloaded werden können.

## 18. Erweiterungen 2026-08-16

Die Root-Struktur wird ergänzt um:

```text
packages/plugin-sdk/
packages/theme-sdk/
packages/contracts/domains/anatomy/
packages/contracts/domains/reconstruction/
packages/contracts/domains/plugins/
platform/storage/
services/media-tools/crates/mesh/
services/media-tools/crates/evidence/
services/ai/reconstruction/
services/ai/evaluation/
```

Web-Ziel ist **React Router Framework Mode**, nicht nur ein nackter Router und nicht Next.js als zweite Full-Stack-Serverruntime. Details: `frontend-framework.md`.

Große AI/3D-Funktionen sind Capability-gesteuert und optional. Große Binärartefakte liegen nicht in PostgreSQL, sondern im content-addressed Artifact Store. Details: `ai-capabilities-model-registry.md` und `artifact-store-and-derived-assets.md`.

## 17. Upstream integration update — 17 August 2026

The detailed policy is defined by `managed-upstreams-and-product-surface.md` and ADR-0025.

- Jellyfin, Stash and Audiobookshelf are imported as pinned source baselines during Track 02 so later contracts can be designed against their real capabilities. Tracks 26–28 complete the cutover rather than first importing the projects.
- SABnzbd, qBittorrent, Prowlarr, Sonarr, Radarr and Whisparr are managed upstream services: upstream code remains unmodified by default while MediaForge owns lifecycle, compatibility, normalised API/events and the normal product UI.
- Prowlarr/SAB/qBittorrent may remain long-term backend components. Sonarr/Radarr/Whisparr are transitional automation providers whose product-level Wanted/Release/Upgrade functions can progressively move into MediaForge.
- Normal users see MediaForge concepts, not a collection of embedded product surfaces. Native upstream UIs are advanced/admin fallbacks only.
- `packages/localization` owns first-class UI locale resources, glossary/translation-memory contracts and localisation QA.
