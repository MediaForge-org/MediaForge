# Jellyfin Enhancement

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

Jellyfin bleibt Kernsystem für Filme, Serien, Musik, Live TV, normale Videobibliotheken, Adult-Bibliotheken, Playback, Transcoding und Benutzerzugriff. MediaForge erweitert Jellyfin, ersetzt es aber nicht.

## Enhancement-Bereiche

- modernes lokales Dashboard mit Jellyfin-Daten
- bessere Medienkarten, Grid-Layouts, Listen und Detailseiten
- Hero-Banner, Kapitelübersichten, technische Medieninformationen
- Codec-, Audio-, HDR-, Dolby-Vision-, Untertitel- und Dateiinformationen
- Media Health Score, Metadata Quality Score und Storage-Informationen
- Disc-/Blu-ray-/DVD-Logik mit episode-granularem Watch-State
- bessere Filter, Sortierung, Collections, Dublettenwarnungen und Empfehlungen
- bidirektionaler Watch-State-Sync über lokale Jellyfin-API

## Integrationsgrenze

MediaForge nutzt offizielle lokale Jellyfin-APIs, Webhooks, Plugins, Themes oder reverse-proxy-freundliche Erweiterungspunkte. Quellcode-Änderungen an Jellyfin sind keine Pflicht. Wenn eine tiefe Änderung sinnvoll ist, wird sie als optionale Upstream-, Plugin- oder Fork-Strategie dokumentiert.
