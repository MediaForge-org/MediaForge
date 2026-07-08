# UI Performance

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

Das UI darf die lokalen Kernsysteme nicht verlangsamen.

## Verbindliche Techniken

- Lazy Loading
- virtuelle Listen
- Bildgrößenoptimierung
- Caching
- Pagination
- API-Request-Minimierung
- Debouncing
- stabile Grid-Dimensionen
- mobile Performance-Budgets
- Tests mit großen Bibliotheken

Animationen bleiben dezent: Fade, Slide, Scale, Skeleton Loader, sanfte Übergänge, Hover-Effekte und Ladezustände. Performance hat Vorrang.

## Querverweise

Backend-Budgets und Query-Muster stehen in [database/query-catalog.md](../database/query-catalog.md), Job-Profile in [architecture/jobs-reference.md](../architecture/jobs-reference.md), UI-Komponentenregeln in [ui/design-system.md](../ui/design-system.md).

## Akzeptanzkriterien

- Große Grids und Tabellen nutzen Pagination oder virtuelle Listen.
- Bildgrößen werden serverseitig oder über klare Derivate begrenzt.
- Filter und Suche debouncen und vermeiden N+1-Request-Muster.
- Mobile Ansichten dürfen keine funktionslosen abgespeckten Versionen sein; sie müssen dieselben Kernworkflows tragen.
