# Sequenzierungsregel-Katalog (Track-Sequencer)

Vertiefung zu [modules/audiobook-assembler.md](../audiobook-assembler.md), Abschnitt „Sequencer". Normativer Katalog der Evidenzquellen, Muster-Bibliothek, Konsens- und Confidence-Berechnung des `TrackSequencer` — analog aufgebaut zum [Klassifikationsregel-Katalog der Disc-Engine](../disc-engine/classification-rules.md): stabile Kennungen (SQ-nn), Settings mit Defaults, Evidence-Schema, durchgerechnete Beispiele, Testverankerung in [api-ui-tests.md](api-ui-tests.md).

## Design-Verpflichtungen

1. **Stille Sequenzfehler sind der Feind.** Zwei vertauschte Tracks in Stunde 14 hört niemand beim Stichproben-Check (Modulkapitel). Deshalb entscheidet nie eine Einzelquelle: Jede Sequenz braucht entweder Konsens zweier unabhängiger Quellen oder menschliche Bestätigung.
2. **Reproduzierbarkeit.** Gleiche Dateien ⇒ gleiche Sequenz, über Betriebssysteme und Locales hinweg (ICU-Collation, fixierte Muster-Bibliothek, deterministische Tie-Breaks).
3. **Teilwissen zählt.** Quellen mit Teilordnung (Tags bei 80/97 Dateien) scheiden als Alleinquelle aus, validieren aber — ein Widerspruch in den 80 wiegt genauso schwer wie bei Vollabdeckung.
4. **Manuelle Ordnung ist unantastbar** (`sequence_source='manual'`; automatische Läufe rühren sie nie an, Modulkapitel).

## Evidenzquellen

### SQ-10 — Tag-Nummern

Gelesen werden (media-tools, Tag-Snapshot je Datei): `track` (ID3v2 TRCK, Vorbis TRACKNUMBER, MP4 trkn), `disc` (TPOS, DISCNUMBER, disk), jeweils mit `x/y`-Zerlegung (`3/97` ⇒ Nummer 3, Gesamt 97). Normalisierung: führende Nullen irrelevant; nicht-numerische Reste (`A3`, `3b`) machen den Wert unbrauchbar (zählt als fehlend, Evidence `unparsable_tags`).

Ordnungsbildung: primär `(disc, track)` lexikographisch, wenn disc-Werte existieren; sonst `track` allein. Qualitätsstufen der Quelle:

| Stufe | Bedingung | Verwendbarkeit |
|---|---|---|
| `global` | track-Nummern über alle Dateien eindeutig und lückenlos 1..N | Alleinquelle möglich |
| `disc_local` | track-Nummern je disc lückenlos, disc-Werte vollständig | Alleinquelle möglich |
| `disc_local_implied` | track-Nummern starten mehrfach bei 1, **keine** disc-Tags — CD-Lokalität wird aus SQ-30-Ordnern erschlossen | nur kombiniert mit SQ-30 |
| `partial` | < 100 % der Dateien getaggt | nur Validierung |
| `broken` | Duplikate innerhalb einer disc, Lücken > 2 | nur Negativ-Evidenz |

`total`-Angaben (`3/97`, `disc 2/3`) werden gegen die Realität geprüft: `total ≠ Dateizahl` ist ein Warnsignal (Evidence `total_mismatch` — häufig bei zusammenkopierten Teilrips), das die Confidence der Quelle um 0.1 senkt, sie aber nicht disqualifiziert (Publisher taggen `total` notorisch falsch).

### SQ-20 — Dateinamen-Muster

Die Muster-Bibliothek wird in deklarierter Reihenfolge gegen die Dateinamen (ohne Erweiterung, Unicode-NFC-normalisiert) geprüft; das erste Muster, das ≥ 90 % der Dateien matcht (Setting `assembler.sequencer.pattern_coverage_min`, Default 0.9), wird die Dateinamen-Ordnung. Bibliothek (normativ, erweiterbar via Plugin SDK — Erweiterungen registrieren sich **hinter** den Kernmustern):

| Muster | Regex-Kern (vereinfacht) | Beispiel | Extrahiert |
|---|---|---|---|
| P-01 führende Nummer | `^(\d{1,4})\b` | `042 - Titel.mp3` | track |
| P-02 Nummer nach Titel | `\b(\d{1,4})$` | `Der Name des Windes 003` | track |
| P-03 CD+Track kombiniert | `\bCD\s*(\d{1,2})\D+(\d{1,3})\b` | `CD2 Track07` | disc, track |
| P-04 Kapitel-Benennung | `\b(?:Kapitel\|Chapter\|Teil\|Part)\s*(\d{1,4})\b` | `Kapitel 17.mp3` | track (+ Titel-Kandidat fürs `track_as_chapter`-Set) |
| P-05 Publisher-Schema B/T | `^[A-Z]\d{3}\b.*?(\d{3})$` | `B003 … 003.mp3` | track |
| P-06 x-von-y | `\b(\d{1,3})\s*(?:of\|von\|/)\s*(\d{1,3})\b` | `Track 3 of 97` | track, total |

Mehrdeutige Treffer innerhalb einer Datei (P-01 und P-02 matchen verschiedene Zahlen): Es gilt das Muster, das über die Gesamtheit die konsistentere Folge liefert (weniger Duplikate/Lücken); Gleichstand ⇒ Quelle wird `ambiguous` (nur Validierung, Evidence `pattern_ambiguity`). Jahreszahlen (1900–2099) und Bitraten-Fragmente (`128`, `320` unmittelbar vor `kbps`) sind als Track-Kandidaten ausgeschlossen (klassische Falschtreffer).

### SQ-30 — Ordnerstruktur

Erkennt CD-/Teil-Ordner über Verzeichnisnamen-Muster (`CD\s*\d+`, `Disc\s*\d+`, `Teil\s*\d+`, `Part\s*\d+`, römische Ziffern bis XII) direkt unterhalb des Editions-Wurzelordners. Liefert die Grobordnung (Ordner-Nummer) und adelt SQ-10-`disc_local_implied` bzw. kombiniert mit der Feinordnung aus SQ-10/SQ-20 innerhalb jedes Ordners. Ein Ordner ohne Muster inmitten nummerierter Geschwister (`CD1`, `CD2`, `Bonus`) wird ans Ende sortiert und als `unnumbered_sibling` gemeldet — typisch Bonusmaterial, das der Review als Nicht-Werk ausschließen kann (Modulkapitel Edge Case „M4B + lose MP3s").

### SQ-40 — Natürliche Sortierung

ICU-Collation, Locale `de`, numerische Ordnung (`Track2 < Track10`), Groß/Klein-indifferent, über den vollständigen Relativpfad (Ordner zuerst — stabil bei CD-Strukturen). Immer verfügbar, nie Alleinquelle mit hoher Confidence (sie *ist* oft richtig, aber sie hat keine Semantik — ihre Confidence ist strukturell gedeckelt, siehe Scoring).

### SQ-50 — Eingebettete Reihenfolge-Anker (Validierung, keine Ordnung)

Zwei Zusatzsignale ausschließlich zur Konsensprüfung: **Laufzeit-Homogenität** (Tracks eines Rips streuen typisch eng; ein Ausreißer < 25 % oder > 400 % des Medians markiert Fremdkörper — Evidence `duration_outlier`, Review-Anstoß, Modulkapitel) und **Encoder-Konsistenz** (gleicher Encoder/Bitrate-Fingerprint über alle Tracks; Wechsel mitten in der Folge deutet auf zusammenkopierte Quellen — `encoder_break`, senkt Gesamt-Confidence um 0.05 je Bruchstelle, max. 0.15).

## Konsens und Scoring

**Kandidaten:** Jede Quelle mit Vollordnung (SQ-10 global/disc_local, SQ-20, SQ-30-Kombination, SQ-40) erzeugt eine Kandidaten-Sequenz mit Basisgewicht: Tags 1.0, Dateiname 0.8, Ordner-Kombination 0.75, natürliche Sortierung 0.4.

**Ausnahme-Regel** (Modulkapitel): Sind Tag-Nummern erkennbar CD-lokal (`disc_local*`) und existiert eine **globale** Dateinamen-Nummerierung (P-01/P-02/P-05 mit Werten bis N), gewinnt der Dateiname das Basisgewicht 1.0 und die Tags 0.8 — globale Zählung ist beweiskräftiger als lokale.

**Konsens:** Paarweise Kendall-Tau-Distanz zwischen den Kandidaten. Sequenzen mit τ = 1.0 (identisch) bilden den Konsensblock. Confidence:

```
confidence = min(0.99, Σ gewichte(konsensblock) / Σ gewichte(alle kandidaten)
                        · deckel(quellen)
                        − abzüge)
deckel: 0.99 bei ≥ 2 unabhängigen Quellen im Block; 0.90 bei Alleinquelle Tags global;
        0.85 Alleinquelle Dateiname; 0.60 Alleinquelle natürliche Sortierung
abzüge: total_mismatch 0.1 · encoder_breaks ≤ 0.15 · duration_outlier 0.1 je Fund (≤ 0.2)
        · unnumbered_sibling 0.05
```

Schwellen (Settings `assembler.sequencer.*`): `auto_threshold` 0.95 (≥ ⇒ automatisch `sequenced`), `review_threshold` 0.60 (< ⇒ Review ohne Vorauswahl; dazwischen Review mit vorausgewähltem Bestkandidat). Widerspricht ein Kandidat mit Gewicht ≥ 0.75 dem Konsensblock (τ < 1.0), gibt es **immer** einen Review-Task `audiobook_sequence`, auch über 0.95 — sichtbarer Widerspruch zweier starker Quellen ist nie automatisch entscheidbar (Design-Verpflichtung 1). Der Review zeigt die Divergenzstellen als Gegenüberstellung (genau die Positionen, an denen die Ordnungen differieren, nicht 97 Zeilen Rauschen).

**Tie-Breaks:** Innerhalb identischer Ordnungsschlüssel (zwei Dateien, beide `track=7`, disc gleich) entscheidet die natürliche Sortierung der Dateinamen; das ist zugleich ein `broken`-Signal der Tag-Quelle.

## Evidence-Schema (`sequencer_evidence`, JSONB)

```json
{
  "sequencer_version": "1.4.0",
  "sources": {
    "tags":     {"quality": "disc_local", "coverage": 1.0, "weight": 1.0,
                 "notes": ["total_mismatch"]},
    "filename": {"pattern": "P-03", "coverage": 0.97, "weight": 0.8},
    "folders":  {"folders": ["CD1", "CD2", "CD3"], "weight": 0.75},
    "natural":  {"weight": 0.4}
  },
  "consensus": {"members": ["tags", "filename", "folders"], "tau_matrix": {…},
                "dissenters": []},
  "anchors": {"duration_outliers": [], "encoder_breaks": 0},
  "confidence_calc": {"raw": 0.93, "cap": 0.99, "deductions": {"total_mismatch": 0.1},
                      "final": 0.93},
  "decision": "review_prefilled"
}
```

Wie überall gilt: Evidence ist Diagnose-Vertrag (Review-UI rendert daraus die Gegenüberstellung), additiv erweiterbar, `sequencer_version` Pflicht.

## Zeitachsen-Regeln (Schritt `timeline`)

Präzisierung des Modulkapitels:

* **Header-Messung** (ffprobe `format.duration`): Default. **Dekodier-Trigger** (⇒ `ffmpeg -f null`, `duration_method='decoded'`): (a) MP3 ohne Xing/Info/VBRI-Header bei VBR-Indizien (Bitraten-Streuung in Stichproben-Frames), (b) `|header_dauer − größenschätzung| / header_dauer > 0.01`, (c) Container-Fehler-Warnungen im Probe. Größenschätzung: `dateigröße_bytes · 8 / nominal_bitrate`.
* Offsets sind exakte Ganzzahl-Summen der `duration_ms` — es gibt **keine** Rundungsverteilung; die Präzision der Werkzeit ist die Präzision der Einzelmessungen. Deshalb dokumentiert `duration_method` je Track: Ein Set, das auf einer Header-Zeitachse aligned wurde, wird bei nachträglicher Dekodier-Korrektur einzelner Tracks automatisch `raw` (Zeitachsen-Änderung = Set-Invalidierung, Modulkapitel-Invariante).
* Nicht-Audio-Dateien der Edition (Cover, PDF, NFO) sind nie Tracks; Audio-Formate außerhalb der Kern-Unterstützung (mp3, m4a/m4b, flac, ogg/opus, wma) erzeugen einen Review statt stiller Auslassung (`unsupported_audio`).

## Re-Sequenzierung

Auslöser: Dateibestand der Edition ändert sich (Fundament-Event; Assembly ⇒ `stale`, Modulkapitel), manueller Anstoß, Sequencer-Versionssprung. Verhalten: `manual`-Sequenzen werden nie überschrieben — der Lauf rechnet den neuen Bestand, vergleicht mit der manuellen Ordnung und erzeugt bei Differenz (neue/entfernte Dateien) einen Review mit Delta-Ansicht („3 neue Dateien einsortieren?"); Heuristik-Sequenzen werden neu gerechnet und bei τ < 1.0 zur alten Fassung ebenfalls review-pflichtig (eine bestätigte Kapitelstruktur hängt an der alten Ordnung — stiller Austausch würde aktive Sets entwerten).

## Durchgerechnete Beispiele

**A: „97 MP3s, drei CD-Ordner"** (Kern-Flow des Modulkapitels). Tags: track je CD ab 1, keine disc-Tags ⇒ `disc_local_implied`. Dateinamen: P-01 mit CD-lokalen Nummern (coverage 1.0). Ordner: CD1–CD3. Kombination SQ-30×SQ-10 und SQ-30×SQ-20 liefern identische Ordnung; natürliche Sortierung ebenfalls (Pfad-primär). Konsens: 4 Mitglieder (implied-Tags zählen kombiniert), keine Dissenter, ≥ 2 unabhängige ⇒ Deckel 0.99, keine Abzüge ⇒ 0.97 … auto-`sequenced`. 

**B: „Tags global, Dateinamen widersprechen"**: Tags 1..97 lückenlos (`global`, 1.0); Dateinamen P-05 mit anderer Folge an Position 43/44 (Publisher-Umbenennung). τ < 1.0, Dissenter-Gewicht 0.8 ≥ 0.75 ⇒ Review trotz rechnerischer 0.56/… — Anzeige: „Position 43: Tags sagen ‚…043', Dateiname sagt ‚…044'". Mensch entscheidet; typischer Befund: Tag-Duplikat durch Kopierfehler.

**C: „Nichts außer Dateinamen"**: keine Tags, ein Ordner, P-02 coverage 0.94. Alleinquelle Dateiname ⇒ Deckel 0.85 ⇒ Review mit Vorauswahl. Ein Klick Bestätigung — bewusste Reibung: Eine 40-Stunden-Struktur auf Basis einer einzigen semantischen Quelle verdient einen menschlichen Blick.

## Settings-Referenz

| Schlüssel (`assembler.sequencer.*`) | Default | Verwendung |
|---|---|---|
| `pattern_coverage_min` | 0.9 | SQ-20 Musterakzeptanz |
| `auto_threshold` | 0.95 | Auto-`sequenced` |
| `review_threshold` | 0.60 | Vorauswahl vs. leerer Review |
| `duration_outlier_low` / `_high` | 0.25 / 4.0 | SQ-50 relative Grenzen |
| `decode_divergence` | 0.01 | Dekodier-Trigger (b) |
| `weight_tags` / `weight_filename` / `weight_folders` / `weight_natural` | 1.0 / 0.8 / 0.75 / 0.4 | Basisgewichte |

Kalibrierungs-Verpflichtung identisch zur Disc-Engine: Defaults hängen an der synthetischen Fixture-Bibliothek ([api-ui-tests.md](api-ui-tests.md)); Änderungen laufen gegen die Golden-Suite.
