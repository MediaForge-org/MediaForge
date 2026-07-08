# Disc-Engine: Test-Katalog

Vertiefung zu [modules/disc-engine.md](../disc-engine.md), Abschnitt „Tests". Vollständiger Katalog der Fixtures, Testfälle und Suiten mit stabilen Kennungen. Methodische Grundlagen (Harnesses, Invarianten-Suite, Golden-File-Mechanik) definiert [developer-handbook/testing.md](../../developer-handbook/testing.md); hier stehen die Disc-spezifischen Bestände. Jede normative Aussage der Vertiefungsdateien ([Formatreferenzen](formats-bluray.md), [Regelkatalog](classification-rules.md), [Mapping](mapping-algorithm.md), [Playback](playback-translation.md), [API](api-reference.md)) ist mindestens einem Testfall zugeordnet; die Rückverfolgungs-Matrix am Ende weist das aus.

## Fixture-Bibliothek (`tests/fixtures/disc-analysis/`)

Anonymisierte `disc-analysis/v1`-JSONs realer Disc-Strukturen (Labels/IDs geschwärzt, Struktur authentisch). Namenskonvention `{kind}-{muster}-{nn}.json`. Jede Fixture dokumentiert im Kopf-Kommentarblock (`_fixture`-Feld): Herkunftsmuster, Besonderheiten, erwartete Klassifikation als Golden-Referenz.

| Fixture | Struktur | Exerziert |
|---|---|---|
| `bd-series-clean-01` | 47 PL, 6 Episoden + Play-All + Extras (Regelkatalog-Beispiel 1) | R-10, R-20/22, R-30, R-41/43, Happy Path |
| `bd-series-noplayall-02` | 6 Episoden ohne Play-All, wenige Duplikate | R-41 ohne R-32-Boost |
| `bd-series-doubleep-03` | 5 Episoden + 1 Doppelfolge (2,1× Median) | R-44, Mapper N-03/Stufe-4-Segmente |
| `bd-movie-editions-01` | Film, 2 Fassungen (Jaccard 0.83) + Extras (Beispiel 2) | R-50/51, Fassungsgruppen |
| `bd-movie-multifeature-02` | Doppelfeature ohne Clip-Beziehung | R-52 |
| `bd-obfuscated-01` | 412 PL, 88 % Duplikate (Beispiel 3) | R-11/12, K-03, Signatur-Stabilität unter Faltung |
| `bd-3d-mvc-01` | 3D-Film mit SSIF | Formatreferenz 3D, `is_3d`-Durchleitung |
| `uhd-dv-01` | UHD, Dolby Vision Dual Layer, BDMV v3 | UHD-Erkennung, HDR-Priorität, SubPath Typ 8 |
| `bd-anomalies-01` | synthetisch: alle BD-Anomalie-Codes in einer Struktur | Anomalie-Normalisierungen einzeln |
| `bd-encrypted-01` | AACS-Verzeichnis vorhanden, Streams verschlüsselt markiert | `aacs_present`/`streams_encrypted`-Pfad |
| `dvd-box-chain-01` | 4 Discs, PGC-Ketten, Play-All-only auf Disc 3 (Beispiel 4) | R-31/32 (`pgc_chain`), N-04 Set-Kontext, K-04 |
| `dvd-pal-speedup-01` | PAL-Serie, Provider-Laufzeiten NTSC-Basis | Mapper N-02 (Quotient 1.043) |
| `dvd-arccos-01` | ARccOS-Narben: dangling cells, 74 Fake-PGCs | DVD-Obfuskation, `structurally_broken` |
| `dvd-multiangle-01` | Konzert-DVD mit Angle-Blocks | Angle-Block-Faltung zu einem Item |
| `dvd-parental-01` | Parental-PGC-Gruppe (3 Fassungen, geteilte Zellen) | R-51-Analogon DVD |
| `dvd-bonusdisc-01` | Box-Bonus-Disc: 6 uniforme Featurettes | K-04 (Erwartungs-Mismatch drückt Gruppe) |
| `dvd-anomalies-01` | synthetisch: alle DVD-Anomalie-Codes | Anomalie-Normalisierungen |
| `bd-miniseries-longep-01` | 3 × 85-min-Episoden (Fenster-Durchfaller) | R-40-Grenze, Mapper S-03-Sonderfall |
| `bd-webisodes-mixed-01` | 45-min-Gruppe + 22-min-Gruppe | R-45 Mehrfachgruppen, Mapper Gruppenwahl |
| `bd-noruntime-01` | Serie, Provider ohne Episoden-Laufzeiten | Laufzeit-neutraler Score, runtime_coverage-Deckel |

Ergänzend Katalog-Fixtures (`tests/fixtures/catalog/`): Season-24, Season-6, Show-3-Seasons, Film — als Suchraum-Gegenstücke mit Provider-Laufzeiten.

## Suite U: Unit — Analyzer-Parsing (media-tools-Repo)

Läuft in CI des media-tools-Dienstes gegen Binär-Mini-Fixtures (handgebaute MPLS/CLPI/IFO-Dateien, wenige KB):

* **U-01 … U-04**: MPLS-Parsing (Zeitkonvertierung 45 kHz exakt per Tabelle bekannter Werte; EntryMark vs. LinkPoint; Multi-Angle-Item; SubPath-Typen).
* **U-05 … U-07**: CLPI (Stream-Typ-Bytes → Codec-Enum vollständig; STC-Sequenzen; Größen-Plausibilisierung).
* **U-08 … U-11**: IFO (BCD-Zeit inkl. 29.97-Rundung per Tabellenwerte; Zellkategorien/Angle-Blocks; PGC-Zeit vs. Zellsumme; Program Map → Marken).
* **U-12**: Kanonisierung/Signatur — byte-identisches BLAKE3 für definierte Strukturen (Golden-Hashes im Repo); Permutations-Invarianz (Playlist-Reihenfolge im Input ändert Signatur nicht); Sensitivität (eine geänderte Marke ändert die Signatur).
* **U-13**: JSON-Schema-Validierung aller Bibliotheks-Fixtures gegen `disc-analysis.v1.json`.

## Suite C: Klassifikator (PHP, pure — Fixtures rein, Golden Files raus)

* **C-GOLD**: Jede Bibliotheks-Fixture → vollständiges Klassifikationsergebnis (Klasse, Confidence ± 0.001, rules_fired, disc-Flags) gegen Golden File. Golden-Änderungen brauchen Begründung im PR (Kalibrierungs-Verpflichtung des Regelkatalogs).
* **C-01 … C-24**: Regel-Einzeltests, je Regel mindestens ein Positiv- und ein Negativ-Fall aus synthetischen Minimalstrukturen (Regel-IDs 1:1: C-01 ⇔ R-01 usw.; C-12 prüft zusätzlich die 0.6-Ratio-Grenze exakt beidseitig).
* **C-K1 … C-K4**: Konsistenzphase (gewichtetes Mittel nachgerechnet; Review-Schwelle beidseitig; K-03-Rückstufung stellt nie um, nur zurück; K-04-Malus).
* **C-DET**: Determinismus — 100 Läufe über `bd-obfuscated-01` liefern byte-gleiche Evidence.
* **C-MAN**: manuelle Klassifikation überlebt Re-Run; `heuristic_disagrees` wird gesetzt.

## Suite M: Mapper (PHP, pure)

* **M-GOLD**: Fixture × Katalog-Gegenstück → vollständiges Alignment (Zuordnungen, Confidences ± 0.001, Evidence-Kernfelder) als Golden Files; deckt S-01 (Box), S-03 (Pfad), Doppelfolge, PAL, Mehrfachgruppen, laufzeitlos ab.
* **M-P1 … M-P5**: die fünf Eigenschaften aus der [Algorithmus-Spezifikation](mapping-algorithm.md) als Property-Tests (je 500 Zufallsinstanzen, Seeds fixiert): P-1 Rekonstruktion (Rauschen ≤ 5 %), P-2 kein confident wrong (adversariale Permutationen erreichen nie 0.90), P-3 Injektivität/Ordnung, P-4 Determinismus, P-5 Kontext-Monotonie.
* **M-01 … M-12**: Randfälle einzeln — leerer Suchraum (S-05), N=1, M=1, alle Laufzeiten identisch (ambiguity_malus greift, complete_monotone_boost nur bei Vollbelegung), Inversions-Nachlauf verbessert korrekt, Doppel-Match nur auf Nachbarn, Segment-Snapping mit/ohne Marke im Fenster, Vererbungs-Selektion (meiste Confirms, Tie ältestes), Suchraum-Schutzgrenze, Auto-Confirm-Bedingungen (a)–(e) je einzeln verletzt ⇒ kein Auto-Confirm.
* **M-CAL**: Kalibrierungs-Suite — Gewichts-/Toleranz-Defaults gegen die Golden-Gesamtheit; schlägt bei Default-Drift an.

## Suite P: Playback-Übersetzung (Integration, DB + Fake-Zeit)

* **P-SPAN-01 … 08**: Span-Reducer pur — Kadenz-Jitter innerhalb Toleranz (eine Spanne), Seek vorwärts/rückwärts (Spannentrennung), pause/play, Batch-Grenzen-Invarianz (gleiche Events, andere Batches ⇒ gleiche Spannen), min_span-Verwurf, playlist_change-Abschluss, Wanduhr-Sprung durch Uhrkorrektur.
* **P-TRANS-01**: das Kernszenario (Modulkapitel „unverhandelbarer Regressionsanker"): 6-Episoden-Fixture, nur Playlist 2 gespielt ⇒ genau eine Episode watched, Disc partial, keine weiteren Watch-State-Zeilen.
* **P-TRANS-02 … 10**: Play-All-Grenzüberquerung (Beispiel der [Playback-Spezifikation](playback-translation.md) exakt nachgestellt), Segmentgrenze halboffen (Position exakt auf Grenze → rechtes Segment), recap/credits zählen, bonus/unassigned nicht, unknown_playlist, unresolved_title, Idempotenz (Doppelverarbeitung ⇒ identischer Endzustand inkl. Audit-Zählern), Reprocessing nach später Bestätigung (occurred_at-Treue gegen Fundament-Konfliktauflösung), Korrektur-Fall erzeugt metadata_conflict statt Rücknahme.
* **P-MODE-01 … 05**: title_only ≥ 95 % (Anrechnung), 94 % (below_threshold), Zapping-Ausschluss, Segment-Playlist im title_only (keine Anrechnung; Ausnahme degenerierter Fall), open_close_only (nie automatisch; Ack-Karte entsteht; AcknowledgeManualPlayback rechnet an; Verfall nach 14 Tagen).
* **P-SESS-01 … 06**: Session-Lebenszyklus — idempotentes open (session_key), Fremd-open auf aktive Session, Sweeper stale/discarded/finalize, Reaktivierung durch Batch, end idempotent, Events nach end (409).

## Suite DB: Constraints und Invarianten

* **DB-01**: Segment-Exclusion (Überlappung wirft; Anschlussgrenzen exakt erlaubt).
* **DB-02/03**: partielle Unique-Indizes (zweites bestätigtes Ganz-Playlist- bzw. Segment-Mapping wirft).
* **DB-04**: Invariante 3 gemischt (Ganz + Segment confirmed) scheitert in der Action mit `disc.mapping_conflict`.
* **DB-05**: Invariante 4 (Container-Ziel) scheitert in Action und API (422).
* **DB-06**: Kaskaden — File-Löschung räumt Struktur, Sessions bleiben (SET NULL); Playlist-Löschung räumt Segmente/Mappings.
* **DB-07**: `user_disc_status`-View gegen handberechnete Szenarien (unmapped/unwatched/partial/watched; nur confirmed zählt).
* **DB-08**: Progress-Cache-Konsistenz — View vs. Cache nach Event-Folgen identisch; `mediaforge:rebuild-progress` stellt Gleichheit nach künstlicher Cache-Korruption her.

## Suite API: Verträge

* **API-SCHEMA**: OpenAPI gegen echte Responses aller Disc-Routen (Konventions-Standard).
* **API-01 … 14**: je Fehlercode des [Katalogs](api-reference.md) ein erzeugender Test (Tabelle 1:1).
* **API-SCOPE**: Scope-Matrix der Disc-Routen (inkl. der Verschärfung: `admin` scheitert am Player-Protokoll).
* **API-PLAYER-01 … 04**: Protokoll-Semantik — Batch-Teilannahme (rejected-Einzelfälle), Duplikat-ULIDs als duplicates, Herabstufungs-Antwort (`accepted_reporting`), disc_ref-Ablauf (422).
* **API-PAG**: Cursor-Stabilität der Disc-Liste bei Einfügungen.

## Suite PB: Player-Konformität (Vertragstests gegen Session-Simulator)

Die zehn Konformitätspunkte der [Playback-Spezifikation](playback-translation.md) als wiederverwendbare Suite, die jede Player-Integration in ihrer CI ausführt (der Simulator spielt einen MediaForge-Server nach; Referenzformate, Kadenz, finale Positionen, Menü-Übergänge, Batch-Retry, UTC, end-Verhalten, Reconnect, Silence nach end, Batch-Limit — PB-01 … PB-10). Bestehen ⇒ Integration darf `playlist_position` deklarieren; die Fähigkeits-Matrix im [External-Player-Kapitel](../../connectors/external-player.md) verweist auf den PB-Status je Player.

## Suite E2E (Dusk/Browser, kritische Pfade)

* **E2E-01**: Review-Fließband — Fixture-Disc mit 6 Vorschlägen, Sammel-Bestätigung, Task-Auflösung, Weiter-Navigation.
* **E2E-02**: Segment-Editor — Doppelfolge teilen mit Marken-Snapping, Speichern, Anrechnung-Vorschau korrekt.
* **E2E-03**: Set-Matrix — Box vervollständigen, Spalten-Häkchen, Zell-Navigation.
* **E2E-04**: Ack-Karte — open_close-Session erzeugen (API), Karte erscheint, zwei Episoden anrechnen, Watch-State sichtbar.

E2E bleibt bewusst schmal (vier Flows); alles Fachliche ist darunter abgedeckt (Test-Strategie des Handbuchs: Browser-Tests prüfen Verdrahtung, nicht Logik).

## Rückverfolgungs-Matrix (Auszug)

| Normative Quelle | Abschnitt | Tests |
|---|---|---|
| formats-bluray | Zeitmodell 45 kHz | U-01, U-12 |
| formats-bluray | Anomalie-Katalog | U-13, C-GOLD (`bd-anomalies-01`) |
| formats-bluray | Signatur-Kanonisierung | U-12, C-DET |
| formats-dvd | BCD/29.97-Rundung | U-08 |
| formats-dvd | ARccOS-Verhalten | C-GOLD (`dvd-arccos-01`) |
| classification-rules | jede Regel R-nn/K-nn | C-01…C-24, C-K1…K4 |
| mapping-algorithm | Eigenschaften P-1…P-5 | M-P1…M-P5 |
| mapping-algorithm | Auto-Confirm (a)–(e) | M-12 |
| playback-translation | Span-Algorithmus | P-SPAN-* |
| playback-translation | processing_note-Katalog | P-TRANS-06/07, P-MODE-02 |
| playback-translation | Konformitäts-Prüfliste | PB-01…PB-10 |
| api-reference | Fehlercode-Katalog | API-01…14 |
| Modulkapitel | Invarianten 1–6 | DB-01…DB-08, P-TRANS-09 |
| Modulkapitel | Kernszenario Regel 11 | P-TRANS-01, E2E-04 |

Vollständige Matrix als generierte Tabelle in CI (Annotation `@covers-spec` an den Tests; der Build bricht, wenn eine als normativ markierte Spec-Kennung ohne Test bleibt — derselbe Mechanismus wie die Contract-Drift-Prüfung der API-Konventionen).

## Betriebs-Smoke (Deploy-Gate)

Drei Prüfungen nach jedem Deploy gegen die Staging-Bibliothek (Runbook-Einbindung: [developer-handbook/runbooks.md](../../developer-handbook/runbooks.md)): (1) Reanalyse einer Referenz-Disc endet `analyzed` mit erwarteter Signatur, (2) Klassifikation der Referenz-Disc gleicht dem Golden-Stand, (3) simulierte 30-Sekunden-Session rechnet exakt eine Episode an. Die drei Prüfungen sind absichtlich die Miniatur der drei Referenzebenen des Datenmodells (Struktur/Interpretation/Nutzung) — wenn sie bestehen, ist die Kette Ende-zu-Ende intakt.
