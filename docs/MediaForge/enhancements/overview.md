# Enhancement-Überblick

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

MediaForge ist ein lokaler Enhancement Layer für Jellyfin und Audiobookshelf. Die Suite ersetzt keine Player, keine Streaming-Kerne und keine Bibliotheksverwaltung der Ursprungssysteme. Sie erweitert vorhandene Installationen um gemeinsame Oberflächen, Metadaten, Suche, Analyse, Automatisierung, Backups, Health Checks, AI-Funktionen und lokale Verwaltung.

## Architekturprinzip

```mermaid
flowchart TB
    U["MediaForge Enhancement Layer"]
    U --> UI["Unified Dashboard + UI/UX"]
    U --> META["Unified Metadata Engine"]
    U --> SEARCH["Unified Search"]
    U --> OPS["Health / Storage / Backup"]
    U --> AUTO["Workflow / Rule Engine"]
    U --> SDK["Plugin / Extension SDK"]
    UI --> JF["Jellyfin"]
    UI --> ABS["Audiobookshelf"]
    META --> JF
    META --> ABS
    SEARCH --> JF
    SEARCH --> ABS
```

## Verantwortlichkeiten

Jellyfin bleibt zuständig für Video, Musik, Live TV, Adult-Bibliotheken, Playback, Transcoding und Client-Zugriff. Audiobookshelf bleibt zuständig für Hörbücher, Podcasts, Kapitelanzeige, Fortschritt und Wiedergabe. MediaForge speichert lokale Referenzen, IDs, Overrides, Scores, Workflows, Regeln, Audit-Daten, UI-Einstellungen und Suchindexdaten.

## Modulgruppen

- **Experience**: Unified Dashboard, moderne Navigation, UI-/UX-Enhancement, Design-System, Anpassbarkeit.
- **Metadata**: Unified Metadata Engine, Provider-Prioritäten, Konfliktlösung, Versionierung, Rollback.
- **Operations**: Health Center, Storage Analytics, Backup Center, Developer Center.
- **Automation**: Rule Engine, Workflow Engine, Jobs, Queues, Benachrichtigungen.
- **Intelligence**: lokale AI Engine, Embeddings, Kapitelvorschläge, Duplicate Detection, Qualitätsbewertung.
- **Extensions**: Plugin SDK, Connector SDK, optionale Upstream-Beiträge.

## Nicht-Ziele

MediaForge ist kein Jellyfin-Fork, kein Audiobookshelf-Fork, kein eigener Streaming-Server, kein Cloudservice und keine internetzentrierte Medienplattform. Optionale externe Quellen dürfen Kernfunktionen verbessern, aber nicht voraussetzen.
