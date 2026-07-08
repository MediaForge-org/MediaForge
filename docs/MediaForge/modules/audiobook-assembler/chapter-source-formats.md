# Kapitelquellen: Formatreferenz

Vertiefung zu [modules/audiobook-assembler.md](../audiobook-assembler.md), Abschnitt „Chapter-Source-Collector". Normativ für die Parser der `ChapterSourceParserRegistry`: je Quellformat das Binär-/Textformat (soweit modelliert), die Extraktionsregeln, die Normalisierung ins **RawChapters**-Zwischenformat und der Anomalie-Umgang. Aufbau analog den [Disc-Formatreferenzen](../disc-engine/formats-bluray.md); wie dort gilt: beschrieben wird, was die Parser lesen — nicht die vollständige Fremdspezifikation.

## RawChapters: das Zwischenformat

Jeder Parser liefert dasselbe Zwischenformat; der [Aligner](alignment-algorithm.md) kennt nur dieses:

```json
{
  "parser": "cue", "parser_version": "1.2.0",
  "origin_detail": "cue:CD2.cue",
  "time_domain": "file_local" | "work" | "unscaled",
  "declared_total_ms": 21618000,
  "entries": [
    {"seq": 1, "title": "Kapitel 1", "title_kind": "source|generated",
     "start": {"file_ref": "CD2/Track01.mp3", "offset_ms": 0} | {"work_ms": 0},
     "end_hint_ms": null}
  ],
  "anomalies": [{"code": "…", "detail": "…"}]
}
```

`time_domain` ist die wichtigste Deklaration: `file_local` (Marken relativ zu benannten Dateien — CUE, Datei-eingebettete Kapitel), `work` (Werkzeit ab 0 — offizielle Listen, M4B-Kapitel, Sidecars) oder `unscaled` (Werkzeit einer *anderen* Fassung — offizielle Listen ohne Laufzeitgarantie; der Aligner entscheidet über Skalierung). `end_hint_ms` bleibt null, wo die Quelle nur Startmarken kennt (CUE); explizite Enden (M4B, Audnexus) werden mitgeführt und vom Aligner gegen die Folgemarke geprüft.

## Q-01: Offizielle Provider-Kapitel (Audnexus-kompatibel)

**Transport:** `GET {base}/books/{asin}/chapters` (Basis-URL Setting `assembler.sources.official_endpoint`; Audnexus-kompatibles Schema). Antwort (relevante Felder):

```json
{"asin": "B004V0GLBW", "brandIntroDurationMs": 2043, "brandOutroDurationMs": 5061,
 "isAccurate": true, "runtimeLengthMs": 21641243,
 "chapters": [{"lengthMs": 22219, "startOffsetMs": 0, "startOffsetSec": 0,
               "title": "Opening Credits"}]}
```

**Extraktion:** `chapters[]` → Entries in `time_domain='unscaled'` (die Laufzeit gilt für die Audible-Fassung, nicht zwingend für die lokale); `runtimeLengthMs` → `declared_total_ms`; `isAccurate=false` ⇒ Anomalie `provider_inaccurate` (Set entsteht, Auto-Aktivierungs-Kandidatur entfällt — dieselbe Konsequenz wie die 2-%-Laufzeitprüfung des Modulkapitels). **Brand-Intro/Outro:** `brandIntroDurationMs` wird als Verschiebungs-Hypothese an den Aligner durchgereicht (`shift_hints`) — lokale Rips enthalten das Audible-Intro nicht; die beste Verschiebung entscheidet der Aligner, nicht der Parser. Titel sind `title_kind='source'`; leere/numerische Titel („Chapter 1") bleiben source (Offizialität schlägt Schönheit). ASIN-Herkunft, Endpoint und Abrufzeit wandern nach `raw_source` (Reproduzierbarkeit des Lookups).

**Verifikations-Kopplung** (Modulkapitel): Der Collector ruft den Lookup nur bei verifiziertem Provider-Mapping; der Parser erzwingt zusätzlich `asin`-Gleichheit zwischen Anfrage und Antwort (`asin_mismatch` ⇒ Set wird verworfen, nicht angelegt — das einzige Verwerfen im Collector: eine falsche offizielle Liste ist aktiv schädlich).

## Q-02: MP4/M4B-Kapitel

Drei koexistierende Mechanismen im MP4-Container, in Präzedenz:

1. **QuickTime-Kapitel-Track** (`chap`-Referenz eines Audio-Tracks auf einen Text-Track): der reichhaltigste und von Audible/ABS geschriebene Weg. Extraktion via media-tools (ffprobe `-show_chapters` konsolidiert bereits): Start/Ende in ms, Titel UTF-8.
2. **Nero-Kapitel** (`chpl`-Box in `udta`): Legacy, von m4b-tool geschrieben. Millisekunden-Auflösung, Titel Pascal-String.
3. **`chpl` und Text-Track gleichzeitig, widersprüchlich:** Anomalie `dual_chapter_atoms`; es gilt der Text-Track (Mechanismus 1), die Differenz landet im Anomalie-Detail (Anzahl/erste Abweichung).

`time_domain='work'` bei Ein-Datei-Editionen (das M4B *ist* das Werk); bei Multi-Datei-Editionen mit Kapiteln je Datei `file_local` mit `file_ref` = jeweilige Datei (Konkatenations-Regel des Modulkapitels übernimmt der Aligner). Kapitel mit `end < start` oder Start jenseits der Dateidauer + 1 s: Anomalie `chapter_out_of_range`, Eintrag verworfen. Titel-Heuristik für `title_kind`: Muster `^(Chapter|Kapitel|Track|Teil)?\s*\d+$` ⇒ `generated`, sonst `source` — dieselbe Regex wie beim CUE-Parser (geteilte Bibliotheksfunktion, ein Verhalten überall).

## Q-03: ID3v2 CHAP/CTOC (MP3)

CHAP-Frames tragen ID, `start_ms`/`end_ms` (und Byte-Offsets, die ignoriert werden — Zeit schlägt Bytes), eingebettete Subframes (TIT2 = Titel). CTOC definiert die Reihenfolge; fehlt CTOC, gilt die CHAP-Reihenfolge im Tag mit Anomalie `missing_ctoc`. Zeitbasis ist die Datei ⇒ `file_local`. Praxis-Anomalien: CHAP nur in Datei 1 einer Multi-Datei-Edition mit Marken über die Gesamtlaufzeit (manche Tools schreiben Werkzeit in die erste Datei) — erkannt daran, dass Marken die Dateidauer überschreiten: Umdeutung zu `time_domain='work'` mit Anomalie `chap_work_time_in_file`; CHAP-Frames mit `end_ms = 0xFFFFFFFF` (offenes Ende) ⇒ `end_hint` null.

## Q-04: Vorbis/Opus-Kapitel-Kommentare

`CHAPTER001=00:12:34.567` + `CHAPTER001NAME=Titel` (Matroska-Konvention in Vorbis-Comments; auch in FLAC üblich). Parsing: dreistellige Nummern (auch lückenhaft — Lücken sind Anomalie `chapter_gap_numbers`, Reihenfolge nach Nummer), Zeitformat `HH:MM:SS.mmm` strikt (lockere Varianten `H:MM:SS` werden akzeptiert und als `loose_time_format` gemeldet). `file_local`. Ohne NAME-Zeile: Titel `Kapitel {n}` mit `title_kind='generated'`.

## Q-05: CUE-Dateien

### Dialekt-Toleranz beim Lesen

Encoding: BOM-Erkennung, sonst UTF-8-Versuch, Fallback CP1252 (`encoding_fallback`-Anomalie — reale CUEs aus Rip-Tools sind oft Latin-1). Gelesen werden: `FILE "…" (WAVE|MP3|AIFF)` (Typ-Token wird ignoriert — es lügt notorisch), `TRACK nn AUDIO`, `INDEX 01 mm:ss:ff` (75 fps-Frames; `INDEX 00` — Pre-Gap — wird gelesen, aber nicht als Kapitelmarke verwendet), `TITLE`, `PERFORMER` (ignoriert für Kapitel, bleibt in raw_source), `REM`-Zeilen (durchgereicht; eigene `REM MEDIAFORGE_*`-Marker erkennen Re-Import eigener Artefakte ⇒ Anomalie `own_artifact_reimport`, Set entsteht als Zeuge, wird aber nie Auto-Kandidat — sonst zirkuliert ein Artefakt als Quelle seiner selbst).

**FILE-Matching** (Security-Regel des Modulkapitels: nie als Pfad öffnen): Der FILE-Bezeichner wird ausschließlich per Basename-Vergleich (case-insensitiv, NFC) gegen die Editions-Dateien aufgelöst; Mehrdeutigkeit oder Fehltreffer ⇒ Anomalie `file_unresolved`, betroffene TRACKs werden verworfen. Multi-FILE-CUEs ergeben `file_local`-Entries je FILE; Single-FILE-CUEs für Multi-Datei-Editionen (CUE beschreibt ein nicht vorhandenes Gesamt-Image) ⇒ `time_domain='work'`-Umdeutung mit `single_file_cue_reinterpreted` — die häufigste CUE-Verwirrung, explizit modelliert statt verworfen.

Frames→ms: `ms = (mm·60 + ss)·1000 + round(ff·1000/75)`. TITLE-Regeln wie Q-02 (`generated`-Erkennung). Mehrere CUEs der Edition ⇒ mehrere Sets (Modulkapitel Edge Case); eine CUE pro CD-Ordner wird **nicht** automatisch fusioniert — jede ist ein eigenes `file_local`-Set, die Konkatenation besorgt der Aligner über die Zeitachse (die CUE-Grenzen validieren dabei die Sequenz kostenlos mit: eine CD2-CUE, deren FILEs in der Sequenz nicht kontiguierlich liegen, ist ein Sequenz-Alarmsignal, Anomalie `cue_sequence_conflict` mit Review-Anstoß).

## Q-06: Sidecar-JSON/NFO

Parser-Registry (Modulkapitel), Kernparser normativ:

* **ABS `metadata.json`** (`chapters: [{id, start, end, title}]`, Sekunden-Floats): `work`-Domäne, Sekunden→ms mit Rundung, `abs_metadata`-Herkunft. ABS schreibt `start/end` konsistent; fehlende `end`-Werte ⇒ `end_hint` null.
* **m4b-tool `chapters.txt`** (`00:00:00.000 Titel` je Zeile): `work`, Zeilenformat strikt, Abweichler-Zeilen werden übersprungen (`skipped_lines`-Anomalie mit Zählwert).
* **Generisches `chapters.json`** (`[{title, start_ms|start}, …]`): akzeptiert ms-Integer oder Sekunden-Float (Feldname entscheidet); ohne erkennbares Schema ⇒ Parser lehnt ab (kein Set — Raten über Fremdformate erzeugt Müll-Sets, die das Review-UI fluten).
* **Kodi-NFO** u. ä. XML: nur `<chapters>`-Elemente bekannter Struktur; sonst Ablehnung wie oben.

Größen-Limit 1 MB und Encoding-Strenge gelten registry-weit (Modulkapitel Security). Plugin-Parser durchlaufen dieselbe Vertragsprüfung: Zwischenformat-Schema-Validierung, Anomalie-Pflichtfelder, deterministische Ausgabe ([Plugin SDK](../../developer-handbook/plugin-sdk.md), Parser-Extension-Point).

## Q-07: Track-als-Kapitel

Kein Parser im engeren Sinn — der Collector erzeugt das Set aus der bestätigten Sequenz: je Track ein Kapitel `[start_offset_ms, start_offset_ms + duration_ms)`, `time_domain='work'` (per Konstruktion aligned — dieses Set überspringt den Aligner und ist sofort `aligned`; das einzige Set mit dieser Abkürzung). Titelquelle in Präzedenz: Tag-Titel (sofern nicht generisch nach der geteilten Regex), P-04-Kapitelmuster aus Dateinamen, sonst `Kapitel {seq}` `generated`. Confidence konstruktiv: 0.9 bei ≥ 50 % source-Titeln, sonst 0.7 (die Grenzen stimmen sicher, die Semantik „Track = Kapitel" ist die Restunsicherheit).

## Q-08: KI-Vorschlag

Formatvertrag der [AI Engine](../ai-engine.md) an den Collector (`chapter-proposal/v1`): Grenzen-Kandidaten mit je `work_ms`, `boundary_confidence`, Herkunfts-Merkmalen (`silence_window`, `speech_pause`, `announced_number` mit ASR-Text) und optionalen Titel-Vorschlägen (`title`, `title_confidence`). Der Parser übernimmt Grenzen als Entries (`work`-Domäne), Titel nur mit `title_confidence ≥ 0.8` (sonst `Kapitel {n}` generated) und schreibt Modellname/-version/Parameter nach `origin_detail` und `raw_source` (Reproduzierbarkeit; Architekturregel 5-Kennzeichnung hängt an `origin='ai'` und ist unumgehbar per DB-CHECK, Modulkapitel). Ein KI-Set trägt nie `end_hints` — Enden ergeben sich aus Folgegrenzen; das Modell soll Grenzen finden, keine Intervalle behaupten.

## Anomalie-Gesamtkatalog

| Code | Quelle | Wirkung |
|---|---|---|
| `provider_inaccurate` | Q-01 | keine Auto-Aktivierungs-Kandidatur |
| `asin_mismatch` | Q-01 | Set verworfen (einziger Verwerfungsfall) |
| `dual_chapter_atoms` | Q-02 | Text-Track gewinnt |
| `chapter_out_of_range` | Q-02/03/04 | Eintrag verworfen |
| `missing_ctoc` | Q-03 | Frame-Reihenfolge gilt |
| `chap_work_time_in_file` | Q-03 | Domänen-Umdeutung |
| `chapter_gap_numbers`, `loose_time_format` | Q-04 | toleriert, gemeldet |
| `encoding_fallback`, `skipped_lines` | Q-05/06 | toleriert, gemeldet |
| `file_unresolved` | Q-05 | TRACKs verworfen |
| `single_file_cue_reinterpreted` | Q-05 | Domänen-Umdeutung |
| `cue_sequence_conflict` | Q-05 | Review-Anstoß (Sequenz!) |
| `own_artifact_reimport` | Q-05/06 | nie Auto-Kandidat |

Anomalien leben im Set (`raw_source.anomalies` und aggregiert im `alignment_report` nach dem Alignment) und sind im Vergleichs-UI je Set sichtbar — die Herkunftsgüte ist Teil der Entscheidungsgrundlage des Reviewers, nicht nur der Maschine.

## Kollektions-Reihenfolge und Idempotenz

Der Collector läuft in fester Quellen-Reihenfolge (Q-07, Q-02…Q-06, Q-01 zuletzt — der Netz-Lookup soll lokale Ergebnisse nie verzögern) und ist idempotent über `origin_detail`: Ein Re-Lauf aktualisiert bestehende Sets gleicher Herkunft in-place nur, wenn sich `raw_source` unterscheidet (Hash-Vergleich), und legt nie Duplikate an. Aktive Sets werden durch Re-Läufe nie deaktiviert; eine inhaltliche Änderung eines aktiven Sets (Provider hat Kapitelliste korrigiert) setzt es auf `raw` zurück und erzeugt den Review `chapter_proposal` mit Diff — Aktivierungs-Entscheidungen verfallen nicht stillschweigend ([ADR-0008](../../adr/0008-chapter-source-hierarchy.md)-Konsequenz: Hierarchie entscheidet zwischen Quellen, Menschen entscheiden über Änderungen an Entschiedenem).
