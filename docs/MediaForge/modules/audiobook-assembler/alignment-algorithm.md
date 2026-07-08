# Kapitel-Alignment: Algorithmus-Spezifikation

Vertiefung zu [modules/audiobook-assembler.md](../audiobook-assembler.md), Abschnitt „Selector und Aligner". Normative Spezifikation des `ChapterAligner` — pure Funktion `align(RawChapters, Timeline, ?SilenceMap) → AlignmentResult`. Input-Formate: [RawChapters](chapter-source-formats.md) (Zwischenformat aller Parser), Timeline (materialisierte Zeitachse aus [Sequenzierung](sequencing-rules.md)), SilenceMap (optional, aus der Audioanalyse: Stillefenster als `[{start_ms, end_ms, rms_db}]` in Werkzeit). Output: alignierte Kapitel in Werkzeit plus der vollständige `alignment_report`.

## Stufenfolge

```
A-1 Domänen-Übersetzung   (file_local/work/unscaled → Werkzeit-Rohmarken)
A-2 Verschiebungs-Suche   (shift_hints, Brand-Intro etc.)
A-3 Skalierungsprüfung    (linear/nichtlinear)
A-4 Grenz-Snapping        (Stillefenster, Trackgrenzen)
A-5 Struktur-Bereinigung  (Monotonie, Lücken, Überlappungen, Enden)
A-6 Abdeckungs-Validierung (Anfang/Ende/Vollständigkeit)
A-7 Confidence & Report
```

Jede Stufe schreibt ihren Abschnitt in den Report; ein Fehlschlag in A-3/A-5/A-6 setzt `alignment_status='failed_validation'` mit dem Report als Begründung — nie stilles Verwerfen einzelner Marken über die dokumentierten Toleranzen hinaus.

## A-1: Domänen-Übersetzung

**`file_local`:** Jede Marke `(file_ref, offset_ms)` wird über die Timeline übersetzt: `work_ms = start_offset(track(file_ref)) + offset_ms`. Nicht auflösbare `file_ref`s sind hier unmöglich (der Parser hat sie bereits verworfen); Offsets jenseits der Trackdauer + 1 s ⇒ Validierungsfehler `offset_beyond_track` (kein Toleranzfall: eine file-lokale Marke außerhalb ihrer Datei ist ein Quellendefekt). Multi-Set-Konkatenation (CUE je CD, Datei-Kapitel je Datei): Entries werden nach Übersetzung global nach `work_ms` geordnet; Ordnungsumkehrungen gegenüber der Quell-Reihenfolge ⇒ `cue_sequence_conflict`-Pfad (Review, [Formatreferenz](chapter-source-formats.md)).

**`work`:** Übernahme unverändert; Marken > Werkdauer + 1 s ⇒ A-3 entscheidet (Skalierungsverdacht statt Sofortfehler).

**`unscaled`:** Übernahme als Rohmarken; A-2/A-3 sind für diese Domäne Pflichtstufen (für `work`/`file_local` nur Prüfstufen).

## A-2: Verschiebungs-Suche

Konstante Offsets zwischen Quelle und lokaler Fassung (Audible-Brand-Intro fehlt im Rip; zusätzlicher Verlags-Jingle vorhanden). Kandidaten: 0, jede `shift_hint` des Parsers (±`brandIntroDurationMs`, ±`brandOutroDurationMs` als Endkorrektur), sowie — nur wenn SilenceMap vorliegt — die Verschiebung, die die Marken-zu-Stille-Kongruenz maximiert (Grid-Suche ±30 s in 250-ms-Schritten über die Kongruenz-Metrik: Anteil der Marken mit Stillefenster in ±2 s). Gewählt wird der Kandidat mit maximaler Kongruenz; ohne SilenceMap der erste Hint, der die A-3-Prüfung besteht (Reihenfolge: 0, Hints in Parserreihenfolge). Gewählte Verschiebung ≠ 0 wird protokolliert (`shift_applied_ms`) und fließt als Malus 0.05 in die Confidence (eine verschobene Quelle ist eine interpretierte Quelle).

## A-3: Skalierungsprüfung

Vergleich `declared_total_ms` (bzw. letzte Marke + mittlere Kapitellänge als Schätzer, wenn die Quelle kein Total deklariert) gegen `total_duration_ms` der Timeline:

* **Δ ≤ 0.5 %:** keine Skalierung, `scale = 1.0`.
* **0.5 % < Δ ≤ 4 %:** lineare Skalierungs-Hypothese `scale = total_timeline / total_source`. Verifikation gegen die SilenceMap, falls vorhanden: Kongruenz der skalierten Marken muss die unskalierten um ≥ 0.15 übertreffen, sonst gilt unskaliert mit Warnung `scale_unverified`. Ohne SilenceMap wird skaliert und `scale_applied` mit Malus 0.1 protokolliert (typischer Fall: PAL-artige Encoder-Differenzen, Modulkapitel-Beispiel 0.96).
* **Δ > 4 %:** Fassungs-Mismatch-Verdacht (gekürzt vs. ungekürzt, Modulkapitel Edge Case) ⇒ `failed_validation` mit `version_mismatch_suspected` — es sei denn, die Quelle ist `file_local` (dann kann das Total gar nicht abweichen; ein solches Δ wäre ein Timeline-Fehler ⇒ `timeline_integrity_error`, Review auf die **Sequenz**, nicht die Quelle).

Nichtlinearität wird nach linearer Skalierung geprüft, sofern SilenceMap vorhanden: Residuen der Marken zu ihren nächsten Stillefenstern; wachsen die Residuen monoton über die Werkzeit (Regression, R² > 0.8 bei Steigung > 1 s/h), liegt ein struktureller Unterschied vor (zusätzliche/fehlende Passagen) ⇒ `failed_validation` mit `nonlinear_drift` und dem Residuen-Verlauf im Report (das Review-UI plottet ihn — der Mensch erkennt „ab Kapitel 12 driftet es" auf einen Blick).

## A-4: Grenz-Snapping

Ziel: gerundete Quellzeiten (Sekunden-Auflösung bei Q-01/Q-06, Frame-Rundung bei CUE) auf hörbar richtige Grenzen ziehen. Reihenfolge je Marke (erste zutreffende Regel gewinnt, alle Parameter Settings unter `assembler.aligner.*`):

1. **Trackgrenzen-Mikro-Snap:** Distanz zur nächsten Trackgrenze < 500 ms (`track_snap_ms`) ⇒ auf die Trackgrenze (Quellen meinen fast immer die Dateigrenze, wenn sie so nah liegen; zugleich CUE-freundlich, [Artefakt-Referenz](artifact-builders.md)).
2. **Stille-Snap:** SilenceMap vorhanden und ein Stillefenster ≥ 300 ms (`min_silence_ms`) liegt in ±2 s (`silence_snap_window_ms`) ⇒ auf die **Mitte** des Fensters (Grenze im Stillsten, nicht am Stille-Rand — schnittfest für alle Artefakt-Verwender).
3. **Belassen:** volle Quellenzeit (Modulkapitel: „nie stumm auf Trackgrenzen gezogen").

Snapping ist marken-lokal und ordnungserhaltend: Würde ein Snap die Monotonie verletzen (zwei Marken ins selbe Stillefenster), snapt nur die erste, die zweite bleibt roh mit `snap_conflict`. Jede Snap-Entscheidung steht einzeln im Report (`snapping: [{seq, from_ms, to_ms, rule}]`) — im Kapitel-Editor als „gesnappt"-Marker sichtbar.

## A-5: Struktur-Bereinigung

Auf den gesnappten Marken (Regeln aus Modulkapitel-Invarianten, präzisiert):

* **Monotonie:** nicht-monotone Folgen nach A-1…A-4 sind Quellendefekte ⇒ `failed_validation` (`non_monotonic`) — außer die Verletzung ist ≤ 1 s bei genau einem Paar: dann werden die beiden Marken auf ihren Mittelwert ± 500 ms auseinandergelegt (`micro_swap_fixed`, Malus 0.05; deckt Rundungs-Kollisionen ab).
* **Enden-Bildung:** `end(k) = start(k+1)`; explizite `end_hints` der Quelle werden verglichen — Abweichung > 2 s ⇒ `end_hint_conflict` (Hinweis, kein Fehler: Enden-Lücken sind oft absichtliche Pausen zwischen Kapiteln; die geschlossene Kette gewinnt, die Hint-Differenz bleibt sichtbar).
* **Mini-Kapitel:** Kapitel < 3 s (`min_chapter_ms`) werden mit dem Vorgänger verschmolzen (`tiny_chapter_merged`; Ausnahme: seq 1 — ein kurzes „Opening Credits" ist legitim).
* **Lücken/Überlappungen:** nach Enden-Bildung strukturell unmöglich; die 500-ms-Lückenregel des Modulkapitels wirkt in A-1 bei Multi-Set-Konkatenation (Zwischenräume zwischen CUE-Dateiblöcken).

## A-6: Abdeckungs-Validierung

Modulkapitel-Regeln als exakte Kette: erste Marke ∈ (0, 5 s] ⇒ auf 0 ziehen (`start_pulled`); erste Marke > 5 s ⇒ Vorspann-Kapitel `Kapitel 0`/`generated` einfügen, wenn die Quelle offiziell ist (`leading_gap_chapter`), sonst `failed_validation` (`uncovered_start` — eine nichtoffizielle Quelle, die den Anfang nicht kennt, hat ihre Glaubwürdigkeit verwirkt). Letztes Kapitel: Rest zur Werkdauer < 30 s ⇒ strecken (`end_stretched`); ≥ 30 s ⇒ Schlusskapitel-Einfügung analog Vorspann-Regel. Ergebnis-Invariante (DB-seitig als Exclusion + Action-Validierung): lückenlose Partition von `[0, total_duration_ms]`.

## A-7: Confidence und Report

```
confidence = basis(origin)                        − Σ mali
basis: official 0.95 · embedded 0.9 · cue 0.85 · sidecar 0.8 · track_as_chapter (konstruktiv, s. Formatreferenz)
       · manual 1.0 (überspringt A-2/A-3) · ai (vom Modell, gedeckelt 0.85)
mali:  shift_applied 0.05 · scale_applied 0.1 · scale_unverified 0.05 · micro_swap 0.05
       · je snap_conflict 0.02 (≤ 0.1) · leading_gap/end-Einfügung 0.05
       · Anomalie-Mali des Parsers (Formatreferenz: provider_inaccurate ⇒ Kandidatur-Verlust, nicht Malus)
```

`alignment_report` (JSONB, Vertragsfelder): `aligner_version`, `input` (Domäne, Marken-/Kapitelzahl, declared_total), `shift`, `scale` (Hypothese, Verifikation, Residuen-Statistik), `snapping[]`, `structure` (Fixes), `coverage` (Pull/Stretch/Einfügungen), `confidence_calc`, `failure` (bei failed_validation: Code + Kontext). Der Report ist die alleinige Erklärungsquelle des Vergleichs-UIs — dieselbe Rolle wie `classification_evidence` in der Disc-Engine.

## Aktivierungs-Wettbewerb (Selector-Präzisierung)

Ergänzend zum Modulkapitel („höchste Stufe gewinnt, dann Confidence"): **Konflikt-Definition** für den Review-Fall statt Auto-Wahl — ein Konflikt liegt vor, wenn (a) das hierarchie-beste Set einen aktiven Malus-Deckel trägt (`provider_inaccurate`, Laufzeit-Warnung), (b) zwei Sets benachbarter Stufen sich in > 20 % der Grenzen um > 10 s unterscheiden (echte Strukturdifferenz, nicht Rundung), oder (c) das beste Set weniger als 60 % source-Titel hat, während ein niedrigeres Set > 90 % hat (Grenzen vs. Titel-Qualität auseinander — der Review bietet die Titel-Übernahme an, siehe unten). Fall (c) führt zum **Titel-Merge-Angebot**: Grenzen des höheren Sets + Titel des niedrigeren als neues `manual`-Set per einem Klick (Herkunftskette in `origin_detail: "merge:grenzen=<setA>,titel=<setB>"`) — der einzige definierte „Fusions"-Pfad, bewusst als manueller Vorschlag, nie automatisch (Modulkapitel-Alternativen: keine automatische Fusion).

## Durchgerechnete Beispiele

**1: Audnexus-Liste auf 97-Track-Rip** (Kern-Flow). 31 Kapitel `unscaled`, `runtimeLengthMs` 21 641 243, Timeline 21 618 004 (Δ 0.107 % ⇒ keine Skalierung). Brand-Intro-Hint 2 043 ms; Kongruenz-Suche mit SilenceMap: Verschiebung −2 043 ms gewinnt (Kongruenz 29/31 vs. 11/31 bei 0). Snapping: 24 Marken auf Stillefenster, 5 Trackgrenzen-Mikro-Snaps, 2 belassen. A-6: erste Marke 0 (nichts zu ziehen), Rest 14 s ⇒ Strecken. Confidence 0.95 − 0.05 (shift) = 0.90. Auto-Aktivierung Stufe 2, konfliktfrei.

**2: CUE dreier CDs, eine Marke im Nirgendwo.** `file_local`, Konkatenation sauber, aber CD2-CUE referenziert `Track09.mp3`, das in der Sequenz fehlt (file_unresolved beim Parsen ⇒ TRACK fehlt) ⇒ nach A-5 ein 84-min-Kapitel zwischen zwei 8-min-Kapiteln. Kein Validierungsfehler (Partition stimmt!), aber der Report zeigt die Kapitel-Längen-Statistik; das Set bleibt `aligned` mit Confidence 0.85 − Parser-Anomalie-Malus. Es verliert den Wettbewerb gegen `track_as_chapter` (0.9) — korrekt: Die beschädigte CUE ist die schlechtere Wahl, und der Vergleichs-UI-Diff macht das 84-min-Kapitel augenfällig.

**3: Offizielle Liste, gekürzte Fassung.** Δ 11 % ⇒ A-3 `failed_validation`, `version_mismatch_suspected`, Review mit Fassungs-Hinweis (Modulkapitel Edge Case). Kein Set-Wettbewerb; `embedded` (M4B des Rips) gewinnt als bestes verbleibendes.

## Eigenschaften (testverankert in [api-ui-tests.md](api-ui-tests.md))

* **AL-P1 Determinismus:** gleiche Inputs ⇒ byte-gleicher Report.
* **AL-P2 Partitions-Garantie:** jedes `aligned`-Ergebnis ist lückenlose Partition von `[0, total]`.
* **AL-P3 Ordnungserhalt:** Snapping/Fixes invertieren nie die Quell-Reihenfolge.
* **AL-P4 Grenzfall-Tabelle:** 499/501-ms-Lücke, 2.9/3.1-s-Kapitel, 4.9/5.1-s-Erstmarke, 29/31-s-Rest, 0.49/0.51-%-Δ, 3.9/4.1-%-Δ — jeweils beidseitig des Schwellwerts getestet.
* **AL-P5 Keine stille Markenvernichtung:** Anzahl Quellmarken = Anzahl Ergebnis-Kapitel + dokumentierte Merges/Einfügungen (Bilanz im Report geht immer auf).

## Settings-Referenz

| Schlüssel (`assembler.aligner.*`) | Default | Stufe |
|---|---|---|
| `track_snap_ms` | 500 | A-4.1 |
| `silence_snap_window_ms` | 2000 | A-4.2 |
| `min_silence_ms` | 300 | A-4.2 |
| `min_chapter_ms` | 3000 | A-5 |
| `scale_trigger` | 0.005 | A-3 |
| `scale_max` | 0.04 | A-3 |
| `shift_search_window_ms` | 30000 | A-2 |
| `start_pull_ms` | 5000 | A-6 |
| `end_stretch_ms` | 30000 | A-6 |
| `conflict_boundary_delta_ms` | 10000 | Selector (b) |
| `conflict_boundary_ratio` | 0.2 | Selector (b) |
