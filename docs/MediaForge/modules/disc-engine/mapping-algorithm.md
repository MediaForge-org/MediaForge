# Episoden-Mapping: Algorithmus-Spezifikation

Vertiefung zu [modules/disc-engine.md](../disc-engine.md), Abschnitte „Episoden-Mapping" und „Doppelfolgen und Segment-Mappings". Normative Spezifikation des `EpisodeMapper` — einer puren Funktion `align(candidates, episodes, context) → MappingProposal`. Wie beim [Klassifikationsregel-Katalog](classification-rules.md) gilt: Der Katalog hier ist die Spezifikation, der Code ihre Implementierung; jede Formel und jeder Parameter ist testverankert ([test-catalog.md](test-catalog.md)).

## Problemdefinition

Gegeben: N Episode-Kandidaten-Playlists P₁…P_N (aufsteigend nach `playlist_ref`; bei Set-Kontext über die Set-Reihenfolge der Discs konkateniert), M Katalog-Episoden E₁…E_M (aufsteigend nach `sort_index`, mit Provider-Laufzeit `runtime_ms`, die fehlen darf). Gesucht: eine partielle, injektive, ordnungsbewusste Zuordnung P → E mit Confidence je Paar, sodass falsche Zuordnungen unter der Auto-Schwelle bleiben (**Design-Ziel „kein confident wrong"**: lieber Confidence drücken als elegant raten).

Injektiv heißt: keine zwei Playlists derselben Disc-Analyse auf dieselbe Episode (Wiederholungs-Discs bilden getrennte Analysen; Modulkapitel Edge Case „Dieselbe Episode auf mehreren Discs"). Partiell heißt: Playlists dürfen unzugeordnet bleiben (Bonus-Fehlklassifikat in der Gruppe) und Episoden unbelegt (Disc 2 von 4 enthält eben nur E07–E12).

## Stufe 0: Kontextermittlung

Der Suchraum-Beschluss vor jedem Alignment. Prioritätskaskade (erste zutreffende gewinnt), je Quelle normativ:

**S-01 — Bestätigtes Disc-Set mit Container.** `disc_sets.container_item_id` gesetzt und `confirmed_at` nicht null. Suchraum: alle konsumierbaren Episoden unterhalb des Containers (Season ⇒ deren Episoden; Show ⇒ alle Episoden aller Seasons, Reihenfolge `season.sort_index, episode.sort_index`). Set-Positions-Prior aktiv (Score-Term W₃).

**S-02 — Edition-Verknüpfung.** Das Image hängt als `edition_file` an einem Container-Item (Katalog-Fundament). Suchraum wie S-01 ohne Set-Prior.

**S-03 — Pfad-Analyse.** Der Katalog-Matcher des Fundaments erkennt aus dem Pfad (`…/Serie XY/Season 03/Disc 2.iso`) Show + Season (+ Disc-Nummer als unbestätigten Set-Hinweis). Suchraum: die erkannte Season; bei nur-Show-Erkennung die ganze Show mit Warn-Evidence `{context_breadth: "show"}` und Confidence-Deckel 0.85 (breiter Suchraum ⇒ nie Auto-Confirm). Sonderfall: Erkennt der Matcher einen **Film**, und die Klassifikation lieferte `main_feature`, wird das triviale 1:1-Mapping vorgeschlagen (Laufzeit-Score als Confidence); mehrere `main_feature` (Fassungen) ⇒ alle auf den Film (Modulkapitel).

**S-04 — Volume-Label/Meta-Titel.** `label`/`meta_title` (Formatreferenzen) durch den Katalog-Matcher als Titelsuche. Nur wirksam bei eindeutigem Treffer mit Namens-Score ≥ 0.9; Confidence-Deckel 0.75 (nie Auto-Confirm). Evidence `{context_source: "label", matched: "…"}`.

**S-05 — Kein Kontext.** Kein Mapping; Review-Task „Disc zuordnen" (Modulkapitel). Der Task bietet die S-03/S-04-Teiltreffer als Vorschlagsliste an, entscheiden muss der Mensch.

Provider-Laufzeiten fehlen bei manchen Episoden (frisch angelegte Serien, Specials). Episoden ohne Laufzeit bleiben im Suchraum; ihr Laufzeit-Score ist neutral 0.5 (weder Beleg noch Gegenbeleg), und ein Alignment, das überwiegend auf laufzeitlosen Episoden ruht, deckelt bei 0.8 (Evidence `{runtime_coverage}` = Anteil der Episoden mit Laufzeit).

## Stufe 1: Normalisierung

**N-01 — Kandidaten-Ordnung.** Innerhalb einer Disc nach `playlist_ref`; über Set-Discs in Set-Reihenfolge. `double_episode_suspect`-Kandidaten (R-44) bleiben in der Sequenz (sie belegen im Alignment zwei Episoden-Slots, siehe DP-Erweiterung).

**N-02 — PAL-Speedup-Korrektur.** Aktiv, wenn Framerate 25/50 (DVD-PAL immer; BD nur bei 25p-Streams, Formatreferenzen). Test: `median(runtime_ep) / median(dur_pl)` über alle Fenster-Paare; liegt der Quotient in [1.035, 1.055], werden alle Playlist-Laufzeiten für das Scoring mit 25/23.976 ≈ 1.0427 multipliziert. Evidence `{pal_speedup_applied: true, ratio_observed}`. Die Korrektur ist alles-oder-nichts je Disc (kein Per-Playlist-Mischmasch — entweder die Disc ist ein PAL-Master oder nicht).

**N-03 — Doppel-Kandidaten-Expansion.** Jeder R-44-Kandidat wird für das Alignment als Pseudo-Paar (Pᵢᵃ, Pᵢᵇ) mit je halber Laufzeit geführt; ein Match beider Hälften auf benachbarte Episoden erzeugt den Segment-Mapping-Vorschlag (Stufe 4).

## Stufe 2: Paar-Scores

Score je Paar (Pᵢ, Eⱼ) als gewichtete Summe, alle Terme in [0, 1]:

```
score(i, j) = W1·runtime(i, j) + W2·order(i, j) + W3·setpos(i, j) + W4·anatomy(i)
Gewichte (Settings disc_engine.mapper.*):
  W1 = 0.55 (weight_runtime)   W2 = 0.20 (weight_order)
  W3 = 0.15 (weight_setpos)    W4 = 0.10 (weight_anatomy)
  ohne Set-Kontext: W3 → 0, W1 → 0.65, W2 → 0.25
```

**Laufzeit-Term** (Modulkapitel, präzisiert): `runtime(i,j) = 1 − min(1, |dur_i − rt_j| / (tol · rt_j))` mit `tol = 0.12` (`mapper.runtime_tolerance`). Fehlt `rt_j`: konstant 0.5. Der Term ist bewusst linear statt gaußisch: Die Ableitung bleibt konstant, kleine Laufzeitfehler werden nicht schöngerechnet, und ab `tol` Abweichung ist der Beleg exakt null statt asymptotisch.

**Reihenfolge-Term:** wird nicht paarweise, sondern über die Alignment-Struktur vergeben (monotone Pfade bevorzugt) — technisch als Übergangs-Bonus/-Malus im DP (unten), hier als Paar-Nullterm geführt. In der Evidence erscheint er als `order_bonus` des Pfads.

**Set-Positions-Term:** Bei „Disc d von D" mit M Episoden im Container: erwartetes Fenster `[⌊M·(d−1)/D⌋ + 1, ⌈M·d/D⌉]`, erweitert um ±1. `setpos(i,j) = 1` für j im Fenster, sonst `max(0, 1 − (abstand/M)² · 4)` — quadratischer Abfall (Modulkapitel: „Abweichung kostet quadratisch").

**Anatomie-Term:** Gruppenkohärenz aus R-42/N-03 der Klassifikation: 1.0 bei Mehrheits-Anatomie, 0.5 bei Abweichung, 0.75 ohne verwertbare Marken (< 2). Der Term hängt nur von i ab — er belohnt vertrauenswürdige Kandidaten, nicht spezifische Paarungen.

## Stufe 3: Sequenz-Alignment (DP)

Globales Alignment mit Lücken beiderseits, Variante von Needleman-Wunsch über der Score-Matrix:

```
D(0, 0) = 0
D(i, 0) = D(i−1, 0) + gap_p          # Playlist unzugeordnet lassen
D(0, j) = D(0, j−1) + gap_e          # Episode unbelegt lassen
D(i, j) = max(
    D(i−1, j−1) + score(i, j) + mono_bonus,     # Match in Ordnung
    D(i−1, j)   + gap_p,                        # Playlist überspringen
    D(i, j−1)   + gap_e,                        # Episode überspringen
    D(i−1, j−1) + score(i, j) − inv_penalty     # via Inversions-Kante (s. u.)
)
gap_p = −0.15 (mapper.gap_playlist)   gap_e = −0.05 (mapper.gap_episode)
mono_bonus = +0.08 (mapper.mono_bonus)  inv_penalty = 0.25 (mapper.inversion_penalty)
```

`gap_e` ist bewusst billig (unbelegte Episoden sind der Normalfall bei Teil-Discs), `gap_p` teuer (eine als Episode klassifizierte Playlist ohne Zuordnung ist ein Warnsignal). **Inversionen** (Disc-Reihenfolge ≠ Episodenreihenfolge — existiert real bei Bonus-Reihenfolgen und Produktions- vs. Ausstrahlungsreihenfolge) werden über einen zweiten Lauf behandelt: Nach dem monotonen Optimum wird für jede unzugeordnete Playlist × unbelegte Episode geprüft, ob ein Direkt-Match mit `score − inv_penalty > gap_p + gap_e` das Gesamtergebnis verbessert; solche Kanten werden gierig absteigend nach Score ergänzt (injektiv bleibend). Das hält den Kern-DP monoton O(N·M) und macht Inversionen als explizite Evidence sichtbar (`inversions: [{playlist_ref, episode, score}]`) statt sie im DP zu verstecken.

**Doppel-Kandidaten:** Das Pseudo-Paar (Pᵢᵃ, Pᵢᵇ) aus N-03 nimmt als zwei Sequenz-Positionen teil; ein gültiger Doppel-Match verlangt Zuordnung auf **benachbarte** Episoden (j, j+1) — der DP erzwingt das, weil a und b in der Kandidaten-Sequenz benachbart sind und nur der monotone Pfad beide matcht. Matcht nur eine Hälfte, verwirft Stufe 4 den Doppel-Verdacht (Playlist wird als normale Einzel-Episode mit der Gesamtdauer neu bewertet — einmalige Wiederholung von Stufe 2/3 ohne Expansion; Rekursionstiefe fest 1).

**Confidence-Normalisierung:** Pfad-Score → Paar-Confidence: `conf(i,j) = clamp(score(i,j) + mono_share(i,j), 0, 1) · caps`. `mono_share` verteilt den Pfad-Bonus gleichmäßig auf die Matches des monotonen Rückgrats (Inversions-Kanten erhalten ihn nicht); `caps` sind die Deckel aus Stufe 0 (S-03-Show 0.85, S-04 0.75, runtime_coverage-Deckel 0.8). Zusätzlich global: `conf ≤ 0.95` immer (Restunsicherheit ist ehrlich; 1.0 gibt es nur für `manual`/`inherited`).

**Zweitbestes Alignment:** Der DP wird mit Verbots-Maske des besten Pfads erneut gelöst (jede Match-Kante des Optimums einzeln verboten, bestes Ergebnis der Läufe = zweitbeste Alternative je Playlist). `evidence.second_best = {episode, conf_delta}` — das Review-UI zeigt „beste vs. zweitbeste" (Modulkapitel). Ein kleiner `conf_delta` (< 0.1) drückt die Confidence des Bestvorschlags um 0.1 (`ambiguity_malus`): Wenn zwei Episoden fast gleich gut passen, ist Sicherheit gelogen. Typischer Auslöser: Staffeln mit uniformen Laufzeiten — dort trägt dann allein Reihenfolge + Set-Fenster, was gewollt unter der Auto-Schwelle bleibt, außer die Gruppe ist set-konsistent vollständig (alle Slots besetzt, keine Lücken — dann ist die Reihenfolge beweiskräftig: `complete_monotone_boost` +0.05).

## Stufe 4: Vorschlags-Erzeugung

Aus dem Alignment entstehen `disc_episode_mappings`-Zeilen (`status='suggested'`, `mapping_source='heuristic'`):

* **Ganz-Playlist-Mappings** für Einzel-Matches (`segment_id = NULL`).
* **Segment-Mapping-Paare** für bestätigte Doppel-Matches: zwei Segmente, Grenze an der Kapitelmarke, die der Provider-Laufzeit von Eⱼ am nächsten liegt (Suchfenster ± 3 min; ohne Marke im Fenster: Grenze exakt bei `rt_j`, Segment-`source='heuristic'` statt `'chapter_marks'`, zusätzlicher Review-Hinweis `no_snap_mark`). Segment-Vorschläge sind nie Auto-Confirm-Kandidaten (Modulkapitel).
* **Play-All-Segmentierung:** Für als `play_all` klassifizierte Playlists mit R-30/R-31-Members: Segmente entlang der kumulierten Member-Grenzen (Marken-Snapping wie oben), jedes Segment gemappt wie sein Member. Erzeugt erst, wenn die Member-Mappings bestätigt sind (`ConfirmDiscEpisodeMapping` dispatcht die Ableitung) — vorher wäre es Spekulation auf Spekulation.

Evidence je Mapping (JSONB `evidence`, Vertragsfelder):

```json
{
  "mapper_version": "1.7.0",
  "context": {"source": "S-01", "container": "01J…", "set_position": [2, 4]},
  "scores": {"runtime": 0.94, "setpos": 1.0, "anatomy": 1.0, "order_bonus": 0.08},
  "confidence_calc": {"raw": 0.92, "caps": [], "ambiguity_malus": 0,
                      "complete_monotone_boost": 0.05, "final": 0.95},
  "second_best": {"episode_id": "01J…", "conf_delta": 0.31},
  "pal_speedup_applied": false,
  "runtime_coverage": 1.0,
  "alignment_snapshot": {"n": 6, "m": 24, "matches": 6, "gaps_p": 0, "inversions": 0}
}
```

## Stufe 5: Schwellwert-Entscheidung

Modulkapitel-Zonen, hier der exakte Auto-Confirm-Vertrag: Auto-Bestätigung genau dann, wenn (a) `disc_engine.auto_confirm_mappings = true`, (b) **jede** Playlist der Episodengruppe eine Zuordnung mit `conf ≥ 0.90` hat, (c) das Rückgrat vollständig monoton ist (`inversions = 0`, `gaps_p = 0`), (d) kein Mapping ein Segment-Mapping ist, (e) kein Deckel aus Stufe 0/3 aktiv war. Auto-Confirm läuft als `ConfirmDiscEpisodeMapping` mit System-Actor je Mapping (einzeln auditiert; ein Sammel-Confirm wäre im Audit nicht rückverfolgbar genug).

## Mapping-Vererbung (Signatur-Treffer)

Algorithmus bei `signature`-Schritt-Treffer (Modulkapitel „Mapping-Vererbung"): Quell-Image = das mit den meisten bestätigten Mappings (Tie: ältestes). Je bestätigtem Quell-Mapping wird das Ziel über `playlist_ref` (identisch per Signatur-Definition) übernommen; Segment-Mappings übernehmen die Segmentgrenzen wörtlich. `mapping_source='inherited'`, `evidence = {inherited_from: {image_id, mapping_id}}`, Confidence des Originals. Status nach Setting (`inherit_confirmed_mappings`, Default true ⇒ direkt `confirmed` mit System-Actor). Nicht vererbt werden: abgelehnte Mappings (die Ablehnung galt dem Vorschlag, nicht der Struktur) und `suggested`-Reste — der Mapper läuft für Unbestätigtes normal.

## Komplexität und Grenzen

O(N·M) DP + O(K · N·M) für K Zweitbest-Läufe (K = Matches ≤ N) + O(U·V) Inversions-Nachlauf. Realistische Maxima: N ≤ 60 (Set-weit), M ≤ 500 (Komplett-Show-Kontext) ⇒ < 10⁶ Zellen, Mikrosekundenbereich, irrelevant. Der Mapper läuft synchron im `map`-Schritt des Analyse-Jobs. Harte Grenze als Schutz: N·M > 10⁷ ⇒ Abbruch mit Review-Task `mapping_search_space_too_large` (praktisch unerreichbar; Schutz gegen pathologische Kontexte).

## Durchgerechnetes Beispiel

Disc 2/4 einer 24-Episoden-Staffel (S-01), 6 Kandidaten à ~43 min, Provider-Laufzeiten 41–44 min, Fenster E07–E12 (±1: E06–E13).

Score-Matrix (Auszug, Zeilen P₁…P₆, Spalten E06…E13, W-gewichtet):

| | E06 | E07 | E08 | E09 | E10 | E11 | E12 | E13 |
|---|---|---|---|---|---|---|---|---|
| P₁ | .78 | **.92** | .89 | .90 | .74 | .88 | .90 | .77 |
| P₂ | .77 | .90 | **.93** | .88 | .72 | .89 | .87 | .76 |
| P₃ | .70 | .84 | .85 | **.91** | .69 | .84 | .86 | .70 |
| P₄ | .58 | .71 | .70 | .72 | **.90** | .73 | .71 | .59 |
| P₅ | .76 | .88 | .90 | .89 | .73 | **.92** | .90 | .75 |
| P₆ | .75 | .89 | .87 | .88 | .71 | .89 | **.93** | .76 |

P₄/E10 sticht heraus (E10 ist eine 38-min-Episode, P₄ die kürzeste Playlist — die Laufzeit-Kopplung zieht das Alignment zusammen). Monotoner DP-Pfad: P₁→E07 … P₆→E12, Pfad-Score 5.51 + 6 × mono_bonus. Uniforme Nachbarn (P₁: second_best E09, delta 0.02 roh) — aber der `complete_monotone_boost` greift (6/6 Slots, keine Lücken) und die Set-Fenster-Kante drückt Alternativen außerhalb E07–E12: finale Confidences 0.90–0.95, `ambiguity_malus` entfällt, da `conf_delta` **nach** Fenster-Term > 0.1. Auto-Confirm-Prüfung: (a)–(e) erfüllt ⇒ sechs auditierte System-Confirms. Hätte die Box stattdessen 6 uniforme 42:00-Episoden ohne Laufzeitvarianz **und** wäre P₄ nicht unterscheidbar: `conf_delta < 0.1` bliebe, malus −0.1 ⇒ 0.80–0.85 ⇒ Zone 2, Review — genau das gewollte Verhalten.

## Eigenschaften (testverankert)

* **P-1 Rekonstruktion:** Aus Episoden konstruierte Kandidaten (Laufzeiten + Rauschen ≤ 5 %) werden vollständig und korrekt aligniert (Property-Test, Modulkapitel Tests).
* **P-2 Kein confident wrong:** Rauschen > tol ⇒ keine Zuordnung erreicht 0.90 (Property-Test mit adversarialen Permutationen).
* **P-3 Injektivität & Ordnung:** Kein Output verletzt Injektivität; Inversions-Kanten sind explizit ausgewiesen.
* **P-4 Determinismus:** Gleicher Input ⇒ byte-gleiche Evidence (keine Zufallsquellen; Tie-Breaks lexikographisch über `playlist_ref`/`sort_index`).
* **P-5 Monotonie der Kontexte:** Engerer Suchraum (S-01 statt S-03) verschlechtert nie die Confidence korrekter Zuordnungen (Kalibrierungs-Suite).

## Settings-Referenz

| Schlüssel (`disc_engine.mapper.*`) | Default | Verwendung |
|---|---|---|
| `runtime_tolerance` | 0.12 | Laufzeit-Term |
| `weight_runtime` / `weight_order` / `weight_setpos` / `weight_anatomy` | 0.55 / 0.20 / 0.15 / 0.10 | Score-Gewichte |
| `gap_playlist` / `gap_episode` | −0.15 / −0.05 | DP-Lücken |
| `mono_bonus` / `inversion_penalty` | 0.08 / 0.25 | DP-Übergänge |
| `ambiguity_delta` / `ambiguity_malus` | 0.10 / 0.10 | Zweitbest-Regel |
| `complete_monotone_boost` | 0.05 | Stufe 3 |
| `segment_snap_window_ms` | 180000 | Marken-Snapping |
| (übergeordnet) `disc_engine.auto_confirm_mappings` | true | Stufe 5 |
| (übergeordnet) `disc_engine.inherit_confirmed_mappings` | true | Vererbung |

Kalibrierungs-Verpflichtung wie beim Klassifikator: Default-Änderungen laufen gegen die Golden-Suite; die Zonen-Schwellen selbst (0.90/0.60) stehen im Modulkapitel und sind dort normiert.
