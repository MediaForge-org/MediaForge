# Work Graph und medienübergreifende Beziehungen

Priorität: P0 Datenmodell / P2 reichhaltige Visualisierung

## Ziel

MediaForge soll nicht nur Dateien kennen, sondern Werke und Beziehungen zwischen verschiedenen Medienformen.

Beispiel:

```text
The Shining (Work cluster)
├── Novel
├── Audiobook editions
├── Film 1980
├── TV/Film adaptation 1997
├── Documentary
└── Soundtrack
```

Oder:

```text
Supernatural Universe
├── TV Series
├── Blu-ray Discs
├── Books
├── Audiobooks
├── Soundtracks
└── Extras
```

## Relations

```text
adaptation_of
based_on
same_franchise
spin_off_of
soundtrack_for
bonus_for
compilation_contains
alternate_release_of
continued_by
prequel_to
sequel_to
```

Jede Relation besitzt Source/Provenienz und kann manuell korrigiert werden.

## Nutzen

- intelligente Collections;
- Franchise Landing Pages;
- Watch Orders;
- Cross-Media Search;
- bessere Discovery;
- gemeinsame Provenienz.

## Keine automatische Vermischung

Zwei Werke mit ähnlichem Titel werden niemals allein aufgrund des Titels verbunden. Identity/Relationship-Matching folgt denselben Evidence-Regeln wie andere Metadaten.
