# Query-Katalog: kanonische Zugriffe und Performance-Budgets

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Vertiefung zu [database/core-schema.md](core-schema.md) und [schema-reference.md](schema-reference.md). Katalog der **kanonischen Queries** des Systems: die UI- und Job-Hot-Paths mit normativem SQL-Muster, erwarteter Index-Nutzung und Budget. Jede Query hat eine stabile Kennung (Q-nn); die Plan-Suite (unten) verankert sie in CI und der Health-Check „Query-Pläne" ([health-monitoring](../modules/health-monitoring.md)) überwacht sie im Betrieb. Der Katalog verhindert das schleichende Elend großer Kataloge: Eine neue UI-Ansicht, deren Query hier nicht einzuordnen ist, hat ein Datenmodell- oder Index-Gespräch verdient, bevor sie merged.

## Budgets und Messkontext

Budgets gelten auf dem Referenz-Mengengerüst des Kernschemas (500k files, 300k items, 2 Mio. watch_states, 20 Mio. events) auf bescheidener Hardware (4 vCPU, NVMe): **UI-interaktiv < 50 ms**, **UI-Liste < 150 ms**, **Job-Batch < 1 s pro 1k Subjekte**, **Wartung/Nacht unbudgetiert, aber gedrosselt**. „Index-Nutzung" benennt den erwarteten Zugriffspfad; ein Seq-Scan auf L/XL-Tabellen in einer UI-Query ist per Definition ein Defekt (Konventions-Regel der Sort-Whitelists gilt API-seitig identisch).

## Bibliothek und Katalog

**Q-01 Bibliotheks-Grid mit Watch-Status** (die meistgerufene Query des Systems): Items eines Typs einer Bibliothek, paginiert, mit Container-Fortschritt des Benutzers.

```sql
SELECT mi.id, mi.title, mi.year, ucp.watched_count, ucp.consumable_total, ucp.next_up_item_id
FROM media_items mi
LEFT JOIN user_container_progress ucp
       ON ucp.media_item_id = mi.id AND ucp.user_id = :user
WHERE mi.library_id = :lib AND mi.media_type = 'show' AND mi.deleted_at IS NULL
ORDER BY mi.sort_title
LIMIT 51 OFFSET …  -- UI-seitig Cursor über sort_title+id
```

Index: `media_items_type_idx` + PK-Join auf den Cache. Budget UI-Liste. **Verbot:** die Live-Aggregation über `user_watch_states` an dieser Stelle — genau dafür existiert der Cache ([schema-reference](schema-reference.md), `user_container_progress`).

**Q-02 „Weiterschauen"-Leiste**: `ucp_continue_idx` (partieller Index, `in_progress_count > 0`), sortiert `last_activity_at DESC`, Limit 20; plus Einzelmedien in Arbeit aus `user_watch_states_user_idx` (`status='in_progress'`), gemischt im Server. Budget interaktiv.

**Q-03 Item-Detail**: PK-Zugriffe (Item, Satellit, Editionen mit Dateien, Credits mit Personen, aktive Assets, eigener Watch-State) — sechs gezielte Queries statt eines Monster-Joins; Eloquent-Eager-Loading mit exakter Relation-Liste (der Architektur-Test gegen N+1 prüft die Query-Zahl dieser Seite: ≤ 8).

**Q-04 Titelsuche (Fundament)**: `title % :q` über `media_items_title_trgm` (GIN), Ranking `similarity()`, Limit 50, kombiniert mit `people_name_trgm`. Budget interaktiv bei ≥ 3 Zeichen (darunter UI-seitig gar nicht suchen). Die semantische Suche hat ihren eigenen Pfad ([search](../modules/search.md), pgvector-HNSW).

## Watch-State-Pfade

**Q-10 Fortschritts-Upsert** (`RecordPlaybackProgress`): `INSERT … ON CONFLICT (user_id, media_item_id) DO UPDATE` + Event-Append + Audit-Append in einer Transaktion. Budget: < 10 ms (Player-Kadenz sitzt dahinter). Partition-Routing des Events über `occurred_at` ist deklarativ (kein Trigger-Overhead).

**Q-11 Konfliktauflösung** (Connector-Sync): letzter Event je (user, item) via `watch_state_events_subject_idx` (`ORDER BY occurred_at DESC LIMIT 1`) — Index-Only-tauglich; niemals `MAX()`-Subqueries über die Partitionsmenge.

**Q-12 Verlaufs-Ansicht** (Item-Detail, „zuletzt gesehen"): gleiche Index-Route, Limit 10, Partition-Pruning greift über den impliziten Zeitbezug **nicht** (kein Zeitprädikat) — akzeptiert: Der Index ist global über Partitionen (partitionierte Indizes), die Query bleibt im Budget; der Katalog dokumentiert das bewusst als Ausnahme vom „immer Zeitfenster"-Reflex.

## Review-Inbox

**Q-20 Inbox-Liste**: `review_tasks_open_idx` (partieller Index deckt `status IN ('open','in_review')`), Filter Typ/Priorität, Sortierung Priorität, Alter. Budget interaktiv. **Q-21 Subjekt-Anreicherung**: Morph-Auflösung je Alias in gebatchten PK-Queries (Registry-getrieben; nie N+1 über gemischte Typen).

## Disc-Engine

**Q-30 Disc-Liste mit Status** ([API-Referenz](../modules/disc-engine/api-reference.md)): `disc_images` der Bibliothek (Join über `files`), `mapping_summary` aus `disc_playlists_class_idx`-Aggregat (gruppiert, ein Subquery), `watch_summary` aus dem Progress-Cache — **nie** die `user_disc_status`-View in Listen (Modulkapitel Performance; die View ist Detail-/Rebuild-Werkzeug). Budget UI-Liste.

**Q-31 Positions-Übersetzung** (`TranslatePlaybackEventsJob`, heißester Job-Pfad): Events einer Session (`disc_events_session_idx`), Playlist-Auflösung PK, Segment-Range-Lookup über den GiST des Exclusion Constraints (`int8range(start_ms,end_ms) @> :pos`). Budget: < 10 ms je Batch (Modulkapitel). **Q-32 Unverarbeitet-Sweep**: partieller Index `disc_events_unprocessed`, chunked (1000/Lauf).

## Jobs, Artefakte, Housekeeping

**Q-40 Idempotenz-Gate** (jeder Builder): `artifacts_idempotency`-Unique-Probe (`generator, input_signature`, partiell aktiv) — Index-Only, < 1 ms. **Q-41 Checkpoint-Resume**: PK-Muster `(checkpoint_key, step_name)`. **Q-42 Waisen-GC**: Artefakte `orphaned` älter Karenz (`status`-Filter + `updated_at` — kleiner M-Bestand, Seq-Scan akzeptiert und dokumentiert). **Q-43 Housekeeping-Sweeps** (`.partial`-Waisen, abgelaufene Ack-Karten, stale Sessions): jeweils partielle Indizes auf den Sonderzuständen; Sweeps laufen chunked mit `LIMIT` + Wiederholung statt Riesen-Transaktionen (Lock-Hygiene).

**Q-44 Partition-Wartung** (`MaintainPartitionsJob`): Katalog-Query über `pg_partition_tree`; `DETACH CONCURRENTLY` außerhalb von Transaktionen (eigene Migration-Klasse-Semantik im Job); Budget Nacht.

## Enrichment und Provider

**Q-50 Refresh-Kandidatenwahl**: `provider_ids` join `media_items` mit Alters-/Prioritätsfilter — gedeckt durch `provider_ids_lookup` + `last_seen_at`-Bereich; chunked je Provider (Rate-Limiter ist ohnehin der Engpass, die Query darf gemütlich sein). **Q-51 Spiegel-Upsert**: `ON CONFLICT (provider, external_id, endpoint)`; Payload-TOAST — niemals `SELECT payload` in Listen (dasselbe Verbot wie `raw_analysis`, Disc-Modulkapitel). **Q-52 Kollisions-Signal** (Dedup): `provider_ids` gruppiert über `(provider, external_id)` mit `HAVING count(DISTINCT (entity_type, entity_id)) > 1` — nächtlich, Ergebnis in Dubletten-Verdachtsfälle.

## Suche und Empfehlung (Grenzen)

**Q-60 Hybrid-Suche**: Trigram-Kandidaten (Q-04) ∪ Vektor-Kandidaten (HNSW, `embedding <=> :q LIMIT 100`) mit Re-Ranking im Server ([search](../modules/search.md), ADR-0010). Der Katalog hält hier nur die Grenze fest: **kein** `ORDER BY`-Mix aus Distanz und relationalen Prädikaten in einer Query (Planner-Falle — HNSW zuerst, Filter danach, dokumentierter Recall-Trade-off im Such-Modul).

## Anti-Katalog (verbotene Muster)

Ausdrücklich als Defekt definierte Zugriffe — jeder hat einen Katalog-Ersatz: Live-Container-Aggregation in Listen (→ Q-01-Cache) · `user_disc_status` in Listen (→ Q-30) · `SELECT *` auf Tabellen mit TOAST-Spalten (`raw_analysis`, `payload`, `raw_source`) in Mehrzeiler-Queries (→ Spaltenlisten) · `OFFSET`-Pagination auf L/XL (→ Cursor; API-Konvention) · `count(*)` über XL-Tabellen für UI-Badges (→ gepflegte Zähler bzw. `reviewCounts`-Muster) · unparametrisierte Morph-Auflösung in Schleifen (→ Q-21-Batching) · `DELETE` mit Zeitprädikat auf partitionierten Tabellen (→ Q-44 `DROP PARTITION`) · Schreiben an `user_watch_states` außerhalb der Actions (Architektur-Test, Kernschema).

## Plan-Suite (CI-Verankerung)

Jede Q-Kennung hat einen Plan-Test: Das Test-Setup lädt das synthetische Referenz-Mengengerüst (skaliert auf CI-tauglich 1/10, Verteilungen realistisch: Zipf über Serien-Größen, 80/20-Watch-Aktivität), führt `EXPLAIN (FORMAT JSON)` aus und assertiert **Zugriffspfad-Eigenschaften** (verwendeter Index, kein Seq-Scan auf definierten Tabellen, Sort-Strategie) — nie absolute Kosten (flaky). Budget-Messungen laufen nightly auf dem vollen Mengengerüst mit Trend-Aufzeichnung; eine Verschlechterung > 50 % gegen den 30-Tage-Median öffnet ein Health-Incident. Neue Katalog-Queries brauchen den Plan-Test im selben PR (Prüfliste der [schema-reference](schema-reference.md)).

## Betriebs-Rezepte (Read-only-Diagnose)

Für Runbooks ([developer-handbook/runbooks.md](../developer-handbook/runbooks.md)) die gefahrlosen Diagnose-Queries: langsame Queries (`pg_stat_statements` Top-N nach `mean_exec_time`, Erwartungsabgleich gegen diesen Katalog), Index-Nutzung (`pg_stat_user_indexes.idx_scan = 0` über 30 Tage ⇒ Lösch-Kandidat — mit dem Katalog abgleichen, bevor gelöscht wird: Manche Indizes existieren für seltene, aber kritische Pfade wie Q-44), Tabellen-Bloat (`pgstattuple`-Stichprobe auf L/XL), Partitions-Zustand (`pg_partition_tree`-Lückenprüfung), Cache-Drift (`ucp_drift_count`-Query der schema-reference). Jedes Rezept ist Copy-Paste-fähig im Runbook hinterlegt; Schreiboperationen gehören nie in Diagnose-Rezepte.
