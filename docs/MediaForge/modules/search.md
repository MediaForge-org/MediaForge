# Semantische Suche

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [database/core-schema.md](../database/core-schema.md) (Trigram-Basis), [modules/ai-engine.md](ai-engine.md) (Embeddings), [modules/audit.md](audit.md). Verwandt: [Knowledge Graph](knowledge-graph.md) (Beziehungs-Navigation ergänzt Suche).

**Vertiefung**: [Embedding- und Fusions-Spezifikation](search/embedding-spec.md) (exakter Einbettungstext, Modell-Registry, RRF-Formel, Coverage)

## Motivation

Titelsuche kann jedes Referenzsystem; MediaForge braucht mehr, weil sein Katalog heterogener ist: „das Hörbuch, wo der Ermittler auf der Insel verschwindet", „Dokus über Tiefsee", „Filme wie X" — Anfragen über **Bedeutung**, nicht Zeichenketten. Gleichzeitig darf die banale Suche („Staffel 3 Disc 2") nicht schlechter werden als ein Trigram-Match. Das Modul liefert deshalb eine **hybride Suche**: lexikalisch (Trigram + Postgres-FTS) für Präzision bei bekannten Begriffen, semantisch (pgvector-Embeddings) für Bedeutungsnähe, fusioniert zu einer Rangliste. Immich beweist die Machbarkeit dieses Zuschnitts im Selfhosting (CLIP-Suche über Fotos, pgvector, lokale Modelle — Masterdatei-Referenzanalyse).

## Problemstellung

**Zwei Suchwahrheiten fusionieren.** Lexikalische Scores (ts_rank, Trigram-Similarity) und Vektor-Distanzen leben auf inkommensurablen Skalen; naive Score-Addition produziert Zufall. Es braucht ein definiertes Fusionsverfahren.

**Embedding-Lebenszyklus.** Embeddings hängen an Modell+Version ([AI Engine](ai-engine.md)); ein Modellwechsel invalidiert Millionen Vektoren. Ohne Versions-Disziplin vergleicht die Suche Äpfel-Vektoren mit Birnen-Vektoren — stillschweigend und falsch.

**Was wird eingebettet?** Ein Medium ist kein Text. Der Einbettungstext muss definiert, versioniert und herkunftsbewusst sein (KI-generierte Beschreibungen dürfen nicht unmarkiert in den Suchindex sickern — Regel-5-Ausläufer).

**Optionalität.** Ohne ai-worker gibt es keine Embeddings; die Suche muss lexikalisch voll funktionieren und semantische Anteile transparent zuschalten.

## Analyse bestehender Lösungen

**Immich**: pgvector + CLIP, Hybrid mit Metadaten-Filtern — Architektur-Blaupause; Unterschied: MediaForge bettet primär Text ein (Titel/Summary/Tags/Credits), Bild-Embeddings sind Immich-Sache (Fotos bleiben dort). **Meilisearch/Typesense**: exzellente Selfhosting-Suchserver mit Hybrid-Fähigkeiten — verworfen als Zusatzinfrastruktur (Backup/Update/Konsistenz-Zoo; die Postgres-Entscheidung der Masterdatei gilt), festgehalten als Fallback-Option, falls Postgres-FTS-Grenzen real werden ([ADR-0010](../adr/0010-postgres-hybrid-search.md)). **Jellyfin-Suche**: reine Substring/Prefix-Suche — Negativ-Referenz (genau die Lücke). **Reciprocal Rank Fusion (RRF)**: Standard-Fusionsverfahren hybrider Systeme; übernommen, weil es ohne Score-Normalisierung auskommt (nur Ränge zählen) — die robuste Antwort auf das Inkommensurabilitäts-Problem.

## Architekturentscheidung

**Ein Suchdienst, drei Stufen:**

1. **Lexikalisch**: `tsvector`-Spalte (generiert) über Titel/Originaltitel/Summary mit `german`+`simple`-Doppelkonfiguration (deutsche Stemming-Treffer plus exakte Namen), Trigram für Tippfehler-Toleranz und Teilwort-Treffer; kombiniert per `websearch_to_tsquery` + Similarity-Fallback.
2. **Semantisch**: `embeddings`-Tabelle (pgvector, HNSW-Index, Cosine); Anfrage-Embedding via AI Engine (`ai-light`), k-NN über aktive Modellversion.
3. **Fusion + Filter**: RRF über beide Ranglisten (k=60), danach harte Filter (Bibliothek, Medientyp, Rollen-Sichtbarkeit) — Filter nach Fusion, damit beide Stufen mit vollen Kandidatenmengen arbeiten; Facetten (Typ, Jahr, Tags) aus dem gefilterten Ergebnis.

**Einbettungstext** ist eine deterministische, versionierte Funktion (`embedding-text/v1`): Titel + Originaltitel + Summary + Genre-Tags + Top-Credits + Serienkontext, in fester Reihenfolge, mit Herkunftsfilter — Felder mit `source='ai'`-Herkunft werden **nicht** eingebettet (der Index repräsentiert belegte Metadaten; ein KI-Summary, das via Suche gefunden wird, würde KI-Halluzination in Suchrelevanz verwandeln). Text-Versionswechsel oder Modellwechsel ⇒ Re-Embedding-Backfill ([migrations](../database/migrations.md)-Mechanik).

## Alternativen

Externe Suchserver (verworfen, oben); **nur semantisch** (schlecht für exakte Titel/IDs — die häufigste Anfrage bleibt banal); **Score-Normalisierung statt RRF** (fragil gegenüber Verteilungsdrift); **Embeddings pro Feld** (Titel-Vektor, Summary-Vektor getrennt): Mehrkosten ohne belegten Gewinn, vertagt; **IVFFlat statt HNSW**: schlechtere Recall/Latenz-Balance bei MediaForge-Größen, HNSW-Bauzeit ist bei 300k Vektoren unkritisch.

## Datenmodell und SQL-Schema

```sql
CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE embeddings (
    id             CHAR(26) PRIMARY KEY,
    subject_type   TEXT        NOT NULL,          -- 'media_item' | 'person'
    subject_id     CHAR(26)    NOT NULL,
    model_name     TEXT        NOT NULL,
    model_version  TEXT        NOT NULL,
    text_version   TEXT        NOT NULL,          -- 'embedding-text/v1'
    embedding      vector(768) NOT NULL,          -- Dimension modellabhängig; pro Modell eine Tabelle-Partition? Nein: s. u.
    embedded_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (subject_type, subject_id, model_name, model_version, text_version)
);

CREATE INDEX embeddings_hnsw ON embeddings
    USING hnsw (embedding vector_cosine_ops)
    WITH (m = 16, ef_construction = 64);

CREATE INDEX embeddings_subject_idx ON embeddings (subject_type, subject_id);

-- Lexikalische Basis (Migration am Kernschema):
ALTER TABLE media_items ADD COLUMN search_tsv tsvector
    GENERATED ALWAYS AS (
        setweight(to_tsvector('german', coalesce(title,'')), 'A') ||
        setweight(to_tsvector('simple', coalesce(original_title,'')), 'A') ||
        setweight(to_tsvector('german', coalesce(summary,'')), 'C')
    ) STORED;
CREATE INDEX media_items_tsv_idx ON media_items USING gin (search_tsv);
```

Anmerkung zur Dimension: pgvector verlangt feste Spaltendimension; ein Modellwechsel mit anderer Dimension bedeutet neue Spalte/Tabelle im Expand/Contract-Verfahren — dokumentierte Konsequenz, kein Blocker (Modellwechsel sind seltene, geplante Ereignisse mit Backfill ohnehin). Nur **eine** aktive Modellversion bedient die Suche (Setting `search.active_embedding_model`); Alt-Vektoren bleiben bis zum Contract als Vergleichsbasis.

## Laravel-Klassen

| Klasse | Typ | Vertrag |
|---|---|---|
| `SearchService` | Service | `search(SearchQuery): SearchResult` — orchestriert Stufen, RRF, Filter, Facetten; deklariert im Ergebnis, welche Stufen aktiv waren (`semantic: unavailable` bei fehlendem Worker — Transparenz statt stiller Degradation) |
| `EmbeddingTextBuilder` | Service (pure) | deterministischer, versionierter Einbettungstext mit Herkunftsfilter |
| `EmbedSubjectJob` | Job (`ai-light`, gebatcht) | auf `MediaItemCreated/Updated` (debounced), via AI-Engine-Dispatcher |
| `ReembedAllJob` | Backfill | Modell-/Textversionswechsel ([migrations](../database/migrations.md)) |
| `SearchController` | HTTP | Inertia-Suche + `GET /api/v1/search` |

## API und UI

`GET /api/v1/search?q=&types=&library=&semantic=auto|off` (member; `semantic=off` erzwingt lexikalisch — für Automatisierung, die Determinismus braucht). UI: globale Suchleiste (Cmd+K-Palette) mit Sofort-Ergebnissen (lexikalisch, < 50 ms) und nachgeladener semantischer Anreicherung (Ergebnisliste erweitert sich sichtbar, semantische Treffer markiert — der Benutzer sieht, was Bedeutungssuche beiträgt); Volltreffer-Navigation (exakte Titel springen direkt); „Ähnliche Titel"-Panel auf Detailseiten (k-NN über das Item-Embedding — dieselbe Infrastruktur, zweiter Konsument).

## Edge Cases

* **Anfrage in anderer Sprache als der Katalog** (englische Anfrage, deutscher Katalog): mehrsprachige Embedding-Modelle fangen das teilweise; die Modellwahl-Empfehlung (Registry) dokumentiert Mehrsprachigkeit als Kriterium. Lexikalisch hilft die `simple`-Konfiguration bei Originaltiteln.
* **Sehr kurze Anfragen** („X", „93"): semantische Stufe wird unter 3 Zeichen übersprungen (Vektor-Rauschen), Trigram trägt.
* **Embedding-Rückstand** (frisch importierte 50k Items, Backfill läuft): Ergebnisse deklarieren Coverage (`semantic_coverage: 0.61`); keine stille Halbwahrheit.
* **Personen-Suche**: `people` erhalten eigene Embeddings (`subject_type='person'`) erst bei Bedarf-Nachweis; lexikalisch (Trigram auf `people.name`) ist Version-1-Stand — festgehalten, damit die Erwartung stimmt.

## Performance

Lexikalisch: GIN-Indizes, Ziel < 50 ms bei 300k Items. Semantisch: HNSW-k-NN (~1–5 ms) plus Anfrage-Embedding — Letzteres dominiert (10–50 ms am Worker, Cache für wiederholte Anfragen 15 min). RRF über Top-200 beider Listen in PHP: vernachlässigbar. Embedding-Schreiblast: gebatcht (64/Batch, AI-Engine-Dispatcher), HNSW-Insert-Kosten bei 300k unkritisch; `maintenance_work_mem`-Hinweis für den Indexbau in den Deployment-Docs.

## Security

Suchergebnisse respektieren Rollen-/Bibliothekssichtbarkeit (Filter nach Fusion, vor Auslieferung — kein Leak über Facetten-Zähler). Anfragen werden nicht protokolliert außer aggregiert (Zähler fürs Dashboard); keine Anfrage-Inhalte im Log (Suchanfragen sind Verhaltensdaten). Anfrage-Embeddings verlassen den Host nicht (lokaler Worker — der Punkt der Selfhosting-Entscheidung).

## Tests

Fusions-Tests (konstruierte Ranglisten ⇒ erwartete RRF-Ordnung, Grenzfälle leerer Stufen). Herkunftsfilter-Test (KI-Summary erscheint nie im Einbettungstext — Regel-5-Regression). Determinismus des `EmbeddingTextBuilder` (Golden Files). Coverage-Deklaration. Such-Qualität als Fixture-Suite (kleiner Katalog, definierte Anfragen ⇒ erwartete Top-3; semantische Fälle mit Fake-Embeddings deterministisch konstruiert).

## ADR-Verweise

[ADR-0010](../adr/0010-postgres-hybrid-search.md) (Hybrid in Postgres statt Suchserver). Setzt um: Regel 5 (Herkunftsfilter), AI-Engine-Verträge.

## Offene Punkte

* **Audio-Embeddings** (Klang-Ähnlichkeit statt Text): Infrastruktur vorhanden (`embedding_audio`-Task der AI Engine), Anwendungsfall (Musik-Ähnlichkeit) unpriorisiert.
* **Personalisierte Rankings** (Watch-History-Einfluss): bewusst nicht in Version 1 — Such-Determinismus ist ein Feature; festgehalten für spätere Diskussion.
* **Meilisearch-Fallback**: Kriterien, wann die Postgres-Entscheidung revidiert würde (Latenz-Budgets gerissen, Facetten-Komplexität), sind in ADR-0010 dokumentiert.
