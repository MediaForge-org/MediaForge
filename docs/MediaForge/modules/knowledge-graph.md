# Knowledge Graph

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [database/core-schema.md](../database/core-schema.md) (Entitäten, Credits, Provider-IDs), [modules/audit.md](audit.md). Verwandt: [Suche](search.md) (Ähnlichkeit ≠ Beziehung — die Abgrenzung ist Kern dieses Kapitels).

**Vertiefung**: [Kantentyp- und Cluster-Algorithmus-Referenz](knowledge-graph/relation-reference.md) (vollständiger Typkatalog, Zyklenprüfung, Cluster-Algorithmus)

## Motivation

Credits und Hierarchien decken die offensichtlichen Beziehungen ab (wer spielt in was, welche Episode gehört wozu). Was fehlt, sind die **werkübergreifenden** Beziehungen, die Sammler tatsächlich navigieren: Buchvorlage → Verfilmung → Hörbuch desselben Stoffs; Remake → Original; Spin-off → Mutterserie; Werkreihe mit Lesereihenfolge ≠ Erscheinungsreihenfolge; „gleicher Stoff, andere Fassung" quer über Medientypen. Genau diese Querbezüge machen aus einem Katalog ein Wissensnetz: „Zeige alles zu diesem Stoff" — der Film, die Serie, das Hörbuch, die Vorlage — als navigierbare Struktur statt als Suchtreffer-Zufall. Die semantische Suche findet Ähnliches; der Graph kennt **Behauptetes mit Herkunft**: Das ist eine Adaption, das ist ein Remake — Fakten, nicht Vektor-Nähe.

## Problemstellung

**Typisierung vs. Wildwuchs.** Ein Graph mit freien Kantentypen verkommt zur Tag-Suppe. Die Kantentypen müssen ein kuratierter, endlicher Katalog mit definierter Semantik sein (Richtung! „X ist Adaption von Y" ≠ „Y ist Adaption von X") — erweiterbar per Release, nie per Betreiber-Freitext.

**Herkunft und Verlässlichkeit.** Beziehungen kommen aus Providern (TMDB kennt Collections und Remake-Verweise), aus Heuristiken (gleiche Provider-Werk-IDs über Medientypen), aus Benutzerhand und perspektivisch aus KI-Vorschlägen — die volle Herkunfts-Disziplin des Systems (Regel 5, Confidence, Review) gilt für Kanten exakt wie für alles andere.

**Relational, nicht Neo4j.** Die Masterdatei legt fest: relational in PostgreSQL, keine zweite Datenbank ([ADR-0011](../adr/0011-relational-knowledge-graph.md)). Die Abfragemuster müssen das rechtfertigen — MediaForge braucht Nachbarschafts-Navigation (1–2 Kanten) und kleine Zusammenhangskomponenten („der Stoff-Cluster"), keine Pfadalgorithmen über Millionen Kanten.

## Analyse bestehender Lösungen

**Wikidata/MusicBrainz-Beziehungsmodelle**: der Goldstandard typisierter, gerichteter, belegter Beziehungen — das Kantentyp-Design (Typkatalog mit Inversen-Definition) folgt diesem Vorbild in klein. **TMDB Collections / TVDB-Franchises**: liefern importierbare Gruppierungen (Reihen) und Verweise — primäre automatische Quelle. **Kodi/Jellyfin**: „Collections" als flache Mengen ohne Typsemantik — Negativ-Referenz (genau der Wildwuchs). **Stash-Tag-Hierarchien**: zeigen, dass Nutzer Beziehungsstrukturen pflegen, wenn das UI es leicht macht — Ansporn für den Beziehungs-Editor.

## Architekturentscheidung

**Kanten als eine Tabelle** (`entity_relations`) über Katalog-Entitäten (media_items, people), mit typisiertem Katalog:

| Typ (gerichtet: subject → object) | Semantik | Invers dargestellt als |
|---|---|---|
| `adaptation_of` | Subjekt adaptiert Objekt (Film → Buch) | „Adaptionen" |
| `remake_of` | Neuverfilmung | „Remakes" |
| `spin_off_of` | Ableger | „Spin-offs" |
| `sequel_to` / `prequel_to` | erzählerische Folge | Gegenrichtung |
| `same_work_as` | dasselbe Werk in anderer Form (Hörbuch ↔ E-Book) | symmetrisch |
| `part_of_series` | Werk → Reihe (Reihen sind eigene Knoten, s. u.) | „enthält" |
| `based_on_person` | Werk → reale Person (people) | „Werke über" |

Symmetrische Typen (`same_work_as`) werden normalisiert gespeichert (kleinere ULID zuerst); Inverse werden **nie** doppelt gespeichert, sondern bei Abfrage gedreht — eine Kante, ein Fakt.

**Reihen als Knoten**: Werkreihen (`work_series`) sind eigene Entitäten mit geordneten Mitgliedschaften und Doppel-Ordnung (`publication_order`, `narrative_order` — die Lesereihenfolge-Frage ist bei Buch-/Hörbuchreihen der häufigste Pflegefall). Die `audiobook_details.series_name`-Felder des Kernschemas werden per Backfill in echte Reihen-Knoten überführt (Expand/Contract).

**Befüllung dreistufig**: (1) Provider-Import (TMDB-Collections → Reihen; Provider-Querverweise → Kanten mit `source='provider'`, Confidence 0.9); (2) Heuristik (zwei Katalog-Items mit derselben externen Werk-ID verschiedener Medientypen ⇒ `same_work_as`-Vorschlag — die Provider-ID-Tabelle als Kantengenerator); (3) manuell (Beziehungs-Editor). KI-Vorschläge (Stufe 4) sind vorgesehen, aber erst nach der AI-Engine-LLM-Entscheidung (dort offener Punkt) — Kanten aus Embedding-Nähe zu raten wäre genau die Vermischung von Ähnlichkeit und Behauptung, die das Modul abgrenzt. Vorschlags-Kanten (`status='suggested'`) erscheinen nur im Review, nie in der Navigation.

## Alternativen

**Neo4j/Graph-DB**: verworfen ([ADR-0011](../adr/0011-relational-knowledge-graph.md)) — Betriebskosten einer Zweitdatenbank gegen Abfragen, die rekursive CTEs über kleine Komponenten locker tragen; Revisionskriterien dokumentiert. **Kanten als Tags**: keine Richtung, keine Herkunft pro Kante — verworfen. **Alles in `media_items.parent_id`** (Reihen als Container-Items): vermengt Besitzhierarchie mit Wissensbeziehungen; Watch-State-/Presence-Semantik würde auf Reihen durchschlagen — verworfen. **Freie Kantentypen** (Betreiber definiert Typen): Wildwuchs, verworfen; das Ventil ist der Typkatalog-Erweiterungsprozess per Release.

## Datenmodell und SQL-Schema

```sql
CREATE TABLE work_series (
    id           CHAR(26) PRIMARY KEY,
    name         TEXT        NOT NULL,
    description  TEXT,
    source       TEXT        NOT NULL DEFAULT 'manual'
        CHECK (source IN ('provider','heuristic','manual','import')),
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE work_series_members (
    id                CHAR(26) PRIMARY KEY,
    series_id         CHAR(26) NOT NULL REFERENCES work_series(id) ON DELETE CASCADE,
    media_item_id     CHAR(26) NOT NULL REFERENCES media_items(id) ON DELETE CASCADE,
    publication_order NUMERIC(6,2),
    narrative_order   NUMERIC(6,2),
    UNIQUE (series_id, media_item_id)
);

CREATE TABLE entity_relations (
    id            CHAR(26) PRIMARY KEY,
    relation_type TEXT        NOT NULL
        CHECK (relation_type IN ('adaptation_of','remake_of','spin_off_of',
                                 'sequel_to','prequel_to','same_work_as',
                                 'based_on_person')),
    subject_type  TEXT        NOT NULL,           -- 'media_item'
    subject_id    CHAR(26)    NOT NULL,
    object_type   TEXT        NOT NULL,           -- 'media_item' | 'person'
    object_id     CHAR(26)    NOT NULL,
    status        TEXT        NOT NULL DEFAULT 'confirmed'
        CHECK (status IN ('suggested','confirmed','rejected')),
    source        TEXT        NOT NULL
        CHECK (source IN ('provider','heuristic','manual','import','ai')),
    confidence    NUMERIC(4,3) NOT NULL DEFAULT 1.000,
    evidence      JSONB       NOT NULL DEFAULT '{}',
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (relation_type, subject_type, subject_id, object_type, object_id),
    CHECK (NOT (subject_type = object_type AND subject_id = object_id))  -- keine Selbstkanten
);

CREATE INDEX entity_relations_subject ON entity_relations (subject_type, subject_id, status);
CREATE INDEX entity_relations_object  ON entity_relations (object_type, object_id, status);
```

Zyklenfreiheit wird nur für die hierarchischen Typen (`sequel_to`/`prequel_to` innerhalb eines Clusters) action-seitig geprüft (rekursive CTE mit Tiefenlimit 20 beim Einfügen); `same_work_as`-Cluster sind naturgemäß zyklisch (Äquivalenzklassen) und werden als solche behandelt.

## Laravel-Klassen

| Klasse | Typ | Vertrag |
|---|---|---|
| `EntityRelation`, `WorkSeries` | Model | wie Schema; Kanten guarded außerhalb der Actions |
| `RelationTypeCatalog` | Service | Typdefinitionen (Semantik, Inversen-Label, erlaubte Subjekt/Objekt-Typen); UI-Schema-Export |
| `GraphNeighborhoodService` | Service | `neighborhood(entity, depth≤2): Graph` — die Navigations-Query (CTE); `workCluster(entity): Cluster` — same_work_as-Äquivalenzklasse + direkte Kanten |
| `CreateRelation`, `ConfirmRelation`, `RejectRelation` | Action | Typprüfung, Zyklus-Check, Normalisierung symmetrischer Typen; Audit |
| `ImportProviderRelationsJob` | Job (`connector`) | TMDB-Collections/Verweise beim Enrichment; Upsert über den Unique-Key |
| `SuggestCrossMediaRelationsJob` | Job (`default`) | Heuristik-Stufe über Provider-ID-Bestand (Batch, wöchentlich) |

## API und UI

`GET /api/v1/media/{ulid}/graph?depth=1|2` (member) liefert die Nachbarschaft als Knoten/Kanten-Struktur; Beziehungs-CRUD analog Actions (`manager`). UI: **Beziehungs-Panel** auf jeder Detailseite — gruppiert nach Inversen-Labels („Adaptionen", „Teil der Reihe … (Band 3)", „Gleicher Stoff als: Hörbuch, E-Book") mit Confidence-/Herkunfts-Badges; **Stoff-Cluster-Ansicht** (der `workCluster` als kleine Graph-Visualisierung, Klick-Navigation); **Beziehungs-Editor** (Typ-Auswahl aus dem Katalog mit Semantik-Hilfetext, Ziel-Suche über die normale Suche, Richtungs-Vorschau in natürlicher Sprache: „*Der Film* ist eine Adaption von *dem Buch*" — die Richtungs-Verwechslung ist der häufigste Pflegefehler, das UI verhindert ihn sprachlich); Reihen-Editor mit Doppel-Ordnungs-Spalten.

## Edge Cases

* **Merge von Dubletten** ([Fingerprinting](dedup-fingerprinting.md)): Kanten des Verlierer-Items werden auf den Gewinner umgehängt (Teil der Merge-Action-Spezifikation dort; Unique-Kollisionen werden zu einer Kante konsolidiert).
* **Kante auf gelöschtes Item**: CASCADE räumt; der Stoff-Cluster heilt sich (Äquivalenzklasse ohne das Item).
* **Widersprüchliche Provider-Angaben** (TMDB sagt Remake, TVDB sagt Spin-off): beide Kanten als `suggested` mit Herkunft; Review entscheidet — nie stille Präzedenz zwischen Providern.
* **Reihen-Ordnungs-Konflikte** (Publikation 1,2,3 vs. narrative 2,1,3): genau dafür die Doppel-Ordnung; UIs zeigen die vom Benutzer gewählte Default-Ordnung (Setting pro Reihe).
* **Riesencluster** (Franchise mit 200 Werken): Nachbarschafts-Query mit Tiefenlimit und Kanten-Cap (500) plus „Cluster zu groß — Listenansicht" statt Graph-Gemälde.

## Performance

Alle Navigationsabfragen sind index-gestützte Nachbarschafts-Lookups (depth 1) bzw. zweistufige CTEs (depth 2) über Kantenzahlen im Zehntausender-Bereich — Millisekunden. Cluster-Berechnung (`same_work_as`-Äquivalenz) per rekursiver CTE mit Memoisierung im Request-Cache. Der Heuristik-Batch nutzt den Provider-ID-Lookup-Index. Kein Caching-Layer nötig, solange die Kantenzahl < 1M bleibt (Monitoring-Metrik).

## Security

Kanten-Pflege `manager`; Navigation `member` im Bibliotheks-Sichtbarkeitsrahmen (Kanten zu unsichtbaren Items werden gefiltert — kein Existenz-Leak über den Graphen: die Kante erscheint gar nicht, nicht als „verborgenes Ziel"). Evidence ohne Secrets (Standard-Denyliste).

## Tests

Typkatalog-Invarianten (Inversen-Konsistenz, erlaubte Typ-Paare). Normalisierung symmetrischer Kanten (beide Einfüge-Richtungen ⇒ eine Zeile). Zyklus-Check (sequel-Kette mit Rückkante muss scheitern). Nachbarschafts-/Cluster-Queries gegen konstruierte Graphen (Golden-Ergebnisse inkl. Sichtbarkeitsfilter). Merge-Umhängung. Provider-Import-Idempotenz.

## ADR-Verweise

[ADR-0011](../adr/0011-relational-knowledge-graph.md) (relational statt Graph-DB, mit Revisionskriterien). Setzt um: Regel 5/7-Disziplin auf Kanten (Herkunft, Provider-Konflikte als Review).

## Offene Punkte

* **KI-Kantenvorschläge**: wartet auf die LLM-Governance-Entscheidung der [AI Engine](ai-engine.md).
* **Typkatalog-Erweiterungen** (z. B. `soundtrack_of`, `documentary_about`): Prozess ist Release-gebunden; Kandidatenliste wird mit Betriebserfahrung gepflegt.
* **Graph-Export** (RDF/JSON-LD für Externe): kein benannter Konsument; vertagt.
