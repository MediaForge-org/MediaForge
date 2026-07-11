# Hörbuch-Assembler: API-, UI- und Test-Referenz

Vertiefung zu [modules/audiobook-assembler.md](../audiobook-assembler.md), Abschnitte „API-Endpunkte", „React-/Inertia-Komponenten" und „Tests". Struktur analog zu [Disc-Engine-API](../disc-engine/api-reference.md)/[-UI](../disc-engine/ui-reference.md)/[-Tests](../disc-engine/test-catalog.md); die [API-Konventionen](../../api/conventions.md) gelten unverändert.

## API-Referenz

Externe Konsumenten: CLI-Batch-Assemblierung (Sammlungs-Migration), ABS-nahe Automatisierung, Skripting der Artefakt-Erzeugung.

### `GET /api/v1/audiobooks/{ulid}/assembly`

Scope `read`, member+. `{ulid}` ist die **Edition**. Antwort:

```json
{"id": "01J9AB…", "status": "chaptered",
 "sequence": {"source": "tags", "confidence": 0.97,
              "confirmed_by": null, "confirmed_at": null,
              "track_count": 97, "total_duration_ms": 21618004,
              "tracks": [{"seq": 1, "file_id": "01J9…", "disc_no": 1, "track_no": 1,
                          "duration_ms": 252330, "duration_method": "header",
                          "start_offset_ms": 0, "display_name": "CD1/Track01.mp3"}]},
 "chapter_sets": [{"id": "01J9AC…", "origin": "official_provider",
                   "origin_detail": "audnexus:asin=B004V0GLBW",
                   "is_official": true, "is_active": true,
                   "alignment_status": "aligned", "confidence": 0.90,
                   "chapter_count": 31,
                   "anomalies": ["provider_inaccurate"],
                   "activated_at": "2026-06-02T10:11:12Z", "activated_by": null}],
 "active_chapters": [{"seq": 1, "title": "Widmung", "start_ms": 0, "end_ms": 252330,
                      "title_source": "source"}],
 "artifacts": [{"kind": "m4b", "status": "active", "stale": false,
                "built_at": "2026-06-02T11:40:00Z", "size_bytes": 812345678}]}
```

`display_name` ist der editions-relative Pfad (keine absoluten Pfade, Konventions-Security). `active_chapters` ist die bequeme Projektion des aktiven Sets — Automatisierung, die nur Kapitel lesen will, braucht keinen zweiten Request. Nicht-assemblierte Editionen liefern 200 mit `status: null` und leeren Feldern (Existenz der Edition entscheidet über 404, nicht der Assembly-Zustand — die Frage „ist da schon etwas?" ist der Hauptanwendungsfall dieser Route).

### Mutationen

| Route | Wirkung | Fehler (`assembler.*`) |
|---|---|---|
| `POST …/assembly/sequence` | `SequenceAudiobookJob` (202, Operation) | `sequence_running` 409 |
| `PUT …/assembly/sequence` | `ReorderTracks`: Body `{"file_ids": [geordnet]}`, vollständiger Ersatz (atomar, wie Disc-Set-Reihenfolge); setzt `manual`, invalidiert Sets | `sequence_incomplete` 422 (fehlende/überzählige IDs), `unknown_file` 422 |
| `POST …/assembly/collect-sources` | `CollectChapterSourcesJob` (202) | `not_sequenced` 409 |
| `POST /api/v1/chapter-sets/{ulid}/activate` | `SelectActiveChapterSet` | `not_aligned` 409, `ai_requires_user` 403 (KI-Set ohne menschlichen Kontext — bei API-Aufruf ist der Token-User der Bestätiger; der 403 trifft nur System-/Workflow-Kontexte), `hierarchy_conflict` 409 (offener Konflikt-Review) |
| `PUT /api/v1/chapter-sets/{ulid}/chapters` | `EditChapters`: erzeugt `manual`-Set (Body: vollständige Kapitelliste) | `invalid_partition` 422 (Lücken/Überlapp/Grenzen), `empty_titles` 422 |
| `POST …/assembly/ai-proposal` | `RequestAiChapterProposal` (202) | `ai_engine_disabled` 409 |
| `POST …/assembly/build?targets=m4b,cue,abs` | Builder-Jobs (202, eine Operation je Target) | `no_active_set` 409, `unknown_target` 422, `build_running` 409 je Target |

`PUT …/chapters` präzisiert: Die eingereichte Liste muss die Partitions-Invariante erfüllen (der Server validiert identisch zum [Aligner](alignment-algorithm.md) A-5/A-6 — dieselbe Bibliotheksfunktion, ein Verhalten); das erzeugte `manual`-Set wird **nicht** automatisch aktiv (separater `activate`-Aufruf — bewusste Zwei-Schritt-Semantik, damit Automatisierung nie versehentlich aktiviert).

### Fehlercode-Katalog

Vollständig: `assembler.sequence_running`, `sequence_incomplete`, `unknown_file`, `not_sequenced`, `not_aligned`, `ai_requires_user`, `hierarchy_conflict`, `invalid_partition`, `empty_titles`, `ai_engine_disabled`, `no_active_set`, `unknown_target`, `build_running`, `stale_assembly` (409 bei Builds auf `stale`-Assemblies — erst Re-Sequenzierung bestätigen). Jeder Code hat einen erzeugenden Test (AB-API-Serie).

## UI-Referenz: `Audiobooks/Assembly`

Die Werkbank (Modulkapitel: drei Zonen) mit den Props- und Interaktions-Verträgen:

### Props-Vertrag (Kern)

```ts
interface AssemblyProps {
  edition: EditionHeaderRef;                 // Titel, Autor, Cover, Editions-Attribute
  assembly: {
    status: AssemblyStatus | null;
    sequence: SequenceZone; chapterSets: ChapterSetCard[];
    activeSetId: Ulid | null; artifacts: ArtifactPanel;
    staleness: { isStale: boolean; reason: string | null };
  };
  reviewTask: ReviewRef | null;              // gesetzt bei Einstieg aus Review-Inbox
  silenceMapAvailable: boolean;              // steuert Zeitachsen-Overlay
}
interface SequenceZone {
  source: SequenceSource | null; confidence: number | null;
  isManual: boolean; consensus: ConsensusDisplay;   // vorformulierte Konsens-/Dissens-Sätze
  tracks: TrackRow[];                        // + Evidenz-Spalten (tagNo, fileNo, folder)
  divergences: DivergencePointer[];          // Positionen, an denen Quellen differieren
}
```

`ConsensusDisplay` und `DivergencePointer` sind serverseitig aus der `sequencer_evidence` formuliert ([Sequenzierungs-Katalog](sequencing-rules.md)) — das UI rendert, es interpretiert nicht (Architekturregel 2, identisch zur Disc-Engine-`evidenceSummary`).

### Zone 1: Sequenz-Editor

Trackliste virtualisiert (97+ Zeilen), Spalten: Position, Anzeige-Name, Tag-Nr. (mit disc), Dateinamen-Nr., Ordner, Laufzeit, `duration_method`-Marker (dekodiert = Präzisions-Badge). Divergenzstellen tragen Warn-Marker; ein Klick scrollt die Gegenüberstellung ein (die beiden Ordnungen ab der Divergenz, 5 Zeilen Kontext). Drag-and-Drop nur nach explizitem „Reihenfolge bearbeiten" (versehentliches Umsortieren einer bestätigten 97-Track-Sequenz wäre teuer); der Bearbeitungsmodus zeigt ein persistentes Speichern/Verwerfen-Banner mit Änderungszähler und dem Hinweis „Speichern invalidiert n Kapitel-Sets". Laufzeit-Ausreißer (SQ-50) sind zeilen-markiert mit Kontextaktion „aus Sequenz ausschließen (Bonus)" — der Modulkapitel-Edge-Case als Ein-Klick-Pfad.

### Zone 2: Kapitel-Vergleich

Gemeinsame Zeitachse (voller Werkbereich, Zoom bis 1 min/vh), Sets als parallele Spuren: Kapitelgrenzen als Marken, Titel als Labels (ausgedünnt nach Zoomstufe), aktives Set hervorgehoben, KI-Sets mit permanentem „KI-Vorschlag — nicht offiziell"-Band (Architekturregel 5; das Band ist Teil der Komponente, nicht abschaltbar). SilenceMap als Hintergrund-Heatmap, wenn verfügbar (`silenceMapAvailable`) — gesnappte Grenzen liegen sichtbar „im Dunkeln". Interaktionen: Set-Aktivierung (Button je Karte; disabled mit serverseitigem Grund — `not_aligned`, Konflikt), Detail-Diff zweier Sets (Auswahl-Checkboxen ⇒ Tabelle: Grenzen-Δ in s, Titel nebeneinander, > 10-s-Differenzen hervorgehoben), Titel-Merge-Angebot als Banner bei Selector-Fall (c) ([Alignment](alignment-algorithm.md)), Kapitel-Editor (unten), Alignment-Report-Ansicht je Set (Drawer: Shift/Scale/Snapping-Liste, bei `failed_validation` der Fehlgrund samt Residuen-Plot bei `nonlinear_drift`).

### Kapitel-Editor

Bearbeitung erzeugt immer ein `manual`-Set (Modulkapitel `EditChapters`) — der Editor öffnet auf einer Kopie und sagt das („Bearbeitung erzeugt eine manuelle Struktur auf Basis von …"). Grenzen ziehen mit Stille-Snapping (Magnet wie Disc-Segment-Editor, `Alt` löst), Doppelklick teilt, `Entf` verschmilzt, Titel inline editierbar (`title_source` wird `manual`), Audio-Vorschau an jeder Grenze (±5 s, signierte Range-URL — Modulkapitel; Play-Button direkt an der Marke, Tastatur `Leertaste` auf fokussierter Grenze). Numerische Eingabe (hh:mm:ss.mmm) als gleichwertige Alternative. Speichern validiert client-seitig die Partition (schnelles Feedback), verbindlich serverseitig.

### Zone 3: Artefakt-Panel

Karten je Artefakt-Typ: Status, Alter, Größe, Signatur-Kurzform, Staleness-Warnung (Signatur-Divergenz mit „was hat sich geändert"-Auflösung: Sequenz/Set/Parameter), Build-Buttons (mit Parameter-Popover beim M4B: Codec-Pfad-Anzeige aus der Vorab-Prüfung — „COPY möglich" vs. „Transcode nötig, geschätzt ~4 h"), laufende Builds mit Operations-Fortschritt (Chunk n/K via Echo). ABS-Export-Karte zeigt den Zielpfad und den letzten Sync-Status des [ABS-Connectors](../../connectors/audiobookshelf.md), sofern konfiguriert.

### Zugänglichkeit

Zeitachsen-Interaktionen vollständig tastaturfähig (Grenzen als fokussierbare Elemente, Pfeiltasten ±100 ms, Shift ±1 s); Set-Spuren als beschriftete Gruppen; Diff-Tabelle als echte Tabelle (Screenreader-tauglich); Farbcodierung der Origins mit redundanten Icons (Design-System-Verpflichtung).

## Test-Katalog

### Fixture-Bibliothek (synthetisch, generiert im Test-Setup)

| Fixture | Inhalt | Exerziert |
|---|---|---|
| `ab-cd-folders` | 3 CDs × 12 Tracks, CD-lokale Tags | SQ-10 implied, SQ-30, Beispiel A |
| `ab-global-tags` | 97 Tracks, globale Tags, Dateinamen-Widerspruch an 43/44 | Konsens-Dissens, Beispiel B |
| `ab-filename-only` | 30 Tracks, keine Tags | Alleinquellen-Deckel, Beispiel C |
| `ab-vbr-broken` | VBR-MP3 ohne Xing, Header lügt +4 % | Dekodier-Trigger, Zeitachsen-Invalidierung |
| `ab-m4b-embedded` | Ein M4B, QuickTime-Track + widersprüchliche chpl | Q-02 Präzedenz, `dual_chapter_atoms` |
| `ab-cue-multi` | 3 CUEs (CP1252, eine mit totem FILE) | Q-05 komplett, `file_unresolved`, Konkatenation |
| `ab-official-shift` | Audnexus-Antwort mit Brand-Intro, SilenceMap | A-2-Suche, Alignment-Beispiel 1 |
| `ab-version-mismatch` | offizielle Liste 11 % länger | A-3-Fehlpfad, Beispiel 3 |
| `ab-outlier-jingle` | 4-s-Track zwischen 8-min-Tracks | SQ-50, UI-Ausschluss-Flow |
| `ab-id3-chap` | MP3s mit CHAP/CTOC, eine Datei mit Werkzeit-CHAP | Q-03 inkl. Umdeutung |
| `ab-sidecars` | ABS-JSON + m4b-tool-txt + kaputtes generisches JSON | Q-06 inkl. Ablehnung |

### Suiten

* **AB-SEQ-01…12** (Sequencer, pure): je Evidenzquelle Positiv/Negativ; Konsens-Formel nachgerechnet; Ausnahme-Regel (global-Dateiname schlägt lokal-Tags); Tie-Break; Re-Sequenzierung mit manual-Schutz und Delta-Review; ICU-Sortier-Reproduzierbarkeit (Locale-Matrix de/en/C).
* **AB-TL-01…04** (Timeline): Property bijektive Übersetzung (Modulkapitel); Dekodier-Trigger-Grenzfälle (0.9 %/1.1 %); Offset-Exaktheit (Summen ohne Drift über 500 Tracks); Set-Invalidierung bei Methodenwechsel.
* **AB-SRC-01…14** (Parser): je Quellformat Golden-Parsing der Fixture; Anomalie-Katalog vollständig (jede Anomalie ein erzeugender Fall); `asin_mismatch`-Verwerfung; 1-MB-Limit; CUE-Security (`FILE /etc/passwd` referenziert ins Leere); Idempotenz des Collectors (Re-Lauf ohne Duplikate, in-place-Update nur bei raw_source-Änderung).
* **AB-AL-01…10 + AL-P1…P5** (Aligner): die fünf Eigenschaften als Property-Tests; Grenzfall-Tabelle AL-P4 beidseitig; Beispiele 1–3 als Golden Reports; Selector-Konfliktfälle (a)–(c); Titel-Merge-Herkunftskette.
* **AB-ART-01…12** (Builder): Golden Files CUE/FFMETADATA/metadata.json (Byte-Vergleich); 99-Kapitel-Grenze; M4B COPY/TRANSCODE/Resume/Verify (Integrationscontainer); ABS `EXDEV`/`target_exists_foreign`; Signatur-No-Op; Staleness-Erkennung.
* **AB-API-01…14**: je Fehlercode ein Test; Scope-Matrix; `active_chapters`-Projektion; 200-bei-leerer-Assembly-Semantik.
* **AB-E2E-01…03** (Dusk): Kern-Flow des Modulkapitels („97 MP3s → eine Verifikation → ABS"); Sequenz-Divergenz-Review mit Gegenüberstellung; Kapitel-Edit mit Audio-Vorschau-Aufruf (Request-Assertion, kein echtes Audio im Browser-Test).
* **Unverhandelbare** (Modulkapitel): AB-GUARD-01 — kein automatischer Pfad aktiviert ein KI-Set (Action ohne User wirft; DB-CHECK `ai ∧ official` wirft; Workflow-Kontext wirft `ai_requires_user`); AB-GUARD-02 — Quell-Sets werden nie in-place editiert (EditChapters auf fremdem Set erzeugt Kopie, Original byte-identisch).

### Rückverfolgung

`@covers-spec`-Annotationen wie in der Disc-Engine; die CI-Matrix bindet: Sequenzierungs-Katalog SQ-nn → AB-SEQ, Formatreferenz Q-nn/Anomalien → AB-SRC, Alignment A-1…A-7/AL-Pn → AB-AL, Builder-Formate/Fehlerkatalog → AB-ART, API-Codes → AB-API. Bausteine ohne Testbindung brechen den Build.
