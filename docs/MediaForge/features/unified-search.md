# Unified Search

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

Unified Search durchsucht lokal Jellyfin, Audiobookshelf, Adult-Bereiche und MediaForge-eigene Enhancement-Daten.

## Suchräume

- Titel und Dateinamen
- Tags, Personen, Sprecher, Performer und Studios
- Collections und Serien/Reihen
- Kapitel, Untertitel und Notizen
- technische Metadaten
- Health-, Qualitäts- und Storage-Daten

## Funktionen

Die Suche bietet lokale Indexierung, Filter, gespeicherte Suchen, Favoriten, Schnellaktionen, Ergebnisgruppen und Relevanzbewertung. Externe Suchdienste sind keine Pflicht.

## Querverweise

Die technische Suchspezifikation steht in [modules/search.md](../modules/search.md), die Embedding-Regeln in [modules/search/embedding-spec.md](../modules/search/embedding-spec.md), die Datenbankentscheidung in [adr/0010-postgres-hybrid-search.md](../adr/0010-postgres-hybrid-search.md).

## Akzeptanzkriterien

- Lexikalische Suche funktioniert ohne AI Worker und ohne externe Suchserver.
- Semantische Suche ist additive Verbesserung und klar als lokal/optional dokumentiert.
- Ergebnisgruppen respektieren Jellyfin-, Audiobookshelf-, Adult- und MediaForge-Systembereiche.
- Berechtigungen werden bereits in der Query angewendet, nicht nur im UI ausgeblendet.
