# Jellyfin UI Enhancement

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

MediaForge verbessert Jellyfin visuell und funktional, ohne Jellyfin zu ersetzen.

## Ziele

- modernes Dashboard
- bessere Startseite
- bessere Medienkarten und größere Poster
- flexible Grid- und Listenansichten
- Hero-Banner und hochwertige Detailseiten
- bessere Bibliotheksnavigation
- bessere Suche, Filter, Sortierung und Collections
- technische Medieninformationen
- Health Score, Storage-Informationen und Codec-Informationen
- Audio-, HDR-/Dolby-Vision-, Kapitel-, Disc- und Dateiinformationen
- Watch-State und Empfehlungen

Die Umsetzung bevorzugt offizielle Erweiterungspunkte, Themes, Plugins, CSS-/JavaScript-Erweiterungen und reverse-proxy-freundliche Integration.

## Querverweise

Fachliche Grenzen stehen in [enhancements/jellyfin-enhancements.md](../enhancements/jellyfin-enhancements.md), Connector-Details in [connectors/jellyfin.md](../connectors/jellyfin.md), Disc-spezifische UI in [modules/disc-engine/ui-reference.md](../modules/disc-engine/ui-reference.md).

## Akzeptanzkriterien

- Jellyfin bleibt Player, Streaming- und Transcoding-System.
- UI-Erweiterungen dürfen Jellyfin-Updates nicht unnötig blockieren.
- Disc-, Codec- und Health-Daten werden aus MediaForge ergänzt, nicht in Jellyfin erzwungen.
- Adult-Bibliotheken nutzen dieselben Sichtbarkeitsregeln wie das Adult Enhancement.
