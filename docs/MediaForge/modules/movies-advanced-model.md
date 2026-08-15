# Filme – Cuts, Editions, Extras und Franchise-Struktur

Priorität: P0 Cut/Edition-Modell / P1 Comparator & UI

## 1. Cut vs. Technical Edition

Die Datenbank unterscheidet strikt:

- Theatrical Cut;
- Director's Cut;
- Extended Cut;
- Final Cut;
- Workprint;
- andere fachlich belegte Varianten.

Darunter technische Editionen:

- UHD Blu-ray Remux;
- 4K WEB;
- 1080p Blu-ray;
- AV1 Encode;
- mobile optimized copy.

Ein 4K-Encode ist kein neuer Cut.

## 2. Edition Comparator

Vergleichsfelder:

- runtime;
- source;
- resolution;
- HDR/DV;
- codec/bit depth;
- audio tracks;
- subtitles;
- file size/bitrate;
- chapter structure.

Unterschiedliche Laufzeiten erzeugen keine automatische Duplicate-/Upgrade-Entscheidung, wenn ein anderer Cut möglich ist.

## 3. Extras

Extras sind echte Objekte:

```text
Deleted Scene
Making Of
Interview
Commentary
Trailer
Gallery
Featurette
```

Sie besitzen eigene Files/Metadata/Relations und werden nicht nur aus einem Dateinamen im `extras/`-Ordner abgeleitet.

## 4. Franchise/Universe

Filme können im Work Graph mit Serien, Büchern, Audiobooks und Soundtracks verbunden werden.

## 5. Best Edition

User/Device Profile wählt automatisch passende Edition, aber keine semantisch andere Cut-Version ohne Benutzerpräferenz.
