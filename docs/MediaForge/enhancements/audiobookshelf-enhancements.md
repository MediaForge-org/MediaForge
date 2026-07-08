# Audiobookshelf Enhancement

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

Audiobookshelf bleibt Kernsystem für Hörbücher, Podcasts, optionale Buchverwaltung, Kapitel, Hörfortschritt und Wiedergabe. MediaForge erweitert Audiobookshelf um Metadaten-, Kapitel-, Analyse-, UI- und Workflow-Funktionen.

## Enhancement-Bereiche

- moderne Bibliotheks- und Serienseiten
- Sprecherseiten, Kapitelansicht, Fortschrittsübersicht und Timeline
- Hörzeit-Statistiken, Empfehlungen und Metadatenqualität
- Kapitelgenerator, CUE Builder und M4B-Artefakte
- Hörspiel-Erkennung, Multi-CD- und Track-Zuordnung
- Cover-Optimierung, Kapitelqualität und Dublettenwarnungen
- Audible-, OpenLibrary-, Google-Books- oder iTunes-Quellen nur optional und als Provider markiert
- lokaler Fortschritts-Sync über Audiobookshelf-API

## Integrationsgrenze

Audiobookshelf bleibt Player und Bibliothekskern. MediaForge erzeugt und verwaltet Enhancement-Daten, Artefakte und Reviews, schreibt aber nicht an Audiobookshelf vorbei in dessen interne Datenhaltung.
