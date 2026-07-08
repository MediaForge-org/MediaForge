# Migrationsstrategie und Schema-Evolution

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [database/core-schema.md](core-schema.md) (Konventionen), [architecture/deployment.md](../architecture/deployment.md) (Upgrade-Ablauf). Normativ für alle Migrationen aller Module.

## Motivation

MediaForge-Installationen leben Jahre und werden von Laien-Admins per `compose pull && up -d` aktualisiert — es gibt kein Ops-Team, das kaputte Migrationen von Hand repariert. Die Migrationsstrategie muss deshalb drei Dinge garantieren: Jede Migration läuft **unbeaufsichtigt** durch (oder bricht sauber vor jeder Änderung ab), **Datenverlust ist strukturell ausgeschlossen** (destruktive Schritte sind zweiphasig), und **große Tabellen bleiben verfügbar** (keine Stunden-Locks auf `watch_state_events` beim Frühstücks-Update).

## Grundregeln

1. **Eine Tabelle, eine Ursprungs-Migration; Änderungen sind neue Migrationen.** Alte Migrationen werden nie editiert ([core-schema](core-schema.md)); die Migrationshistorie ist append-only wie das Audit-Log.
2. **Expand/Contract für alles Destruktive.** Spalten/Tabellen werden nie im selben Release entfernt, in dem ihr Ersatz kommt: Release N fügt Neues hinzu und befüllt (expand), der Code liest neu und schreibt beides wo nötig; Release N+1 (frühestens) entfernt Altes (contract), nachdem ein Prüf-Job die Migration der Daten bestätigt hat. Ein direkter `DROP COLUMN` mit Daten ist ein Review-Defekt.
3. **Downgrades gibt es nicht.** `down()`-Methoden existieren für die Entwicklung; produktiv ist der Weg zurück ein Restore ([modules/backup-restore.md](../modules/backup-restore.md)). Das Deployment dokumentiert entsprechend: Backup vor Update ist Teil des Upgrade-Pfads, nicht optionale Tugend.
4. **Migrationen enthalten kein Eloquent.** Modelle ändern sich; Migrationen sind eingefrorene Vergangenheit. Datenmigrationen in Migrationen nutzen Query Builder mit expliziten Spaltenlisten — oder werden zu Befüllungs-Jobs (unten).
5. **Idempotente Guards.** Jede Migration prüft ihre Vorbedingungen (`hasTable`/`hasColumn`/Index-Existenz) und ist bei Wiederholung folgenlos — halb gelaufene Migrationszustände (Crash mitten im Batch) dürfen den nächsten Lauf nicht sprengen.

## Große Tabellen und Lock-Verhalten

Für die bekannten Großtabellen (`watch_state_events`, `audit_*`, `rule_firings`, `connector_ingest_log` — alle partitioniert) und potenziell große (`files`, `user_watch_states`, `provider_ids`) gelten Sonderregeln:

* **Indizes**: `CREATE INDEX CONCURRENTLY` in eigener Migration mit `$withinTransaction = false`; der Guard prüft auf `INVALID`-Indizes aus abgebrochenen Läufen und räumt sie vor Neuanlage ab.
* **Spalten mit Default**: Ab PostgreSQL 11 metadata-only — erlaubt; aber `NOT NULL` auf Bestandsspalten läuft zweiphasig (Spalte nullable + `CHECK … NOT VALID`, dann `VALIDATE CONSTRAINT` — Letzteres lockt nur kurz).
* **Typänderungen** auf Großtabellen: verboten als In-Place-`ALTER`; stattdessen neue Spalte + Befüllungs-Job + Expand/Contract.
* **Partitionierte Tabellen**: neue Partitionen erzeugt der Housekeeping-Job ([core-schema](core-schema.md)), nie eine Migration; Schema-Änderungen laufen über die Elterntabelle.

## Befüllungs-Jobs (Backfills)

Datenmigrationen, die mehr als ~10k Zeilen anfassen oder externe Arbeit brauchen (Hash-Berechnung, Re-Analyse), sind keine Migrationen, sondern **Backfill-Jobs**: normale ResumableJobs, registriert in einer `backfills`-Tabelle (Name, Ziel-Version, Status, Fortschritt), gestartet vom Post-Deploy-Hook, laufend im Normalbetrieb auf ihren Queues. Der Code der Ziel-Version funktioniert **vor** Abschluss des Backfills (Expand-Phase-Disziplin: neue Logik behandelt unbefüllte Zeilen defensiv). Die Contract-Migration des Folge-Release prüft `backfills.status='completed'` als Guard und bricht andernfalls mit klarer Meldung ab — der eine Mechanismus, der verhindert, dass ein übersprungenes Release Daten verliert.

```sql
CREATE TABLE backfills (
    name          TEXT PRIMARY KEY,             -- 'files-quick-hash-v2'
    target_release TEXT NOT NULL,
    status        TEXT NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending','running','completed','failed')),
    done          BIGINT NOT NULL DEFAULT 0,
    total         BIGINT,
    started_at    TIMESTAMPTZ,
    completed_at  TIMESTAMPTZ
);
```

## Release- und Upgrade-Ablauf

Der Compose-Upgrade-Pfad ([deployment](../architecture/deployment.md)) führt Migrationen im App-Container-Start aus (`migrate --force` hinter einem Redis-Lock gegen parallele Container-Starts). Reihenfolge je Release: (1) Pre-Flight — Migrations-Dry-Run-Prüfungen (Postgres-Version, freier Speicher ≥ 2× größte zu kopierende Tabelle bei Expand-Kopien, ausstehende Pflicht-Backfills); scheitert der Pre-Flight, startet die **alte** App-Version gar nicht erst neu (Container bricht ab, das alte Image läuft weiter — kein „halb migriert, App tot"). (2) Migrationen. (3) App-Start. (4) Post-Deploy: Backfill-Registrierung, Cache-Invalidierung. **Versionssprünge**: Upgrades über mehr als eine Minor-Version sind unterstützt (Migrationskette), Sprünge über eine Major-Version verlangen den dokumentierten Zwischenschritt (der Contract-Guard erzwingt es faktisch).

## Entwicklungs-Workflow

Migrationen entstehen pro Modul in `database/migrations/` mit Zeitstempel-Präfix; Modulzugehörigkeit im Namen (`2026_07_06_120000_disc_engine_create_disc_images.php`). CI erzwingt: frische Datenbank migriert von Null (`migrate:fresh` + Seeder) **und** die Vorversion migriert auf HEAD (Upgrade-Pfad-Test gegen einen Datenbank-Snapshot mit realistischem Testbestand — der Test, der Expand/Contract-Verstöße fängt). Squashing (`schema:dump`) ist erlaubt, sobald die gedumpte Basis älter als die älteste unterstützte Upgrade-Quelle ist; der Dump ersetzt dann die Migrationsvorgeschichte.

## Edge Cases

* **Abbruch mitten in `CONCURRENTLY`**: INVALID-Index bleibt zurück; Guard räumt beim nächsten Lauf (siehe oben).
* **Divergierende Installationen** (Admin hat manuell am Schema geschraubt): Pre-Flight vergleicht einen Schema-Fingerprint (Hash über `information_schema`-Auszug der MediaForge-Tabellen) mit dem erwarteten; Abweichung ⇒ Abbruch mit Diff-Ausgabe statt Migrationsruine auf unbekanntem Fundament.
* **Zu wenig Platz für Expand-Kopie**: Pre-Flight-Prüfung (oben); die Meldung nennt den Bedarf konkret.
* **Backfill dauert Wochen** (Hash über 50 TB Medien): zulässig — genau dafür ist die Entkopplung; die Release-Notes markieren betroffene Features als „vollständig nach Backfill".

## Performance

Migrations-Laufzeitbudget: < 60 s harte Erwartung für den synchronen Teil jedes Releases (alles Längere ist per Definition ein Backfill). Der Upgrade-Pfad-Test in CI misst und failt bei Budget-Riss — Laufzeit-Regressionen fallen vor dem Release auf, nicht in Installationen.

## Security

`migrate --force` läuft mit der normalen App-Datenbankrolle — Migrationen brauchen DDL, aber die Rolle hat weiterhin kein `UPDATE/DELETE` auf den Audit-Tabellen (die REVOKE-Härtung gilt auch für Migrationen; Audit-Schemaänderungen nutzen gezielte `GRANT`-Fenster in der jeweiligen Migration mit sofortigem Re-REVOKE). Schema-Fingerprint-Ausgaben (Diffs) enthalten Struktur, nie Daten.

## Tests

Der Upgrade-Pfad-Test (Vorversion + Bestand → HEAD) ist der wichtigste; dazu: Fresh-Migrate, Guard-Idempotenz (jede Migration doppelt ausführen), Contract-Guard-Verhalten (unfertiger Backfill blockiert), Schema-Fingerprint-Stabilität (Fingerprint ändert sich genau dann, wenn Migrationen es tun).

## ADR-Verweise

Operationalisiert [ADR-0001](../adr/0001-technology-stack.md) (Postgres-Exklusivfeatures erlaubt ⇒ Migrationsdisziplin nötig) und die Upgrade-Versprechen des Deployments.

## Offene Punkte

* **Unterstützte Upgrade-Quellspanne** (wie alt darf eine Installation für Direkt-Upgrade sein): Betriebsentscheidung, Vorschlag „letzte 6 Minor-Releases", final mit dem ersten Release.
* **pgvector-Index-Migrationen** (HNSW-Parameter-Änderungen erzwingen Neubau): mit dem Such-Modul konkretisieren.
