# MediaForge Project Context

Du arbeitest an MediaForge.

MediaForge ist keine neue Medienplattform und kein Ersatz fur Jellyfin oder Audiobookshelf. MediaForge ist eine vollstandig lokale Enhancement Suite, die bestehende Open-Source-Mediensysteme erweitert, verbindet und professionell verwaltbar macht.

## Offizielles Ziel

MediaForge erweitert lokale Installationen von:

- Jellyfin fur Filme, Serien, Musik, Live TV, normale Videobibliotheken, Adult-Bibliotheken, Playback, Transcoding und Jellyfin-seitige Bibliotheksverwaltung
- Audiobookshelf fur Horbucher, Podcasts, optionale Buchverwaltung, Kapitel, Fortschritt und Wiedergabe

MediaForge stellt daruber einen lokalen Enhancement Layer bereit:

- Unified Dashboard
- UI-/UX-Enhancement fur Jellyfin, Audiobookshelf und Adult-Bereiche
- Unified Search
- Unified Metadata Engine
- AI Engine mit lokalem Fokus
- Health Center
- Storage Analytics
- Rule Engine
- Workflow Engine
- Backup Center
- Developer Center
- Plugin SDK und Connector SDK
- lokale Integrations-, Automatisierungs- und Verwaltungsfunktionen

## Lokal bedeutet lokal

Alle Kernfunktionen mussen lokal lauffahig sein. API-Kommunikation meint in dieser Dokumentation lokale Kommunikation zwischen lokalen Diensten, zum Beispiel MediaForge zu lokaler Jellyfin-API, lokaler Audiobookshelf-API, lokaler Laravel-API, PostgreSQL oder Redis. Externe Metadatenquellen und externe AI-Dienste sind hochstens optionale Erweiterungen, nie Pflicht fur den Kernbetrieb.

## Klare Grenzen

MediaForge ersetzt nicht:

- Jellyfin
- Audiobookshelf
- deren Player-, Streaming-, Transcoding- oder Bibliothekskerne
- bewahrte lokale Open-Source-Projekte

Stash ist kein Pflichtsystem. Stash darf als optionale Inspirationsquelle, optionale lokale Datenquelle, optionaler Importer oder Migrationsquelle dokumentiert werden. Der zentrale Adult-Bereich entsteht primar uber Jellyfin plus MediaForge Adult Enhancement.

## Verbindlicher Stack

- Laravel 12
- PHP 8.4
- Vue 3
- TypeScript
- Inertia.js
- Tailwind CSS
- PostgreSQL
- Redis
- Docker Compose
- optionaler lokaler Reverse Proxy

## Feature-Erhalt

Bestehende Features bleiben erhalten und werden in die Enhancement-Architektur eingeordnet, darunter Disc-/Blu-ray-/DVD-Logik, episode-granularer Watch-State, Horbuch-Kapitel-Assembler, CUE-/M4B-Erzeugung, AI Audio Upscaling, Connectoren, Plugin SDK, Connector SDK, Workflow Engine, Rule Engine, Audit-System, Search, Knowledge Graph, Backup/Restore und Health Monitoring.

Lies danach zuerst `docs/MediaForge/MediaForge_Master_Engineering.md` und die dort verlinkten Fachkapitel.
