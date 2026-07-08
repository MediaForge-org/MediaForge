# Audiobookshelf UI Enhancement

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

MediaForge verbessert Audiobookshelf visuell und funktional, ohne Audiobookshelf zu ersetzen.

## Ziele

- moderne Bibliothek
- Serienseiten
- Sprecherseiten
- Kapitelansicht
- Fortschrittsübersicht
- Timeline
- Statistiken und Hörzeit
- Empfehlungen
- Cover-Optimierung
- Kapitelqualität
- CUE-Status
- Hörspiel-Logik
- Metadatenqualität
- Dublettenwarnungen

## Querverweise

Fachliche Grundlagen stehen in [enhancements/audiobookshelf-enhancements.md](../enhancements/audiobookshelf-enhancements.md), Connector-Details in [connectors/audiobookshelf.md](../connectors/audiobookshelf.md), Kapitel- und CUE-Arbeit in [modules/audiobook-assembler.md](../modules/audiobook-assembler.md). Das allgemeine Design-System bleibt [ui/design-system.md](../ui/design-system.md).

## Akzeptanzkriterien

- ABS bleibt Player und Fortschrittsquelle; MediaForge ergänzt Analyse, Kapitelarbeit und kuratierte Ansichten.
- Kapitelqualität, CUE-Status und Metadatenqualität sind sichtbar, aber nicht mit Fehlerzuständen verwechselt.
- Lange Hörbücher und große Serien müssen performant navigierbar bleiben.
- UI-Verbesserungen dürfen keine direkte Änderung an ABS-internen Datenbanken voraussetzen.
