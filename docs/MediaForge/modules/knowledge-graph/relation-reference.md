# Kantentyp- und Cluster-Algorithmus-Referenz

Vertiefung zu [modules/knowledge-graph.md](../knowledge-graph.md). Normativ für: die vollständige Kantentyp-Semantik (Richtung, Inverse, erlaubte Subjekt/Objekt-Paare), den Zyklen-Check-Algorithmus, die `workCluster`-Äquivalenzklassen-Berechnung und die Erweiterungs-Prozedur für neue Kantentypen. Das Modulkapitel definiert die Architektur (relational statt Graph-DB, Herkunftsdisziplin); dieses Dokument ist die Implementierungsreferenz für `RelationTypeCatalog` und `GraphNeighborhoodService`.

## Kantentyp-Katalog (vollständig)

| Typ | Subjekt-Typ(en) | Objekt-Typ(en) | Inverses Label | Symmetrisch | Zyklenprüfung |
|---|---|---|---|---|---|
| `adaptation_of` | media_item | media_item | „Adaptionen von {subject}" | nein | nein |
| `remake_of` | media_item | media_item | „Remakes von {subject}" | nein | ja (Tiefenlimit 20) |
| `spin_off_of` | media_item | media_item | „Spin-offs von {subject}" | nein | nein |
| `sequel_to` | media_item | media_item | „Vorgänger von {subject}" (= `prequel_to` invers) | nein | ja |
| `prequel_to` | media_item | media_item | „Nachfolger von {subject}" (= `sequel_to` invers) | nein | ja |
| `same_work_as` | media_item | media_item | „Gleicher Stoff als" (beidseitig identisch) | **ja** | nein (Äquivalenzklasse) |
| `part_of_series` | media_item | media_item (Reihen-Repräsentant) — *veraltet, s. u.* | „enthält" | nein | nein |
| `based_on_person` | media_item | person | „Werke über {object}" | nein | nein |

**`part_of_series`** ist als Kantentyp im `CHECK`-Constraint vorhanden, aber seit Einführung von `work_series`/`work_series_members` (Modulkapitel) der **bevorzugte** Weg für Reihenzugehörigkeit ist die Mitgliedschaftstabelle, nicht die Kante — der Kantentyp bleibt für Fälle, in denen eine Reihe selbst noch kein `work_series`-Knoten ist (Übergangszustand während Provider-Import, bevor `ImportProviderRelationsJob` die Reihe materialisiert hat). Neue Kanten dieses Typs entstehen nur transient und werden vom Import-Job binnen desselben Laufs in `work_series_members` aufgelöst und gelöscht — ein `part_of_series`-Fund außerhalb eines laufenden Imports ist ein Datenqualitäts-Befund (`quality.orphaned_series_edge`).

### Inverse-Darstellung: exakte Regel

`sequel_to`/`prequel_to` sind **echte Gegenpaare**, keine reine UI-Beschriftung: Wird `A sequel_to B` gespeichert, erzeugt `RelationTypeCatalog::displayNeighborhood()` für B automatisch den Eintrag „Nachfolger: A" **ohne** eine zweite Datenbankzeile — die Inverse wird zur Anzeigezeit gedreht (Modulkapitel: „eine Kante, ein Fakt"). Die Speicherentscheidung, welche Richtung kanonisch ist, liegt bei der erzeugenden Quelle: Provider-Import speichert immer `sequel_to` (nie `prequel_to`) für Konsistenz; manuelle Eingabe über den Beziehungs-Editor kann beide Richtungen wählen, die Action normalisiert nicht um (beide sind gültige, gleichwertige Speicherformen desselben Fakts — nur *innerhalb* eines Kantentyp-Paars ist Konsistenz erzwungen, nicht *zwischen* verschiedenen Erzeugern).

### `same_work_as`: Normalisierung im Detail

```php
function normalizeSameWork(string $idA, string $idB): array {
    return $idA < $idB ? [$idA, $idB] : [$idB, $idA];   // lexikographischer ULID-Vergleich
}
```

`CreateRelation` wendet diese Normalisierung vor dem Insert an; ein Einfüge-Versuch in der „falschen" Richtung trifft denselben Unique-Key (`relation_type, subject_type, subject_id, object_type, object_id`) und wird als Update behandelt (Confidence-Max, Evidence-Merge), nie als zweite Zeile.

## Zyklenprüfung: Algorithmus

Für `remake_of`, `sequel_to`, `prequel_to` (die einzigen Typen mit `Zyklenprüfung: ja`) läuft bei `CreateRelation`/`ConfirmRelation` folgende Prüfung:

```sql
WITH RECURSIVE chain AS (
    SELECT object_id AS node, 1 AS depth
    FROM entity_relations
    WHERE relation_type = :type AND subject_id = :new_object_id AND status = 'confirmed'
  UNION ALL
    SELECT er.object_id, chain.depth + 1
    FROM entity_relations er
    JOIN chain ON er.subject_id = chain.node
    WHERE er.relation_type = :type AND er.status = 'confirmed' AND chain.depth < 20
)
SELECT 1 FROM chain WHERE node = :new_subject_id LIMIT 1;
```

Ein Treffer (die neue Kante würde den Graphen von `object` zurück zu `subject` schließen) ⇒ `CreateRelation` scheitert mit `knowledge_graph.cycle_detected` (422), Evidence enthält den gefundenen Pfad. Tiefenlimit 20 ist eine Schutzgrenze (keine reale Sequel-Kette ist länger; Überschreitung ⇒ konservative Ablehnung statt unbegrenzter Rekursion) — dieselbe Technik wie die Segment-Snapping-Fenstergrenzen anderer Module: eine harte, dokumentierte Zahl statt unbegrenzter Berechnung.

`sequel_to` und `prequel_to` teilen sich **eine** Zyklenprüfung (beide Typen werden für den Kettenaufbau als dieselbe gerichtete Relation behandelt, da `prequel_to` bei der Prüfung invertiert in die `sequel_to`-Kette eingerechnet wird) — eine Kette „A sequel_to B, C prequel_to B" beschreibt denselben Cluster und wird zyklenkonsistent gegen beide Richtungen geprüft.

## `workCluster`: Äquivalenzklassen-Algorithmus

`GraphNeighborhoodService::workCluster(entity)` berechnet die `same_work_as`-Äquivalenzklasse plus eine Schicht direkter Nicht-`same_work_as`-Kanten:

```sql
WITH RECURSIVE equiv AS (
    SELECT :entity_id AS node
  UNION
    SELECT CASE WHEN er.subject_id = equiv.node THEN er.object_id ELSE er.subject_id END
    FROM entity_relations er
    JOIN equiv ON er.subject_id = equiv.node OR er.object_id = equiv.node
    WHERE er.relation_type = 'same_work_as' AND er.status = 'confirmed'
)
SELECT node FROM equiv;
```

(Kein Tiefenlimit nötig: `same_work_as` ist per Konstruktion eine flache Äquivalenzrelation, keine tiefe Kette — die Rekursion terminiert, sobald keine neuen Knoten mehr über direkte `same_work_as`-Kanten erreichbar sind, praktisch nach 1–2 Iterationen bei realistischen Cluster-Größen.) Die zurückgegebene Knotenmenge wird anschließend mit **allen** direkten Kanten jedes Mitglieds angereichert (jeder andere Kantentyp, nicht nur `same_work_as`) — das Ergebnis ist der vollständige „Stoff-Cluster plus eine Nachbarschaftsschicht" der UI (Modulkapitel: „Stoff-Cluster-Ansicht").

**Cap-Regel** (Modulkapitel Edge Case „Riesencluster"): Übersteigt die Äquivalenzklasse selbst 50 Knoten oder die angereicherte Kantenmenge 500, bricht die Query mit `LIMIT` ab und der Service liefert `{truncated: true, total_estimate: …}` statt eines vollständigen Graphen — das UI zeigt dann die „Cluster zu groß — Listenansicht"-Alternative (paginierte flache Liste ohne Graph-Visualisierung).

## `neighborhood(entity, depth)`: Algorithmus

```sql
-- depth = 1: direkte Kanten in beide Richtungen, sichtbarkeitsgefiltert
SELECT * FROM entity_relations
WHERE status = 'confirmed'
  AND ((subject_type = :t AND subject_id = :id) OR (object_type = :t AND object_id = :id))
  AND <visibility_filter>  -- schliesst Kanten zu library_grants-geschuetzten oder geloeschten Items aus

-- depth = 2: eine zusaetzliche Iteration ueber die bei depth=1 gefundenen Knoten,
-- UNION mit depth=1, Kanten-Cap 500, dedupliziert ueber (relation_type, subject_id, object_id)
```

Der Sichtbarkeitsfilter (Modulkapitel Security: „Kante erscheint gar nicht, nicht als verborgenes Ziel") wird **in der Query selbst** angewendet (Join gegen `library_grants` bzw. `deleted_at IS NULL`), nicht als Post-Filter in PHP — ein Post-Filter würde die Kanten laden und verwerfen, was bei Facetten-/Zähl-Antworten einen Timing- oder Zähl-Leak ermöglichen könnte (dieselbe Vorsicht wie bei der Suche: „Filter nach Fusion, vor Auslieferung").

## Erweiterungs-Prozedur für neue Kantentypen

Ein neuer Kantentyp (Modulkapitel-Beispielkandidaten: `soundtrack_of`, `documentary_about`) durchläuft: (1) Erweiterung des `CHECK`-Constraints (Migration), (2) `RelationTypeCatalog`-Eintrag mit Inversen-Label, erlaubten Subjekt/Objekt-Typ-Paaren, Symmetrie-Flag, Zyklenprüfung-Flag, (3) UI-Übersetzungs-String für den Beziehungs-Editor („richtungssichere" natürlichsprachige Vorschau, Modulkapitel), (4) Provider-Mapper-Erweiterung, falls eine automatische Quelle existiert (z. B. TMDB liefert `soundtrack`-Verweise nicht strukturiert — dieser Typ bliebe rein manuell/heuristisch), (5) Testfälle: Typkatalog-Invariante, Zyklen-Verhalten falls zutreffend, Nachbarschafts-Query-Erweiterung. Der Prozess ist bewusst release-gebunden (Modulkapitel: „nie per Betreiber-Freitext") — es gibt keinen API-Endpunkt, der einen Kantentyp zur Laufzeit registriert.

## Heuristik-Stufe: Score-Formel

`SuggestCrossMediaRelationsJob` (Modulkapitel Stufe 2) erzeugt `same_work_as`-Vorschläge über Provider-ID-Koinzidenz. Score-Formel je Kandidatenpaar (A, B verschiedener Medientypen):

```
score = 0.6 (Basis: gemeinsame externe Werk-ID unterschiedlichen Providers, z. B. Audible-ASIN von A referenziert dasselbe ISBN wie B)
      + 0.2 wenn |A.year - B.year| <= 2
      + 0.2 wenn Titel-Trigram-Similarity(A.title, B.title) >= 0.6
Confidence = min(0.95, score)
```

Ergebnis-Kanten entstehen immer mit `status='suggested'` (nie `confirmed` — Modulkapitel: „Vorschlags-Kanten erscheinen nur im Review, nie in der Navigation"), unabhängig von der Score-Höhe. Es gibt in diesem Modul **keine** Auto-Confirm-Schwelle wie beim Disc-Mapper oder Chapter-Set-Selector — jede heuristische Kante ist grundsätzlich review-pflichtig, weil Wissensbeziehungen (anders als Episodenmapping) keine harte technische Verifikation (Laufzeit-Alignment) haben, die eine Automatik rechtfertigen würde.

## Tests (Ergänzung zum Modulkapitel)

**Zyklen-Konstruktions-Suite**: A→B→C→A (3-Kette) muss beim letzten Insert scheitern; A→B, C→D (getrennte Ketten) dürfen unabhängig wachsen; Tiefenlimit-Grenzfall (Kette der Länge 20 vs. 21). **Cluster-Cap-Suite**: konstruierter 60-Knoten-Cluster ⇒ `truncated: true` mit korrektem `total_estimate`. **Score-Formel-Suite**: Tabellentest aller Score-Komponenten-Kombinationen gegen erwartete Confidence. **Inverse-Konsistenz**: `sequel_to`-Kante erzeugt bei Nachbarschafts-Abfrage von B exakt einen „Vorgänger"-Eintrag, keine Dopplung.
