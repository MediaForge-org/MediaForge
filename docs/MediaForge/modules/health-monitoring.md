# Health Monitoring

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [architecture/overview.md](../architecture/overview.md) (Ausfallmatrix), [architecture/deployment.md](../architecture/deployment.md). Konsument: [Admin-Dashboard](admin-dashboard.md) (Anzeige), [Rule Engine](rule-engine.md) (`notify`).

**Vertiefung**: [Health-Check- und Metrik-Referenz](health-monitoring/health-check-reference.md) (vollständiger Check-Katalog mit Schwellen, Metrik-Schlüssel) · Abhilfen: [developer-handbook/runbooks.md](../developer-handbook/runbooks.md)

## Motivation

Ein Heimserver hat kein Ops-Team; der Betreiber schaut vorbei, wenn etwas auffällt — oder wenn MediaForge ihm sagt, dass etwas auffallen sollte. Health Monitoring beantwortet zwei Fragen kontinuierlich: **„Ist alles in Ordnung?"** (ein aggregierter Zustand, ehrlich berechnet, kein grünes Theater) und **„Wenn nein — was, seit wann, was tun?"** (benannte Checks mit Abhilfe-Verweis). Die *arr-Health-Seite ist das UX-Vorbild (Masterdatei-Referenz): eine Liste konkreter Befunde mit Doku-Links, keine Metrik-Friedhöfe.

## Problemstellung

**Check vs. Metrik.** Checks sind boolesche Befunde mit Abhilfe („ai-Queue hat Worker: nein → Doku X"); Metriken sind Zeitreihen für Trends (Queue-Tiefe, Scan-Dauern). Beide vermischt ergeben Dashboards, die niemand liest. Das Modul trennt sie strikt und hält die Metrik-Seite bewusst klein (MediaForge ist kein Observability-Produkt; wer Grafana will, bekommt einen Metrics-Export).

**Flattern.** Ein NAS, das nachts schläft, erzeugt naive Alarme im Stundentakt. Checks brauchen Entprellung (Zustandswechsel erst nach N konsekutiven Befunden) und Ruhefenster (Wartungszeiten).

**Selbstbeobachtung.** Der Monitor läuft im System, das er überwacht — Scheduler tot heißt: auch der Health-Scheduler ist tot. Die Erkennung „Monitoring läuft selbst nicht" braucht einen externen Anker.

## Architekturentscheidung

**Health-Checks als registrierte Klassen** (Registry-Muster wie Rule-Prädikate und Quality-Checks — dritte Anwendung desselben Musters, bewusst): `HealthCheckInterface` mit `key()`, `category()`, `run(): HealthResult (ok|warn|fail + Detail + Abhilfe-Referenz)`, `interval()`. Module registrieren ihre Checks; das Fundament bringt die Basis:

| Kategorie | Checks (Auswahl) |
|---|---|
| Infrastruktur | DB erreichbar/Latenz, Redis, Plattenplatz (`pgdata`, `artifacts`, pro Bibliothek), DB-Zeit vs. App-Zeit |
| Queues | Worker pro Queue vorhanden, Queue-Tiefe über Schwellwert, Failed-Jobs-Serie, älteste Job-Wartezeit |
| Bibliotheken | Mount erreichbar + Marker gültig, letzter erfolgreicher Scan < Intervall×2, Lösch-Dämpfung ausgelöst |
| Connectoren | Instanz-Health (SDK-Spiegel), Cursor-Alter, Outbox-Rückstau |
| AI | Worker-Heartbeats, Modell-Integrität, `ai`-Queue ohne Worker |
| Sicherheit | Login-Fehlerserien, Webhook-Signaturfehler, Schema-Fingerprint ([security](../architecture/security.md)-Ereignisklasse) |
| Wartung | Backup-Alter ([backup-restore](backup-restore.md)), Partitions-Vorlauf, Backfill-Stau, anstehende Artefakt-Waisen |

**Zustandsmaschine mit Entprellung**: Jeder Check hält seinen Zustand (`health_states`); Übergänge erfordern N konsekutive gleiche Befunde (Default 3 für fail→, 1 für →ok — schnell entwarnen, langsam alarmieren); Übergänge erzeugen Events (`HealthStateChanged`) für Notifications und Dashboard. Der Gesamtzustand ist das Maximum der Kategorien-Zustände mit Anzeige der Verursacher — nie ein Durchschnitt (ein toter Worker verschwindet nicht in 40 grünen Checks).

**Metriken minimal**: eine `metrics`-Tabelle (Zeitreihe: key, value, bucket) für die Handvoll Trend-Fragen des Dashboards (Queue-Tiefen, Scan-Dauern, Speicherverläufe, Feuerraten), stündlich aggregiert, 90 Tage Retention — plus ein optionaler **Prometheus-Export** (`/metrics`, Token-geschützt) für Betreiber mit eigener Observability; MediaForge baut kein Grafana nach.

**Externer Anker**: ein optionaler Dead-Man-Switch — MediaForge pingt periodisch eine konfigurierbare URL (Healthchecks.io-Klasse, selbst hostbar); bleibt der Ping aus, alarmiert der externe Dienst. Die Antwort auf die Selbstbeobachtungs-Frage, ohne eigene Zweitinfrastruktur zu erfinden.

## Datenmodell und SQL-Schema

```sql
CREATE TABLE health_states (
    check_key     TEXT PRIMARY KEY,
    category      TEXT        NOT NULL,
    status        TEXT        NOT NULL CHECK (status IN ('ok','warn','fail','unknown')),
    consecutive   INTEGER     NOT NULL DEFAULT 0,   -- gleiche Befunde in Folge (Entprellung)
    detail        TEXT,
    remedy_ref    TEXT,                             -- Doku-Anker
    last_run_at   TIMESTAMPTZ NOT NULL,
    changed_at    TIMESTAMPTZ NOT NULL              -- letzter Zustandswechsel
);

CREATE TABLE metrics (
    metric_key    TEXT        NOT NULL,
    bucket        TIMESTAMPTZ NOT NULL,             -- Stunden-Bucket
    value         DOUBLE PRECISION NOT NULL,
    PRIMARY KEY (metric_key, bucket)
);
```

Bewusst schlicht: Health-Historie über die Events im Audit (Zustandswechsel sind auditierte Systemereignisse), nicht als eigene Zeitreihe; `metrics` ohne ULIDs/Zeilen-Overhead (reine Zeitreihe, DROP-basierte Retention über Bucket-Bereiche).

## Laravel-Klassen

| Klasse | Typ | Vertrag |
|---|---|---|
| `HealthCheckRegistry` | Service | Registrierung, Intervall-Planung |
| `RunHealthChecksJob` | Job (Scheduler, minütlich) | fällige Checks ausführen (Timeout 5 s pro Check — ein hängender Check darf den Lauf nicht reißen), Zustandsmaschine, Events |
| `MetricRecorder` | Service | `record(key, value)` gebuffert (Redis) mit stündlichem Flush — Metrik-Schreiben darf nie Fachpfade verlangsamen |
| `NotificationDispatcher` | Service | Kanäle (Mail/Gotify/ntfy/Webhook) als Treiber-Registry; konsumiert `HealthStateChanged` + Rule-Engine-`notify` — **ein** Kanalsystem für beide (der offene Punkt der Rule Engine wird hiermit hierher aufgelöst) |
| `DeadManPingJob` | Job (Scheduler) | externer Anker |
| Ruhefenster | Setting | `monitoring.quiet_windows` (Cron-artige Fenster): Notifications unterdrückt, Zustände laufen weiter |

## API und UI

`GET /api/v1/health` (aggregiert + Befundliste, `manager`; eine ungeschützte Minimal-Variante `GET /up` liefert nur 200/503 für Container-Healthchecks und externe Prober — ohne Details, kein Informationsleck). `GET /metrics` (Prometheus, Token). UI: die Health-Sektion des [Admin-Dashboards](admin-dashboard.md) — Befundliste nach Kategorie mit Abhilfe-Links, Verlaufs-Sparklines aus `metrics`, Ruhefenster-Verwaltung, Kanal-Konfiguration mit Testsende-Button.

## Edge Cases

* **Check selbst defekt** (wirft): zählt als `unknown` mit eigenem Meta-Befund („Check X wirft seit …") — Monitoring-Bugs werden sichtbar statt still grün.
* **Massen-Zustandswechsel** (Stromausfall des NAS: 12 Checks kippen): Notification-Bündelung (ein Digest pro Lauf statt 12 Pings), Ursachen-Hinweis über Kategorie-Korrelation („alle Bibliotheks-Checks betroffen — Mount-Ebene prüfen").
* **Redis weg**: der Metrik-Buffer fällt aus — Metriken pausieren (verzichtbar per Definition), Checks laufen direkt (DB-basiert); der Redis-Check selbst meldet.
* **Zeitumstellung/Zeitsprünge**: Buckets in UTC; der Zeitplausibilitäts-Check deckt Host-Drift.

## Performance

Minütlicher Lauf mit fälligen Checks (< 20 aktiv pro Lauf, je < 5 s Timeout, meist < 50 ms) — vernachlässigbar. `metrics` wächst kontrolliert (~30 Keys × 24 × 90 ≈ 65k Zeilen im Fenster).

## Security

`/up` informationsfrei; `/metrics` und Befunde Token-/Rollen-geschützt (Befunde enthalten Systemdetails). Notification-Kanal-Secrets im Secret-Store; Notification-Inhalte respektieren die Sichtbarkeitsregeln (keine restriktiven Titel, keine Release-Namen — die Querschnittsregeln aus [security](../architecture/security.md)).

## Tests

Zustandsmaschinen-Matrix (Befund-Sequenzen ⇒ erwartete Übergänge/Events, Entprell-Grenzen). Check-Timeout-Isolation. Digest-Bündelung. Ruhefenster. Kanal-Treiber gegen Fakes. `/up`-Informationsfreiheit (Response-Schema-Test).

## ADR-Verweise

Registry-Muster-Wiederverwendung (Rule/Quality/Health — Konsistenz statt Neuerfindung); Ausfallmatrix aus [architecture/overview.md](../architecture/overview.md) als Check-Quelle.

## Offene Punkte

* **Kanal-Treiber-Umfang** zum Release (Mail + ntfy als Minimum?): Betriebsentscheidung.
* **Trend-Alarme** (Metrik-Schwellen statt nur Check-Booleans, z. B. „Scan-Dauer verdreifacht"): bewusst nicht Version 1 — erst Checks sauber, dann Trends.
