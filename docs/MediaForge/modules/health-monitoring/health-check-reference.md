# Health-Check- und Metrik-Referenz

Vertiefung zu [modules/health-monitoring.md](../health-monitoring.md). Vollständiger, normativer Katalog aller registrierten Checks mit exakten Schwellen, Entprellungs-Parametern und Abhilfe-Verweisen — der Vertrag, den `HealthCheckRegistry` beim Boot validiert (jeder registrierte Check muss eine Zeile hier haben, Contract-Test wie bei den API-Katalogen). Ergänzend der vollständige Metrik-Schlüssel-Katalog für `/metrics` und die Dashboard-Sparklines.

## Check-Vertrag (Wiederholung mit Feldern)

```php
interface HealthCheckInterface
{
    public function key(): string;                 // 'queue.ai_worker_present'
    public function category(): string;             // 'ai'
    public function interval(): int;                // Sekunden zwischen Läufen
    public function debounce(): Debounce;           // {toFail: int, toOk: int} Default {3,1}
    public function run(): HealthResult;            // ok|warn|fail + detail + remedyRef
}
```

`remedyRef` verweist auf einen Anker in [developer-handbook/runbooks.md](../../developer-handbook/runbooks.md) — jeder Check hat einen entsprechenden Runbook-Abschnitt (Contract-Test: Registrierung ohne auflösbaren `remedyRef` bricht den Boot).

## Vollständiger Check-Katalog

### Kategorie: Infrastruktur

| Key | Bedingung | Schwelle | Debounce | Remedy-Anker |
|---|---|---|---|---|
| `infra.db_reachable` | `SELECT 1` | Timeout 2 s | {1,1} (sofort beide Richtungen — DB-Ausfall ist nie flatterhaft) | `runbooks.md#db-unreachable` |
| `infra.db_latency` | `EXPLAIN ANALYZE SELECT 1` | warn > 50 ms, fail > 500 ms | {3,1} | `runbooks.md#db-latency` |
| `infra.redis_reachable` | `PING` | Timeout 1 s | {3,1} | `runbooks.md#redis-unreachable` |
| `infra.disk_pgdata` | `pg_database_size`-Volume freier Platz | warn < 20 %, fail < 5 % | {3,1} | `runbooks.md#disk-pressure` |
| `infra.disk_artifacts` | `/artifacts`-Volume freier Platz | warn < 15 %, fail < 5 % | {3,1} | `runbooks.md#disk-pressure` |
| `infra.disk_per_library` | je Bibliotheks-Mount freier Platz (sofern separates Filesystem) | warn < 10 % | {3,1} | `runbooks.md#disk-pressure` |
| `infra.clock_skew` | `now() - php-Zeit` | warn > 5 s, fail > 60 s | {3,1} | `runbooks.md#clock-skew` |

### Kategorie: Queues

| Key | Bedingung | Schwelle | Debounce | Remedy-Anker |
|---|---|---|---|---|
| `queue.worker_present` | Horizon-Supervisor-Status je Queue | fail: 0 Worker > 2 Läufe | {2,1} | `runbooks.md#worker-missing` |
| `queue.depth` | Queue-Länge (Redis `LLEN`) | warn/fail nach [Job-Referenz](../../architecture/jobs-reference.md)-Queue-Profil (`default` warn>500, `scan` warn>50, `analyze` warn>200, `ai` warn>20) | {3,1} | `runbooks.md#queue-depth` |
| `queue.failed_series` | `failed_jobs`-Neuzugänge seit letztem Lauf | warn ≥ 5, fail ≥ 20 | {2,1} | `runbooks.md#failed-jobs` |
| `queue.oldest_wait` | Alter des ältesten wartenden Jobs je Queue | warn > 2× Queue-typisches Intervall | {3,1} | `runbooks.md#queue-depth` |

### Kategorie: Bibliotheken

| Key | Bedingung | Schwelle | Debounce | Remedy-Anker |
|---|---|---|---|---|
| `library.mount_reachable` | Marker-Datei (`.mediaforge-library`) lesbar + UUID-Match | fail bei Fehlschlag | {2,1} | `runbooks.md#mount-unreachable` |
| `library.scan_freshness` | `now() - last_scan_completed_at` | warn > `scan_interval_min`×2 | {1,1} | `runbooks.md#scan-stale` |
| `library.deletion_dampening_triggered` | offener `mass_deletion`-Review | warn bei Existenz | {1,1} | `runbooks.md#mass-deletion` |

### Kategorie: Connectoren

| Key | Bedingung | Schwelle | Debounce | Remedy-Anker |
|---|---|---|---|---|
| `connector.instance_health` | Spiegel von `connector_instances.health_status` | warn=`degraded`, fail=`unreachable`/`auth_failed` | {1,1} (SDK hat eigene Entprellung) | `runbooks.md#connector-degraded` |
| `connector.cursor_age` | `now() - last_success_at` je Sync-Stream | warn > 3× Intervall | {3,1} | `runbooks.md#connector-degraded` |
| `connector.outbox_backlog` | `connector_outbox`-Zeilen `pending`/`failed` je Instanz | warn > 100, fail > 1000 | {3,1} | `runbooks.md#outbox-backlog` |

### Kategorie: AI

| Key | Bedingung | Schwelle | Debounce | Remedy-Anker |
|---|---|---|---|---|
| `ai.worker_heartbeat` | `mediaforge:ai:workers:*`-Keys mit TTL < 90 s vorhanden | fail bei 0 Workern **und** aktiver Upscale-Nachfrage (kein Fail, wenn Feature ungenutzt) | {2,1} | `runbooks.md#ai-worker-down` |
| `ai.model_integrity` | Worker meldet `models[].loaded=false` mit `reason=hash_mismatch` | fail sofort | {1,1} | `runbooks.md#model-integrity` |
| `ai.queue_without_worker` | `ai`-Queue-Tiefe > 0 **und** kein Heartbeat | fail | {2,1} | `runbooks.md#ai-worker-down` |

### Kategorie: Sicherheit

| Key | Bedingung | Schwelle | Debounce | Remedy-Anker |
|---|---|---|---|---|
| `security.login_failure_series` | fehlgeschlagene Logins je IP/Account in 15 min | warn ≥ 10, fail ≥ 30 | {1,1} | `runbooks.md#login-failures` |
| `security.webhook_signature_failures` | ungültige Webhook-Signaturen je Instanz in 1 h | warn ≥ 5 | {2,1} | `runbooks.md#webhook-signature` |
| `security.schema_fingerprint` | Abgleich des erwarteten Schema-Hashes ([architecture/security.md](../../architecture/security.md)) | fail bei Abweichung ohne begleitende Migration | {1,1} | `runbooks.md#schema-fingerprint` |

### Kategorie: Wartung

| Key | Bedingung | Schwelle | Debounce | Remedy-Anker |
|---|---|---|---|---|
| `maint.backup_age` | `now() - letzter erfolgreicher backup_runs.finished_at` | warn > 36 h, fail > 72 h (bei täglichem Default-Plan) | {1,1} | `runbooks.md#backup-stale` |
| `maint.restore_probe_age` | `now() - letzte erfolgreiche restore_probes.probed_at` | warn > 30 Tage, fail > 45 Tage | {1,1} | `runbooks.md#restore-probe-stale` |
| `maint.partition_lookahead` | künftige Partition (übernächster Monat) existiert für `watch_state_events`/`audit_log`/`disc_playback_events` | fail bei Fehlen | {1,1} | `runbooks.md#partition-missing` |
| `maint.embedding_backlog` | Items ohne aktuelles Embedding (`search.active_embedding_model`) | warn > 5 % des Bestands | {3,1} | `runbooks.md#embedding-backlog` |
| `maint.artifact_orphan_backlog` | `artifacts.status='orphaned'` älter als Karenz | warn > 100 | {3,1} | `runbooks.md#artifact-orphans` |

## Metrik-Schlüssel-Katalog (`metrics`-Tabelle / `/metrics`)

| `metric_key` | Einheit | Quelle |
|---|---|---|
| `queue.depth.{queue}` | Anzahl | Redis-Sampling je Lauf |
| `queue.job_duration_p95.{class}` | ms | `job_progress`-Aggregat |
| `scan.duration_seconds.{library}` | s | `ScanLibraryJob`-Abschluss |
| `disc.analysis_duration_seconds` | s | `AnalyzeDiscImageJob`-Abschluss |
| `assembler.sequence_confidence_avg` | 0–1 | täglicher Aggregat-Sweep |
| `upscaler.chunk_rtf` | Faktor | Worker-`stats.rtf`-Mittel |
| `search.semantic_coverage` | 0–1 | letzter `SearchService`-Lauf-Sample |
| `rule.firing_rate.{rule_id}` | /h | `rule_firings`-Fenster |
| `connector.outbox_backlog.{instance}` | Anzahl | `connector_outbox`-Sampling |
| `db.size_bytes` | Bytes | `pg_database_size` |
| `artifacts.size_bytes_by_type.{type}` | Bytes | `artifacts`-Aggregat |

Alle Schlüssel folgen dem Muster `{domäne}.{messwert}[.{dimension}]`; die Prometheus-Exposition (`/metrics`) mappt Punkte auf Unterstriche (`queue_depth_default 12`) und Dimensionen auf Labels (`queue="default"`), damit dieselbe Quelle beide Konsumenten (interne Sparklines, externes Prometheus) bedient, ohne zwei Erhebungspfade zu pflegen.

## Digest-Bündelung (Ergänzung zum Modulkapitel Edge Case)

Bei einem Lauf mit ≥ 3 gleichzeitigen Zustandswechseln auf `fail`/`warn` bündelt der `NotificationDispatcher` sie zu **einem** Digest statt N Einzelnachrichten. Korrelations-Heuristik für den Ursachen-Hinweis: Wechseln alle betroffenen Checks derselben `category()` gleichzeitig, wird die Kategorie als vermutliche gemeinsame Ursache benannt („alle Bibliotheks-Checks betroffen — Mount-Ebene prüfen", Modulkapitel-Beispiel); bei gemischten Kategorien wird kein Ursachen-Rateversuch unternommen (ehrliches „mehrere unabhängige Befunde" statt falscher Korrelation).

## Boot-Validierung (Contract-Test)

Beim Start prüft `HealthCheckRegistry`: (1) jeder registrierte `key()` ist eindeutig, (2) jeder `remedyRef` löst zu einem existierenden Anker in `runbooks.md` auf (Markdown-Anchor-Parsing gegen die Datei), (3) `interval()` ist ein Vielfaches der minütlichen Scheduler-Taktung, (4) `debounce()`-Werte sind ≥ 1. Verletzung (2) ist der häufigste Fund bei neuen Checks in der Praxis — ein Check ohne dokumentiertes Abhilfe-Runbook widerspricht der Modulkapitel-Motivation („was, seit wann, was tun") strukturell, nicht nur stilistisch.

## Tests (Ergänzung)

Je Check ein Grenzwert-Testpaar (Schwelle − 1 ⇒ `ok`/`warn`, Schwelle ⇒ `warn`/`fail`) gegen konstruierte Fixtures; Debounce-Matrix (N−1 gleiche Befunde ⇒ kein Übergang, N ⇒ Übergang, gemischte Sequenz ⇒ Zähler-Reset); Boot-Validierungs-Suite (absichtlich kaputte Registrierung — fehlender Remedy-Anker — muss den Boot-Test scheitern lassen); Digest-Korrelations-Suite (gleiche Kategorie ⇒ Hinweis, gemischt ⇒ kein Hinweis).
