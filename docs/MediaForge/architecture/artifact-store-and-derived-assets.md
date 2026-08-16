# Analysis Artifact Store and Derived Assets

Status: **P0 Architektur, P1/P2 Implementierung je Feature**

## Grundsatz

Große Binärartefakte gehören nicht in PostgreSQL. PostgreSQL speichert Identität, Hash, Typ, Provenienz, Modellversion, Größe und Storage-Key. Große Daten liegen in einem content-addressed Artifact Store.

Beispiele:

- Evidence Frames;
- Thumbnails/Trickplay;
- Audio Features;
- Transcripts;
- Embeddings;
- Tattoo Masks;
- 3D Meshes;
- Texturen;
- Reconstruction-Derivate;
- temporäre Analyseartefakte.

## Content addressing

```text
/data/derived/sha256/ab/cd/<full-hash>
```

Identische Artefakte werden dedupliziert. Hashes dienen zugleich der Integritätsprüfung.

## Lebenszyklen

Artefakte werden klassifiziert als:

- `canonical-derived`: teuer/benutzerrelevant, standardmäßig behalten;
- `reproducible-cache`: jederzeit regenerierbar;
- `evidence`: für Nachvollziehbarkeit/Verifikation;
- `temporary`: nach erfolgreichem Job löschbar;
- `private-adult`: zusätzlicher Privacy-Namespace und Adult-Lock-Regeln.

## Storage Budget

Der Nutzer kann Limits getrennt konfigurieren:

```text
Models
3D/Reconstruction
Evidence
Thumbnails/Trickplay
Embeddings
Transcripts
Temporary cache
```

GC-Reihenfolge: temporary -> reproducible cache -> alte unreferenzierte Revisionen. Originalmedien werden niemals durch Derived-Asset-GC gelöscht.

## Optionalität

Schwere AI-/3D-Artefakte entstehen nur, wenn die entsprechende Capability explizit aktiviert ist.
