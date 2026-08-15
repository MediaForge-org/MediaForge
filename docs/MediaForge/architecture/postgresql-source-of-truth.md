# PostgreSQL Source of Truth

## Verbindliche Entscheidung

PostgreSQL ist dauerhaft die kanonische MediaForge-Datenbank. Major Upgrades werden separat und getestet durchgeführt.

## Besitzt PostgreSQL

### Identity
- Work;
- MediaItem;
- Edition/Cut;
- File;
- Provider-/Engine-Mappings;
- Slug History.

### Serien/Filme
- Series/Season/Episode;
- Episode Orders;
- Cuts/Technical Editions;
- Extras;
- Timeline Segments.

### Adult
- Scene/Release/Appearance/Lineage;
- Performer/Studio/Organization History;
- Taxonomy;
- Scene Events/Attributes;
- Analysis Runs/Evidence Metadata;
- Coverage;
- Source Facts.

### Audiobooks
- Work/Edition/Narrator;
- Chapters independent from Files;
- Sidecar generation state;
- Transcript index metadata;
- Bookmarks/Notes.

### Cross-media
- Work Graph;
- Collections;
- Watch Orders;
- Progress mappings.

### Operations
- Acquisition Requests/Import Plans;
- Review;
- Audit;
- Jobs/Checkpoints soweit fachlich dauerhaft;
- Settings/Auth/Privacy.

## Engine Persistence

Fork-Engines dürfen intern eigene Stores besitzen, solange sie als technische Engine-Persistenz behandelt werden. Core liest/schreibt nicht direkt hinein.

## Provenienz

Jedes konfliktanfällige Metadatenfeld kann mehrere Source Facts besitzen. Canonical Choice referenziert Facts statt Herkunft zu verlieren.

## Performance

PostgreSQL bleibt zunächst auch Search-/Graph-Basis. Zusätzliche Such-/Vector-Systeme werden nur bei gemessener Notwendigkeit eingeführt.

## Backup

Backups müssen DB + relevante Sidecar/Config/Key-Metadaten konsistent erfassen. Große Mediafiles werden nicht zwingend in MediaForge-DB-Backups kopiert.
