# Storage Analytics

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

Storage Analytics macht lokale Speicherbelegung und Medienqualität sichtbar.

## Auswertungen

- Speicherverbrauch
- größte Dateien
- Codec-Verteilung
- Bitrate
- HDR und Dolby Vision
- Audioformate
- Containerformate
- Wachstum über Zeit
- Einsparpotential
- Transcoding Advisor
- Speichertrends
- Bibliotheksvergleich
- Medienqualitätsverteilung

Die Analysen lesen lokale Dateien und Metadaten; sie ersetzen keine Jellyfin- oder Audiobookshelf-Bibliotheken.

## Querverweise

Storage Analytics nutzt Daten aus [database/core-schema.md](../database/core-schema.md), [modules/audio-analysis.md](../modules/audio-analysis.md), [modules/data-quality.md](../modules/data-quality.md), [modules/dedup-fingerprinting.md](../modules/dedup-fingerprinting.md) und [modules/health-monitoring.md](../modules/health-monitoring.md).

## Akzeptanzkriterien

- Analysen laufen read-only gegen Medienbibliotheken.
- Große Bibliotheken werden paginiert, gecacht und über Jobs analysiert.
- Adult-/Restricted-Bibliotheken erscheinen nur aggregiert oder für berechtigte Benutzer.
- Empfehlungen wie Transcoding Advisor sind Vorschläge, keine automatischen Medienänderungen.
