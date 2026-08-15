# Adult UI Enhancement

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).
Fachmodul: [Adult Enhancement](../modules/adult-enhancement.md).
Designbasis: [Design System](design-system.md).

## Kernregel: im normalen Modus unsichtbar

Adult erscheint im normalen MediaForge-UI **nirgendwo**.

Nicht erlaubt:
- „Adult 🔒“-Kachel auf Home;
- gesperrter Adult-Menüpunkt;
- Adult in globaler Suche/Autocomplete;
- Adult in Continue Watching/Recently Added;
- Adult-Stats;
- Notifications/Activity;
- Background-Preloading von Adult-Artwork.

Der geschützte Einstieg liegt in einem absichtlich aufgerufenen Private-Mode-Flow, z. B. Profil → Privater Modus → Passwort/PIN.

## Entsperrter Modus

Nach Entsperrung darf die Navigation einen eigenen Adult-Kontext zeigen:
- Home;
- Library;
- Performers;
- Scenes;
- Studios;
- Collections;
- Coverage;
- Sync/Review.

Das UI bleibt MediaForge und öffnet nicht die originale Stash-Oberfläche.

## Visuelle Qualität

Die Referenzscreens unter `reference/` sind verbindliche Designbaseline:
- `01_home_dashboard.png`
- `02_performer_detail.png`
- `03_scene_detail.png`
- `04_coverage_library_management.png`

Browsing-Seiten sind cinematic und artwork-zentriert. Coverage/Review/Library Management darf dichter sein, muss aber dieselbe Premium-Designsprache behalten.

## Zero-Leak UX

Beim Sperren:
- Adult-Route-State verwerfen;
- Adult-Queries/Cache aus Client-State entfernen;
- keine letzten Adult-Suchbegriffe in normalem Autocomplete;
- keine Adult-Thumbnails im Browser-Preload;
- normaler Home-Feed neu laden;
- sensitive modale Zustände schließen.

Serverseitige Autorisierung bleibt maßgeblich; Client-Cleanup ist zusätzliche Privacy Defense.

## Akzeptanzkriterien

- Screenshot/DOM/Network-Test im gesperrten Modus enthält keine Adult-Entitäten;
- UI nach Entsperren entspricht der Premium-Referenz;
- Lock/Unlock benötigt keinen Wechsel in eine andere Web-App;
- Back/History darf gesperrte Adult-Seiten nicht wieder sichtbar machen.

## Timeline, Taxonomy and Coverage UI

Die Adult-Oberfläche umfasst zusätzlich drei spezialisierte High-End-Workflows:

### Scene Analysis

Referenz `33_adult_scene_full_analysis_timeline.png`:

- Visual/Audio/Speech Lanes;
- exact timestamp seek;
- Evidence;
- Coverage;
- Filter;
- Verification.

### Taxonomy Inspector

Referenz `34_adult_tag_taxonomy_event_inspector.png`:

- Base Tags;
- Attribute Groups;
- Occurrences;
- AI Evidence;
- manual correction;
- training/evaluation hooks.

### Catalog Coverage

Referenz `36_performer_catalog_completeness.png`:

- Known vs Local vs Missing vs Historical vs Unresolved;
- year/studio breakdown;
- missing-scene workflow;
- Source Sync/Acquisition integration.

Diese Screens sind Adult-Mode-only und müssen beim Lock aus Client-State/Cache/History-sensitive UI bereinigt werden, soweit technisch möglich.
