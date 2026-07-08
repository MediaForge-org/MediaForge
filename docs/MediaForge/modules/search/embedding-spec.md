# Embedding- und Fusions-Spezifikation

Vertiefung zu [modules/search.md](../search.md). Normativ für: das `embedding-text/v1`-Format (exakte Text-Konstruktion), die Modell-Registry-Verträge, die RRF-Fusionsformel mit Parametern und die Coverage-Berechnung. Das Modulkapitel definiert Architektur und Abgrenzung (Hybrid, Herkunftsfilter); dieses Dokument ist die Referenz für `EmbeddingTextBuilder`- und `SearchService`-Golden-Tests.

## `embedding-text/v1`: exakte Konstruktion

```
EmbeddingText(item) = join("\n", [
    Field("Titel", item.title),
    Field("Originaltitel", item.original_title, skip_if_equal=item.title),
    Field("Jahr", item.year),
    Field("Zusammenfassung", item.summary, source_filter=EXCLUDE_AI),
    Field("Genres", join(", ", item.tags[namespace='genre'].name)),
    Field("Mitwirkende", join(", ", top_credits(item, n=5, order_by=sort_index))),
    Field("Serienkontext", parent_chain_titles(item))  -- nur bei episode/season/track
])
```

Normative Regeln je Zeile:

1. **`Titel`**: immer vorhanden (Pflichtfeld des Katalogs), nie leer.
2. **`Originaltitel`**: übersprungen, wenn identisch zu `Titel` (deutsche Titel bei deutschsprachigen Werken sind oft Original = Übersetzung — Redundanz im Einbettungstext verwässert das Signal).
3. **`Jahr`**: als Zahl-Token, nicht als Datum (Jahr trägt semantisches Gewicht — „Film von 1987" — Tag/Monat nicht).
4. **`Zusammenfassung`**: **nur** wenn `field_provenance.summary.source ≠ 'ai'` ([Enrichment](../enrichment.md)-Provenienz-Feld). Ist die einzige verfügbare Zusammenfassung KI-generiert, wird die Zeile komplett weggelassen (nicht durch Leerstring ersetzt) — das ist die wörtliche Umsetzung von Modulkapitel-Regel „KI-Summary erscheint nie im Einbettungstext".
5. **`Genres`**: alle Tags im `genre`-Namespace, kommasepariert, in der Reihenfolge ihrer `taggables`-Erzeugung (deterministisch über `created_at`, nicht alphabetisch — vermeidet Instabilität bei gleichzeitig erzeugten Tags durch sekundäre Sortierung nach `tag_id`).
6. **`Mitwirkende`**: Top-5-Credits nach `sort_index` (Regie/Hauptrollen typischerweise zuerst je Provider-Konvention), Format `"{name} ({role})"`.
7. **`Serienkontext`**: nur für `episode`/`season`/`track` — die Elternkette bis zur Wurzel (`show`/`album`), Titel durch „ / " getrennt, z. B. „Die Kronprinzessin / Staffel 2". Ohne diese Zeile würde eine Episoden-Suche nur den Episodentitel sehen, der oft generisch ist („Folge 7").

Feldtrenner ist `\n` (nicht Komma/Leerzeichen) — das gewählte Embedding-Modell tokenisiert zeilenweise-strukturierten Text nachweislich besser als Fließtext (Modell-Registry-Kalibrierung, [AI Engine](../ai-engine.md)). Der resultierende Text wird **nicht** weiter normalisiert (kein Lowercasing, kein Stemming) — das Embedding-Modell übernimmt Normalisierung intern; ein zusätzlicher Normalisierungsschritt würde nur die Modellauswahl-Freiheit einschränken.

### Determinismus-Vertrag

`EmbeddingTextBuilder::build(item): string` ist eine pure Funktion über den zum Aufrufzeitpunkt geladenen Katalogstand (inkl. Tags/Credits/Provenienz) — bei identischem Stand liefert sie byte-identischen Text (Golden-Test-Grundlage). Änderungen an der Konstruktionslogik erhöhen `text_version` (`embedding-text/v2`, …); die `embeddings`-Tabelle führt `text_version` als Teil des Unique-Schlüssels (Modulkapitel-Schema), sodass alte und neue Texte koexistieren können, bis der Backfill (`ReembedAllJob`) abgeschlossen ist.

## Modell-Registry-Vertrag

```php
interface EmbeddingModelInterface
{
    public function name(): string;              // 'multilingual-e5-base'
    public function version(): string;            // '1.0'
    public function dimension(): int;             // 768
    public function maxInputTokens(): int;
    public function embed(string $text): array;   // float[dimension], L2-normalisiert
}
```

Anforderungen an ein registrierungsfähiges Modell (Aufnahme-Checkliste, analog Enrichment-Provider-Checkliste): (1) L2-normalisierte Ausgabe (Cosine-Distanz im HNSW-Index setzt das voraus — ein nicht normalisiertes Modell liefert falsche Nachbarschaften ohne Fehlermeldung, deshalb Pflichtprüfung im Registrierungs-Boot-Test: Stichproben-Embeddings müssen Norm ≈ 1.0 haben), (2) dokumentierte Mehrsprachigkeits-Fähigkeit (Modulkapitel Edge Case „Anfrage in anderer Sprache"), (3) Kalibrierungs-Suite bestanden (Fixture-Anfragen mit erwarteten Top-3-Nachbarn, [Test-Abschnitt](#tests) unten), (4) `maxInputTokens()` ≥ die 95.-Perzentil-Länge des `embedding-text/v1`-Outputs über den Referenz-Katalog (sonst stille Kürzung bei langen Besetzungslisten).

Aktives Modell: `search.active_embedding_model` (Setting, Format `"{name}:{version}"`). Anfrage-Embeddings verwenden ausschließlich das aktive Modell; Such-Ergebnisse aus Vektoren anderer Modellversionen werden **nie** gemischt (k-NN-Query filtert `WHERE model_name = ? AND model_version = ?` — ein Modellwechsel ohne abgeschlossenen Backfill zeigt reduzierte `semantic_coverage`, nie falsch vermischte Distanzen).

## RRF-Fusionsformel (vollständig)

```
RRF_score(doc) = Σ_over_lists  1 / (k + rank_in_list(doc))
k = 60  (Standard-RRF-Konstante, Setting search.rrf_k)
```

Zwei Listen: `lexical_ranks` (aus `ts_rank_cd(search_tsv, query) DESC` kombiniert mit Trigram-`similarity()` als Tie-Breaker, Top-200) und `semantic_ranks` (aus HNSW-k-NN, `embedding <=> query_embedding` aufsteigend, Top-200). Ein Dokument, das nur in einer Liste erscheint, erhält für die fehlende Liste keinen Term (nicht Rang 201 — das würde lange Listen künstlich abwerten). Ergebnis-Sortierung nach `RRF_score DESC`; bei exaktem Gleichstand (selten, nur bei disjunkten Kandidatenmengen mit identischem Einzel-Score) sekundär nach `updated_at DESC` (neueste Katalogänderung zuerst — deterministischer Tie-Break für reproduzierbare Ergebnisreihenfolgen in Tests).

**Warum k=60**: Standardwert aus der RRF-Literatur (Cormack et al.), der einen guten Kompromiss zwischen „Rang-1-Dominanz" (kleines k) und „Gleichgewichtung aller Ränge" (großes k) bietet; die Kalibrierungs-Suite (unten) verifiziert, dass k=60 auf dem MediaForge-Referenzkatalog keine Regression gegenüber k∈{20,40,100} zeigt — bei abweichendem Befund wird der Wert hier und im Setting-Default gemeinsam angepasst.

## Kurze-Anfragen-Schwelle

`semantic=auto` (API-Parameter, Modulkapitel): Die semantische Stufe wird übersprungen bei `len(trim(q)) < 3` (Setting `search.min_semantic_query_len`) — nicht aus Performance-, sondern aus Qualitätsgründen (Modulkapitel: „Vektor-Rauschen"). Trigram/FTS laufen bei jeder Länge ≥ 1.

## Coverage-Berechnung

```
semantic_coverage = count(embeddings WHERE model=active AND subject IN result_candidate_set)
                     / count(result_candidate_set)
```

Berechnet über die **Kandidatenmenge vor Fusion** (nicht über die finale Ergebnisliste — sonst würde Coverage künstlich hoch erscheinen, weil nur eingebettete Treffer überhaupt in der semantischen Liste auftauchen können). Ein Wert < 1.0 erscheint im API-Response-Meta (`meta.semantic_coverage`) und im UI als dezenter Hinweis „semantische Anreicherung unvollständig (61 %)" — nie stillschweigend (Modulkapitel: „keine stille Halbwahrheit").

## Personen-Embeddings (Stand Version 1)

`subject_type='person'` ist im Schema vorgesehen, aber nicht befüllt (Modulkapitel: „erst bei Bedarfsnachweis"). Der `EmbeddingTextBuilder` hat keinen Personen-Zweig; `EmbedSubjectJob` reagiert nicht auf personenbezogene Events. Diese Festlegung ist hier normativ wiederholt, damit ein Leser, der `subject_type` im Schema sieht, nicht auf ein bestehendes Feature schließt.

## Tests

**Golden-Text-Suite**: konstruierte Items (mit/ohne Originaltitel-Duplikat, mit KI-Summary, mit Serienkontext, mit > 5 Credits) ⇒ exakter erwarteter Text, Byte-Vergleich. **Herkunftsfilter-Regression**: Item mit ausschließlich `source='ai'`-Summary ⇒ Zeile fehlt vollständig (nicht leer). **Modell-Registrierungs-Boot-Test**: L2-Norm-Stichprobe, Dimension-Konsistenz. **RRF-Kalibrierung**: konstruierte Ranglisten-Paare (disjunkt, überlappend, ein-Listen-only) ⇒ erwartete `RRF_score`-Reihenfolge; k-Sensitivitätsanalyse gegen die Fixture-Bibliothek. **Coverage-Test**: Teilbestand mit bekanntem Embedding-Verhältnis ⇒ exakter `semantic_coverage`-Wert. **Mehrsprachigkeits-Suite**: englische Anfrage gegen deutschen Fixture-Katalog ⇒ erwartete Top-3 (nur lauffähig mit einem als mehrsprachig registrierten Modell; die Suite wird übersprungen und im Report vermerkt, wenn kein solches Modell aktiv ist).
