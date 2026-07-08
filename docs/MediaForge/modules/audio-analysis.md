# Audioanalyse und Audioverbesserung

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [architecture/overview.md](../architecture/overview.md) (media-tools, Queues), [database/core-schema.md](../database/core-schema.md) (Artefakte). Konsumenten: [Assembler](audiobook-assembler.md) (Stillefenster, Laufzeiten), [Upscaler](audio-upscaler.md) (Metriken, Preflight), [Fingerprinting](dedup-fingerprinting.md) (Chromaprint-Rohdaten), [Disc-Engine](disc-engine.md) (perspektivisch Intro-Erkennung).

## Motivation

Vier Module brauchen dieselben Antworten über Audiodateien: Wie lang ist das wirklich (nicht laut Header)? Wo ist Stille? Wie ist die Qualität (Bandbreite, Rauschen, Clipping, Lautheit)? Wie klingt es als Fingerprint? Ohne zentrales Analysemodul würde jedes Fachmodul eigene ffprobe/FFmpeg-Aufrufe mit eigenen Parametern, eigenem Caching und eigenen Bugs pflegen. Die Audioanalyse ist deshalb ein Fundament-nahes Dienstmodul: eine Messstelle, ein Metrik-Schema, ein Cache — viele Konsumenten. „Audioverbesserung" im Sinne tatsächlicher Signaltransformation liegt bewusst **nicht** hier, sondern im [Upscaler](audio-upscaler.md) (ML-basiert) bzw. in dessen klassischen Filter-Profilen; dieses Modul misst und beschreibt, es verändert nie.

## Problemstellung

**Messkosten vs. Aktualität.** Vollständige Analysen (Dekodieren, Stille-Suche, Lautheits-Integration) kosten ~0.3–0.5× Echtzeit; eine 40-Stunden-Hörbuch-Edition sind Stunden CPU. Ergebnisse müssen deshalb aggressiv gecacht, an Content-Hashes gebunden und in Stufen abrufbar sein (billige Header-Stufe vs. teure Dekodier-Stufe) — Konsumenten deklarieren, welche Stufe sie brauchen, nichts wird „auf Vorrat" in teuerster Stufe gemessen.

**Vergleichbarkeit.** Der Upscaler vergleicht vorher/nachher; die Dublettenerkennung vergleicht Kandidaten. Das geht nur mit einem versionierten, stabilen Metrik-Schema: Gleiche Datei + gleiche Analyzer-Version ⇒ identische Zahlen. Jede Änderung der Messmethodik ist ein Versionssprung, nie eine stille Drift.

**Zeitreihen vs. Kennzahlen.** Stillefenster, Lautheitsverlauf und Spektral-Zeitreihen sind groß (zehntausende Fenster bei langen Werken); Kennzahlen (integrierte Lautheit, effektive Bandbreite) sind klein. Beides in einer Tabelle wäre entweder aufgebläht oder verstümmelt.

## Analyse bestehender Lösungen

**FFmpeg-Filterökosystem** liefert alle Messprimitive produktionsreif: `silencedetect` (Stille), `ebur128` (Lautheit nach EBU R128), `astats` (Clipping/Peaks/DC), `aspectralstats`/FFT-Auswertung (Bandbreite), `volumedetect`. Die Kunst ist Orchestrierung und Parametrisierung, nicht Signalverarbeitung — genau die MediaForge-Stack-Philosophie (PHP orchestriert, native Werkzeuge rechnen). **Audiobookshelf** misst fast nichts (Header-Laufzeiten) — Quelle bekannter ABS-Probleme mit VBR-Dateien; die Bestätigung, dass die Dekodier-Fallback-Regel des Assemblers nötig ist. **beets** (Musikverwaltung) zeigt ein reifes Plugin-Analyse-Muster (ReplayGain/R128 als nachgelagerte Jobs mit DB-Cache) — strukturell übernommen. **Chromaprint/fpcalc** ist der De-facto-Standard für Audio-Fingerprints und wird hier als Analysestufe erzeugt, aber im [Fingerprinting-Modul](dedup-fingerprinting.md) fachlich verwendet.

## Architekturentscheidung

Die Analyse ist als **gestuftes Messprotokoll** organisiert; jede Stufe hat definierte Kosten, definierten Output und einen eigenen Cache-Eintrag:

| Stufe | Inhalt | Kosten | Persistenz |
|---|---|---|---|
| `probe` | Container/Codec/Kanäle/Samplerate/Header-Laufzeit | ~ms | `audio_probes`-Zeile |
| `duration` | exakte Laufzeit (Dekodier-Zählung), nur bei Divergenz-Verdacht | ~0.1× RT | Feld in `audio_probes` |
| `quality` | Kennzahlen: effektive Bandbreite, Rauschteppich, Clipping-Rate, LUFS-integriert, True Peak, Dynamik | ~0.3× RT | `audio_quality_reports`-Zeile |
| `timeline` | Zeitreihen: Stillefenster, Lautheitsverlauf, Spektral-Frames | wie `quality` (ein Durchlauf misst beide) | `analysis_report`-**Artefakt** (JSON) |
| `chromaprint` | Audio-Fingerprint (fpcalc) | ~0.05× RT | Übergabe an Fingerprinting-Modul |

Alle Stufen laufen im media-tools-Container (ein FFmpeg-Durchlauf misst `quality` und `timeline` gemeinsam — Filtergraph mit `silencedetect`+`ebur128`+`astats`+FFT, ein Dekodier-Pass, mehrere Ergebnisse); PHP steuert über den `AudioAnalyzerInterface`-Vertrag. Cache-Schlüssel ist `(file.content_hash, stufe, analyzer_version)` — Hash-Invalidierung des Fundaments räumt automatisch mit auf. Das Metrik-Schema ist versioniert (`audio-metrics/v1`, bereits vom Upscaler referenziert); die Kennzahlen-Definitionen sind normativ:

* `bandwidth_hz`: höchste Frequenz, deren mittlere Energie über dem Rauschteppich + 10 dB liegt (Median über Frames der lautesten Dezile) — die ehrliche „effektive" Bandbreite, robust gegen einzelne Ausreißer-Frames.
* `noise_floor_db`: 5. Perzentil der Frame-RMS in Nicht-Stille-Regionen.
* `clipping_ratio`: Anteil Samples an Full Scale (±1 Sample-Toleranz) über gleitende Fenster.
* `lufs_integrated`, `true_peak_db`, `lra` (Loudness Range): direkt aus `ebur128`.
* `silence_windows`: Liste `[{start_ms, end_ms, floor_db}]` mit Schwelle −35 dB relativ zum Programm-Median, Mindestlänge 800 ms — die Parameter, auf die sich Assembler-Snapping und KI-Kapitelheuristik verlassen.

## Alternativen

**Analyse in jedem Fachmodul** (Status quo ante): Duplikation, inkonsistente Zahlen zwischen Modulen — verworfen. **Externe Analyse-Datenbank** (Essentia/AcousticBrainz-Stil mit hunderten Deskriptoren): wissenschaftlich reizvoll, aber 95 % der Deskriptoren hätten keinen Konsumenten; das Modul misst nur, was gebraucht wird, und ist erweiterbar, wenn ein Konsument kommt. **Zeitreihen in Postgres-Zeilen** (Fenster als Rows): Millionen Zeilen ohne relationalen Nutzen — Zeitreihen sind Artefakt-JSON (Regel-8-konform: nie gejoint, von Konsumenten als Ganzes gelesen).

## Datenmodell und SQL-Schema

```sql
CREATE TABLE audio_probes (
    id               CHAR(26) PRIMARY KEY,
    file_id          CHAR(26)    NOT NULL REFERENCES files(id) ON DELETE CASCADE,
    content_hash     TEXT        NOT NULL,           -- Bindung an den Dateiinhalt
    analyzer_version TEXT        NOT NULL,
    container        TEXT,
    codec            TEXT,
    sample_rate      INTEGER,
    channels         INTEGER,
    bit_rate         INTEGER,                        -- nominal, aus Header
    header_duration_ms BIGINT,
    exact_duration_ms  BIGINT,                       -- NULL bis duration-Stufe lief
    duration_method  TEXT NOT NULL DEFAULT 'header'
        CHECK (duration_method IN ('header','decoded')),
    probed_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (file_id, content_hash, analyzer_version)
);

CREATE TABLE audio_quality_reports (
    id               CHAR(26) PRIMARY KEY,
    file_id          CHAR(26)    NOT NULL REFERENCES files(id) ON DELETE CASCADE,
    content_hash     TEXT        NOT NULL,
    analyzer_version TEXT        NOT NULL,
    metrics          JSONB       NOT NULL,           -- audio-metrics/v1-Kennzahlen (klein, ~1 KB)
    timeline_artifact_id CHAR(26) REFERENCES artifacts(id) ON DELETE SET NULL,
    measured_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (file_id, content_hash, analyzer_version)
);
```

Editions-aggregierte Sichten (Gesamtqualität eines 97-Track-Hörbuchs) werden zur Lesezeit über die Track-Zugehörigkeit aggregiert (Minimum der Bandbreiten, gewichtete Lautheit); ein Cache dafür entsteht erst bei nachgewiesenem Bedarf (offener Punkt).

## Laravel-Klassen

| Klasse | Typ | Vertrag |
|---|---|---|
| `AudioAnalyzerInterface` | Interface | `probe(File): AudioProbe`, `measureQuality(File): QualityReport`, `chromaprint(File): FingerprintData` — Implementierung `MediaToolsAudioAnalyzer` |
| `AudioMetrics` | DTO | typisierte Kennzahlen; implementiert `AudioMetricsInterface` des Upscalers |
| `AnalyzeAudioFileJob` | ResumableJob (`analyze`) | Stufen als Schritte (`probe`, `duration-if-needed`, `quality`, `chromaprint-if-requested`); Cache-Prüfung vor jeder Stufe |
| `AudioAnalysisRequested` | Event/Mechanik | Konsumenten fordern Stufen an (`RequestAudioAnalysis`-Action mit Stufen-Set); das Modul dedupliziert Anforderungen über den Cache-Schlüssel |
| `SilenceMap` | DTO | Stillefenster-Zugriff für Assembler/AI (lädt Timeline-Artefakt lazy) |

Kein eigenes UI und keine eigene API über Diagnose hinaus: `GET /api/v1/files/{ulid}/audio-analysis` (member) liefert Probe + Kennzahlen + Artefakt-Verweis; Anzeige geschieht in den Konsumenten-UIs (Assembler-Zeitachse, Upscaler-Vergleich).

## Edge Cases

* **Korrupte Dateien** (abgeschnittene MP3s, CRC-Fehler): FFmpeg dekodiert oft trotzdem teilweise; die Analyse unterscheidet `decoded_with_errors` (Kennzahlen mit Warnflag) von Totalausfall (Fachfehler ans Fundament, `analysis_status='failed'`).
* **Mehrkanal-Quellen** (5.1-Hörspiele): Kennzahlen kanalgemittelt, `channels` dokumentiert; Stille = alle Kanäle still.
* **Extrem leise Programme** (Meditations-Audio): relative Stille-Schwelle (Programm-Median-basiert) verhindert, dass das halbe Werk als „Stille" gilt; Mindest-Programmpegel-Prüfung markiert Verdachtsfälle.
* **DRM-/ungewöhnliche Container**: `probe` erkennt Nicht-Dekodierbarkeit früh und billig; höhere Stufen werden gar nicht versucht.

## Performance

Ein Dekodier-Pass pro Datei und Analyzer-Version, geteilt von `quality`+`timeline` — nie zwei Pässe. `analyze`-Queue drosselt (Fundament); Batch-Anforderungen (Scan über neue Bibliothek) laufen als Bulk mit Hash-Vorfilter (bereits gemessene Inhalte — auch unter anderem Pfad — treffen den Cache). Timeline-Artefakte (typisch 50–500 KB JSON, gzip im Artefakt-Store) werden von Konsumenten gestreamt gelesen, nie in Listen-Queries angefasst.

## Security

Die Analyse verarbeitet feindliche Eingaben (präparierte Audio-Container) im media-tools-Container: unprivilegiert, kein Egress, read-only-Mounts — identisches Bedrohungsmodell und identische Härtung wie die Disc-Analyse ([modules/disc-engine.md](disc-engine.md), Security). Ressourcen-Limits pro Analyse-Prozess (CPU-Zeit, Speicher, Output-Größe) verhindern Zip-Bomb-Äquivalente (Dateien, die zu absurden Dekodier-Ausgaben führen).

## Tests

Synthetische Referenzdateien mit bekannten Eigenschaften (Sinus-Sweeps für Bandbreite, kalibrierte Stilleblöcke, absichtliches Clipping, definierte LUFS): Kennzahlen müssen in engen Toleranzen landen — die Testsuite ist zugleich die Kalibrier-Referenz bei FFmpeg-Versionssprüngen (Analyzer-Version bump ⇒ Golden-Werte neu bestätigen). Cache-Tests (gleicher Hash ⇒ kein zweiter Messlauf; Hash-Wechsel ⇒ Invalidierung). Fehlerklassen-Tests (korrupt vs. teilkorrupt vs. Nicht-Audio).

## ADR-Verweise

Kein eigener ADR nötig; setzt [ADR-0001](../adr/0001-technology-stack.md) (native Werkzeuge rechnen) und Regel 8 (Zeitreihen als Artefakte) um.

## Offene Punkte

* Editions-aggregierter Qualitäts-Cache (siehe Datenmodell) — bei Bedarf mit dem Datenqualitätsmodul spezifizieren.
* Spektrogramm-Bild-Erzeugung liegt derzeit beim Upscaler-Finalize; ob sie als eigene Stufe hierher wandert, entscheidet der zweite Konsument.
* Sprach-/Musik-Klassifikation (für automatische Upscaler-Profilwahl) wäre eine natürliche Erweiterungsstufe; gehört methodisch zur [AI Engine](ai-engine.md).
