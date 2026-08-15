# Polyglot Runtime, APIs und Engine-Verknüpfung

## Zielbild

MediaForge darf mehrere Sprachen verwenden, aber nur **eine Fachsprache und einen Satz Contracts** besitzen.

```text
React/TypeScript
      |
      | HTTPS / WS / SSE
      v
Laravel/PHP MediaForge Server
      |
      +------ PostgreSQL
      +------ Redis
      |
      +------ Engine Registry
                 |
         +-------+--------+---------+
         |                |         |
       C#/.NET           Go      Node/TS
       Video            Adult      Audio
         |                |
         +--------+-------+
                  |
                 Rust
              MediaTools
                  |
                Python
                  AI
```

## 1. Browser -> Server

Normale Fachkommunikation läuft über MediaForge API v1. Das Frontend kennt keine Jellyfin-/Stash-/ABS-API direkt.

Beispiele:

```text
GET  /api/v1/home
GET  /api/v1/media/{id}
GET  /api/v1/search
POST /api/v1/playback/sessions
POST /api/v1/libraries/{id}/scan
POST /api/v1/acquisition/intake
GET  /api/v1/jobs/{id}
```

## 2. Server -> Engines

Der Server spricht Engine Contracts. Jede Engine meldet `capabilities()` und `health()`.

Beispiel-Capabilities:

```json
{
  "playback": true,
  "transcoding": true,
  "trickplay": true,
  "audiobookChapters": false,
  "adultSceneAnalysis": false,
  "discMenus": false
}
```

Frontend-Features werden aus MediaForge-DTOs/Capabilities aufgebaut, nicht aus `engine === jellyfin`-Verzweigungen.

## 3. Jobs und Events

Dauerhafte Fachjobs werden serverseitig registriert. Engines/Worker melden Fortschritt als Events:

```text
job.created
job.started
job.progress
job.warning
job.failed
job.completed
```

Domänenevents:

```text
library.scan.progress
analysis.event.detected
analysis.completed
acquisition.download.progress
import.candidate.ready
playback.progress
engine.health.changed
```

Redis ist zunächst Queue-/Realtime-Infrastruktur. NATS/Kafka werden nicht eingeführt, solange gemessene Anforderungen das nicht rechtfertigen.

## 4. Playback

PHP orchestriert, aber streamt die Medienbytes nicht unnötig:

```text
React -> POST playback session -> Laravel
Laravel -> zuständige Engine -> Session
Laravel -> Stream Token/URL -> React
React -> /_stream/... -> Gateway -> Engine
```

Dadurch bleibt eine einzige Origin/URL sichtbar, ohne den PHP-Prozess zum Video-Proxy zu machen.

## 5. AI und MediaTools

Rust und Python sind Worker/Services hinter Contracts.

### Rust

- dekodiert/koordiniert Frames;
- extrahiert PTS/Timestamps;
- verwaltet FFmpeg-Prozesse;
- erzeugt Sprites/Sidecars;
- analysiert Disc-Strukturen;
- erstellt sichere Evidence Assets.

### Python

- erhält Frames/Features/Audio-Chunks;
- führt ML-Inferenz aus;
- liefert Detektionen mit Modellversion/Confidence zurück;
- verändert keine kanonischen Tabellen direkt.

## 6. Contract-Tests

Für jede Sprache existieren Fixtures mit denselben Requests/Responses. CI schlägt fehl, wenn eine Runtime vom Schema abweicht.

Pflichtfelder für interoperable Daten:

- eindeutige IDs;
- UTC-Zeitstempel;
- Dauer in Millisekunden/Microseconds nach definiertem Contract;
- klare Enum-Versionierung;
- Fehlercodes statt nur freie Texte;
- Model-/Detector-Version bei AI-Ergebnissen;
- Source/Provenance bei Metadaten.

## 7. Fehlergrenzen

Eine kaputte Engine darf MediaForge nicht komplett unbrauchbar machen. Der Server meldet Capability-/Health-Degradation und versteckt/markiert nur die betroffenen Funktionen.

Beispiel:

```text
Video Engine offline -> Katalog weiterhin browsbar, Playback deaktiviert
AI Worker offline -> Library/Playback funktioniert, Analyse wartet
Adult Engine locked -> keine Adult-Daten sichtbar
```
