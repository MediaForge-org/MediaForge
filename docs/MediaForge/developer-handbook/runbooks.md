# Betriebs-Runbooks

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [modules/health-monitoring/health-check-reference.md](../modules/health-monitoring/health-check-reference.md) (jeder Check verweist hierher über `remedyRef`), [database/query-catalog.md](../database/query-catalog.md) (Diagnose-Rezepte), [architecture/jobs-reference.md](../architecture/jobs-reference.md) (Job-Timeout-/Retry-Profile). Jeder Abschnitt hier ist das Abhilfe-Ziel eines Health-Checks — die Überschriften-Anker sind Vertrag, nicht Stil (Contract-Test des Health-Moduls prüft ihre Existenz).

## Lesehinweis

Jedes Runbook folgt derselben Form: **Symptom** (was der Check meldet), **Sofortdiagnose** (read-only, gefahrlos), **Abhilfe** (Stufen, von reversibel zu eingreifend), **Eskalation** (wann an den nächsten Schritt). Runbooks enthalten keine destruktiven Befehle ohne explizite Bestätigungs-Aufforderung im Text — wer kopiert, ohne zu lesen, soll höchstens etwas Read-only tun.

## Infrastruktur

### DB Unreachable

**Symptom**: `infra.db_reachable` fail; praktisch der Vollausfall der Ausfallmatrix ([architecture/overview.md](../architecture/overview.md)). **Sofortdiagnose**: `docker compose ps postgres` (läuft der Container?), `docker compose logs --tail=100 postgres` (OOM-Kill? Diskvoll? Crash-Loop?). **Abhilfe**: (1) Container-Restart-Policy sollte bereits greifen — `docker compose restart postgres` nur, wenn sie es nicht tut; (2) Diskplatz auf dem `pgdata`-Volume prüfen (`df -h`) — Postgres verweigert bei vollem WAL-Verzeichnis den Start; (3) bei Crash-Loop: Logs auf `PANIC`/`FATAL` prüfen, ggf. `pg_resetwal` als letzter Schritt **nur** nach Rücksprache mit einem aktuellen Backup in der Hand. **Eskalation**: Wenn der Container nicht sauber hochkommt → [Restore-Probe-Verfahren](#restore-probe-stale) auf einem frischen Volume erwägen, nie blind `initdb` über bestehende Daten.

### DB Latency

**Symptom**: `infra.db_latency` warn/fail. **Sofortdiagnose**: `SELECT * FROM pg_stat_activity WHERE state != 'idle' ORDER BY query_start LIMIT 20;` (lang laufende Queries?), `SELECT * FROM pg_locks WHERE NOT granted;` (Lock-Wartende?). Gegen den [Query-Katalog](../database/query-catalog.md) abgleichen: taucht eine Anti-Katalog-Query auf (Seq-Scan auf XL-Tabelle, `SELECT *` mit TOAST-Spalte)? **Abhilfe**: blockierende Query identifizieren, bei eindeutigem Fehlverhalten (kein Fortschritt seit Minuten) `SELECT pg_cancel_backend(pid);` (sanft) vor `pg_terminate_backend(pid)` (hart). Bei wiederkehrendem Muster: fehlenden Index gegen den Query-Katalog prüfen, nicht den Symptomträger jagen. **Eskalation**: anhaltend hohe Latenz ohne erkennbare Einzelquery → `VACUUM ANALYZE` auf den größten Tabellen prüfen (Autovacuum-Rückstand: `SELECT relname, last_autovacuum FROM pg_stat_user_tables ORDER BY last_autovacuum NULLS FIRST LIMIT 10;`).

### Redis Unreachable

**Symptom**: `infra.redis_reachable` fail. Laut Ausfallmatrix: HTTP läuft weiter, Jobs pausieren, Locks entfallen. **Sofortdiagnose**: `docker compose ps redis`, `redis-cli -h redis PING` aus einem Worker-Container. **Abhilfe**: Container-Neustart; Redis ist laut Fundament-Definition ohne Persistenz-Anspruch für Fachdaten (nur Queues/Cache/Locks) — ein Datenverlust bei Neustart ist tolerierbar, Jobs greifen über Checkpoints (PostgreSQL) nach ([architecture/overview.md](../architecture/overview.md)). **Eskalation**: wiederholte Abstürze → `maxmemory`-Policy und AOF-Konfiguration prüfen (Redis sollte hier nie an Speichergrenzen laufen; die Queues sind klein).

### Disk Pressure

**Symptom**: `infra.disk_pgdata`/`infra.disk_artifacts`/`infra.disk_per_library` warn/fail. **Sofortdiagnose**: `df -h` auf allen relevanten Mounts; `du -sh /artifacts/*` nach Modul sortiert (welcher Erzeuger wächst?); Query-Katalog `Q-42` (Waisen-Artefakte) und `maint.artifact_orphan_backlog`-Metrik prüfen. **Abhilfe** (Reihenfolge nach Reversibilität): (1) `PruneBackupsJob` manuell anstoßen, falls Backup-Rotation hinterherhinkt; (2) Artefakt-GC für `orphaned`-Artefakte über die Karenzfrist hinaus laufen lassen (kein manuelles `rm` — die Registrierung in `artifacts` muss mit der Datei verschwinden); (3) bei `pgdata`: `watch_state_events`/`audit_log`-Partitionierung prüfen (überfällige Retention? [`maint.partition_lookahead`](#partition-missing)). **Eskalation**: strukturelles Wachstum über die Kapazität → Volume-Erweiterung ist eine Deployment-Entscheidung, kein Runbook-Schritt.

### Clock Skew

**Symptom**: `infra.clock_skew` warn/fail. **Sofortdiagnose**: `date` auf Host und in den Containern vergleichen (`docker compose exec app date`); NTP-Status des Hosts (`timedatectl` unter systemd). **Abhilfe**: Host-NTP korrigieren (Container übernehmen die Host-Uhr, sofern nicht explizit isoliert). **Eskalation**: Skew wirkt sich auf `occurred_at`-Konfliktlogik (Connector-SDK, Disc-Playback) aus — nach Korrektur die betroffenen Zeiträume in `connector_ingest_log`/`disc_playback_events` auf Anomalien sichten, falls der Skew über Stunden bestand.

## Queues

### Worker Missing

**Symptom**: `queue.worker_present` fail für eine Queue. **Sofortdiagnose**: `docker compose ps` (welcher `worker-*`-Container fehlt?), Horizon-Dashboard (`/horizon`, falls erreichbar), `docker compose logs worker-{name}`. **Abhilfe**: Container-Neustart; bei Crash-Loop Log auf PHP-Fatal/Memory-Limit prüfen (lange Jobs können OOM auslösen — [Job-Referenz](../architecture/jobs-reference.md) nennt die Ressourcenprofile je Queue). **Eskalation**: wiederholtes Sterben derselben Queue → den zuletzt darauf gelaufenen Job-Typ identifizieren (`job_progress` nach `updated_at DESC`) und gegen dessen Timeout-Profil prüfen.

### Queue Depth

**Symptom**: `queue.depth` warn/fail. **Sofortdiagnose**: `redis-cli LLEN queues:{name}`; welcher Job-Typ dominiert (`job_progress`-Aggregat nach `job_class`)? Gegen die [Ressourcen-Konfliktmatrix](../architecture/jobs-reference.md#job-×-ressourcen-konfliktmatrix) prüfen — konkurriert die Queue gerade mit einem bekannten Engpass (NAS-I/O, GPU, Rate-Limit)? **Abhilfe**: bei `scan`/`analyze`: NAS-Auslastung prüfen, ggf. Parallelität temporär senken (Setting); bei `connector`: Rate-Limiter-Auslastung je Instanz prüfen (ein einzelner langsamer Connector kann die Queue nicht mehr blockieren als seinen eigenen Limiter, sofern die Instanz-Isolation korrekt greift — falls doch, ist das ein Bug, kein Betriebszustand). **Eskalation**: dauerhaft wachsende Tiefe ohne erkennbaren Engpass → zusätzliche Worker-Replica für die Queue (Compose-Skalierung), niemals Timeout-Werte pauschal erhöhen als erste Reaktion.

### Failed Jobs

**Symptom**: `queue.failed_series` warn/fail. **Sofortdiagnose**: `php artisan queue:failed` bzw. `SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 20;`. Wichtige Unterscheidung (Fundament-Konvention): Failed-Jobs sind für **Infrastrukturfehler** reserviert, nicht für erwartbare Datenprobleme (die laufen über Fachfehler-Pfade mit Review-Erzeugung) — eine Serie hier deutet auf einen echten Bug oder Infrastrukturausfall. **Abhilfe**: Exception-Klasse prüfen; bei transienten Ursachen (Netzwerk, kurzer DB-Blip) `php artisan queue:retry all`; bei wiederholtem, identischem Fehler: Job pausieren (Queue-Consumer stoppen) statt endlos retryen zu lassen, Ursache im Code beheben. **Eskalation**: Serie über mehrere Job-Klassen gleichzeitig → auf `infra.*`-Checks zurückgehen (oft eine gemeinsame Ursache).

## Bibliotheken

### Mount Unreachable

**Symptom**: `library.mount_reachable` fail. **Sofortdiagnose**: `docker compose exec worker-scan ls /media/{bibliothek}` (ist der Mount im Container sichtbar?), Host-seitig `mount | grep {pfad}` (NFS/SMB-Verbindung noch aktiv?). **Wichtig**: Dies ist die Schutzfunktion gegen den „leerer Mount sieht aus wie gelöschte Bibliothek"-Fall ([architecture/overview.md](../architecture/overview.md)) — der Scan bricht bereits automatisch ab, ohne Dateien als gelöscht zu markieren. Kein Grund zur Eile bei der Behebung. **Abhilfe**: Netzwerkfreigabe/NAS prüfen, Mount neu einhängen (Host-Ebene, außerhalb von MediaForge), danach wartet der nächste reguläre Scan-Lauf. **Eskalation**: wiederholte Mount-Abrisse → Netzwerk-/NAS-Stabilität ist ein Infrastrukturthema außerhalb von MediaForge.

### Scan Stale

**Symptom**: `library.scan_freshness` warn. **Sofortdiagnose**: `SELECT last_scan_started_at, last_scan_completed_at, scan_enabled FROM libraries WHERE id = ?;` — läuft ein Scan (started ohne completed), oder ist `scan_enabled=false`? Scheduler-Log prüfen. **Abhilfe**: bei hängendem Scan: Job-Status in `job_progress` prüfen (Phase, `done`/`total` seit wann unverändert?); bei echtem Hänger `ScanLibraryJob` für diese Bibliothek gezielt neu anstoßen (API: `POST /libraries/{id}/scan` bzw. Artisan-Äquivalent) — der ResumableJob-Vertrag macht das gefahrlos (Fortsetzung über Checkpoints). **Eskalation**: wiederholtes Hängenbleiben am selben Schritt → giftiger Schritt vermuten ([Job-Referenz](../architecture/jobs-reference.md)), `job_checkpoints.attempts` für den `checkpoint_key` prüfen.

### Mass Deletion

**Symptom**: `library.deletion_dampening_triggered` warn (ein `mass_deletion`-Review ist offen). **Sofortdiagnose**: Review-Inbox öffnen, betroffene Bibliothek und Lösch-Prozentsatz ansehen. **Abhilfe**: Ursache klären (Mount kurz weg gewesen? Ordner absichtlich umbenannt/verschoben? echte Massenlöschung gewollt?) — dann im Review bestätigen (Löschungen anwenden) oder verwerfen (nächster Scan sieht die Dateien wieder, sofern der Mount wiederhergestellt ist). **Nie** vor Klärung der Ursache bestätigen — das ist genau der Schutzmechanismus, den die Dämpfung bereitstellt.

## Connectoren

### Connector Degraded

**Symptom**: `connector.instance_health` warn/fail. **Sofortdiagnose**: `Connectors/Activity`-UI der Instanz öffnen (Health-Detail, letzte Fehler); `GET /api/v1/connector-instances/{ulid}/activity`. **Abhilfe je Ursache**: `auth_failed` → API-Key/Token der Gegenstelle prüfen und ggf. erneuern (Secret-Store-Update über die Instanz-Settings, nie direkt in der DB); `unreachable` → Gegenstelle erreichbar? (`curl` aus dem Worker-Container gegen die Basis-URL); Versions-Inkompatibilität → Diagnostics-Report auf die gemeldete Version prüfen (siehe die jeweilige [API-Mapping-Referenz](../connectors/) des Connectors für die Versionsmatrix). **Eskalation**: nach Behebung der Grundursache heilt der Status über den nächsten `ConnectorHealthCheckJob`-Lauf automatisch; kein manuelles Zurücksetzen nötig.

### Outbox Backlog

**Symptom**: `connector.outbox_backlog` warn/fail. **Sofortdiagnose**: `SELECT status, count(*) FROM connector_outbox WHERE instance_id = ? GROUP BY status;`. Meist Folge eines bereits erkannten `connector.instance_health`-Problems. **Abhilfe**: Grundursache (Connector-Erreichbarkeit) zuerst beheben; die Koaleszenz-Mechanik ([Connector SDK](../connectors/connector-sdk.md)) reduziert den Rückstau automatisch, sobald der Egress-Job wieder läuft (ältere unzugestellte Änderungen werden durch neuere ersetzt, nicht einzeln nachgezogen). **Eskalation**: Backlog bleibt trotz erreichbarer Gegenstelle hoch → Rate-Limit-Konfiguration der Instanz prüfen (zu niedrig für die Änderungsrate?).

## AI

### AI Worker Down

**Symptom**: `ai.worker_heartbeat` bzw. `ai.queue_without_worker` fail. **Sofortdiagnose**: `docker compose ps ai-worker`; `redis-cli HGETALL mediaforge:ai:workers:*` (Heartbeat-Alter); Worker-Logs auf CUDA-/Modell-Ladefehler. **Abhilfe**: Container-Neustart; bei GPU-Fehlern (`nvidia-smi` im Host prüfen — Treiber-Problem? VRAM durch einen Zombie-Prozess belegt?); Feature ist strikt optional (Modulkapitel [Audio-Upscaler](../modules/audio-upscaler.md)) — ohne Worker bleibt die `ai`-Queue liegen, gestaute Jobs laufen nach dem Neustart automatisch weiter (Checkpoint-Mechanik). **Eskalation**: dauerhaft kein Worker gewünscht (Feature deaktiviert) → Compose-Profil ohne `ai-worker` fahren, der Check ist dann per Definition inaktiv (kein Fail ohne aktive Nachfrage).

### Model Integrity

**Symptom**: `ai.model_integrity` fail. **Sofortdiagnose**: Worker-Log auf `weights_hash`-Mismatch prüfen ([Worker-Protokoll](../modules/audio-upscaler/worker-protocol.md)). **Abhilfe**: Modell-Volume auf Beschädigung/unvollständigen Download prüfen, Modell-Datei aus vertrauenswürdiger Quelle neu beziehen, Hash gegen die Registry-Erwartung verifizieren **vor** Wiedereinsatz. **Nie** die Hash-Prüfung umgehen oder deaktivieren — sie ist die einzige Verteidigung gegen ausgetauschte Gewichte (Security-Grenze des Moduls).

## Sicherheit

### Login Failures

**Symptom**: `security.login_failure_series` warn/fail. **Sofortdiagnose**: Audit-Log nach fehlgeschlagenen Login-Versuchen filtern (IP/Account-Häufung). **Abhilfe**: bei erkennbarem Angriffsmuster (viele IPs, ein Account) → Account-Sperre erwägen, Reverse-Proxy-Rate-Limiting prüfen (liegt außerhalb von MediaForge, aber die Doku empfiehlt es); bei einzelnem Benutzer mit vergessenem Passwort → regulärer Reset-Flow. **Eskalation**: verteilter Angriff → Reverse-Proxy-/Firewall-Ebene ist der richtige Ort für IP-Blocking, nicht die Anwendung.

### Webhook Signature

**Symptom**: `security.webhook_signature_failures` warn. **Sofortdiagnose**: welche Instanz, welcher Pfad? Signaturfehler häufen sich typischerweise nach Secret-Rotation ohne Aktualisierung der Gegenstelle. **Abhilfe**: Webhook-Konfiguration der Gegenstelle (z. B. Jellyfin-Webhook-Plugin) mit dem aktuellen Instanz-Secret abgleichen; bei Verdacht auf aktiven Missbrauchsversuch (fremde Quelle, nicht die eigene Gegenstelle) → Zugriffsprotokoll des Reverse Proxy auf die Herkunfts-IP prüfen.

### Schema Fingerprint

**Symptom**: `security.schema_fingerprint` fail (Schema hat sich ohne begleitende Migration verändert). **Sofortdiagnose**: `php artisan migrate:status` — steht eine Migration aus oder wurde außerhalb des Migrationswegs manuell am Schema geändert? **Abhilfe**: ausstehende Migration regulär anwenden; bei manueller Fremdänderung (nie beabsichtigt) → Ursache klären, bevor weitergearbeitet wird — ein abweichendes Schema ohne Migrationsspur ist ein Integritätsalarm, kein Routinebefund.

## Wartung

### Backup Stale

**Symptom**: `maint.backup_age` warn/fail. **Sofortdiagnose**: `SELECT * FROM backup_runs ORDER BY started_at DESC LIMIT 5;` — läuft `RunBackupJob` fehlerhaft, oder ist der Scheduler-Eintrag inaktiv? **Abhilfe**: manuellen Lauf anstoßen (`POST /api/v1/backups` bzw. `php artisan mediaforge:backup`); bei wiederholtem Fehlschlag: `error_detail` prüfen (häufigste Ursache: Backup-Ziel voll oder nicht erreichbar, [Modulkapitel Edge Case](../modules/backup-restore.md)). **Eskalation**: siehe [Backup-und-Restore-Kapitel](../modules/backup-restore.md) für die vollständige Fehlerklassifikation.

### Restore Probe Stale

**Symptom**: `maint.restore_probe_age` warn/fail. **Sofortdiagnose**: `SELECT * FROM restore_probes ORDER BY probed_at DESC LIMIT 1;`. **Abhilfe**: `php artisan mediaforge:restore --verify <neuester-satz>` manuell anstoßen; bei Fehlschlag den Report (`restore_probes.report`) auf Kennzahlen-Abweichungen prüfen (Migrations-Inkompatibilität? Manifest-Zähler weichen vom eingespielten Bestand ab?). **Wichtig**: Eine fehlgeschlagene Probe ist ein Befund über die **Backup-Qualität**, nicht über die Produktion — sie berührt den laufenden Betrieb nicht, verdient aber zeitnahe Klärung, weil sie die einzige Garantie ist, dass ein echter Restore im Ernstfall funktioniert.

### Partition Missing

**Symptom**: `maint.partition_lookahead` fail. **Sofortdiagnose**: `SELECT * FROM pg_partition_tree('watch_state_events'::regclass);` (analog für `audit_log`, `disc_playback_events`) — fehlt die Partition für den übernächsten Monat? **Abhilfe**: `MaintainPartitionsJob` manuell anstoßen ([Job-Referenz](../architecture/jobs-reference.md)); dies ist zeitkritisch — ohne rechtzeitige Partition scheitern Inserts in die betroffene Tabelle am Monatswechsel vollständig (kein Fallback-Verhalten, [Datenbank-Referenz](../database/schema-reference.md)). **Eskalation**: wiederholtes Fehlen deutet auf einen inaktiven/kaputten Scheduler-Eintrag hin, nicht auf einen Einzelfall — Scheduler-Konfiguration insgesamt prüfen.

### Embedding Backlog

**Symptom**: `maint.embedding_backlog` warn. **Sofortdiagnose**: `search.semantic_coverage`-Metrik-Verlauf ansehen; läuft `EmbedSubjectJob` regelmäßig (Queue `ai-light`)? **Abhilfe**: Queue-Tiefe von `ai-light` prüfen (eigene, von `ai` getrennte Queue — sollte nicht hinter Upscale-Läufen stauen, [Job-Referenz](../architecture/jobs-reference.md)); bei großem Rückstand nach Modellwechsel: `ReembedAllJob`-Fortschritt prüfen. **Eskalation**: keine — der Rückstand ist funktional harmlos (lexikalische Suche bleibt voll verfügbar, Modulkapitel [Suche](../modules/search.md)), nur eine Qualitätsminderung der semantischen Stufe.

### Artifact Orphans

**Symptom**: `maint.artifact_orphan_backlog` warn. **Sofortdiagnose**: `SELECT artifact_type, count(*) FROM artifacts WHERE status='orphaned' GROUP BY artifact_type;`. **Abhilfe**: Housekeeping-GC-Lauf prüfen (läuft er planmäßig? Karenzfrist erreicht?); bei Bedarf manuell anstoßen. **Eskalation**: strukturell wachsender Rückstand → Ursache ist meist eine erhöhte Lösch-/Rebuild-Rate eines bestimmten Artefakt-Typs (Assembler-Rebuilds nach Kapitel-Korrekturen, Upscale-Wiederholungen) — gegen die jeweilige Modul-Aktivität abgleichen, kein isoliertes Health-Problem.

## Allgemeine Eskalationsleiter

Für Symptome ohne exakte Check-Zuordnung: (1) betroffene(s) Modulkapitel + zugehörige API-/Job-/Query-Referenz konsultieren, (2) `correlation_id` der auffälligen Operation im Audit verfolgen (Audit-Modul: die Kausalkette über Scan → Analyse → Mapping → Review ist durchgängig), (3) Fixture-/Test-Suiten des betroffenen Moduls als Referenzverhalten heranziehen (jede normative Aussage in den Vertiefungsdateien hat einen Test — ein abweichendes Produktionsverhalten gegen einen dokumentierten Test ist entweder ein Bug oder ein Betriebszustand außerhalb der Spezifikation), (4) bei Unsicherheit: read-only bleiben, Zustand einfrieren (betroffene Queue pausieren, Instanz deaktivieren), bevor Korrekturen versucht werden.
