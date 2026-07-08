# Backup und Restore

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [architecture/deployment.md](../architecture/deployment.md) (Volume-Sicherungsklassen), [database/migrations.md](../database/migrations.md) (Restore als einziger Downgrade-Pfad), [modules/audit.md](audit.md).

## Motivation

MediaForge akkumuliert unersetzliche **Arbeit**: bestätigte Disc-Mappings, kuratierte Kapitelstrukturen, Jahre an Watch-Historie, gepflegte Beziehungen, Regeln, Connector-Konfiguration. Die Medien selbst sichert der Betreiber anderweitig (NAS-Ebene — bewusst außerhalb des MediaForge-Auftrags); MediaForge muss seine lokalen Enhancement-, Audit- und Konfigurationsdaten sichern und — der eigentliche Prüfstein — **beweisbar wiederherstellen** können. Ein Backup, dessen Restore nie geprobt wurde, ist Hoffnung mit Zeitstempel; das Modul behandelt Restore-Proben deshalb als Feature erster Klasse.

## Problemstellung

**Konsistenz über drei Speicher.** Datenbank (Referenzbestand), Artefakte (teuer rekonstruierbar), Konfiguration (`.env`, Compose) müssen als zueinander passender Satz gesichert werden — ein DB-Dump von heute mit Artefakt-Stand von letzter Woche erzeugt Signatur-Waisen in beide Richtungen. Redis ist per Fundament-Definition verzichtbar (kein Backup-Gegenstand).

**Laufender Betrieb.** Backups dürfen weder Downtime verlangen noch mit laufenden Jobs kollidieren (ein Artefakt mitten im `.partial`-Zustand gehört nicht ins Backup).

**Restore-Realitäten.** Der Ernstfall hat Varianten mit verschiedenen Abläufen: Totalverlust (neuer Host), Datenbank-Korruption (DB-only-Restore auf bestehendem Stack), Bedienfehler (gezielte Rücknahme — wofür primär Audit + Soft-Deletes zuständig sind, das Backup ist die letzte Instanz), Downgrade nach fehlgeschlagenem Update.

## Analyse bestehender Lösungen

**pgBackRest/wal-g**: Stand der Technik für Postgres (inkrementell, PITR) — bewusst **nicht** eingebaut: Für die Zielgruppe ist ein konsistenter logischer Dump pro Nacht das richtige Komplexitätsniveau; PITR-Bedarf (Minuten-RPO) hat ein Heimkatalog nicht. Die Doku verweist Profis auf pgBackRest als Eigenbetrieb-Option neben dem MediaForge-Backup. ***arr-Backups**: ZIP aus DB+Config per Klick und Scheduler — das UX-Vorbild (eingebaut, sichtbar, trivial zurückspielbar). **Immich**: dokumentiert Postgres-Dump + Asset-Ordner getrennt — bestätigt die Trennung der Sicherungsklassen. **restic/borg**: exzellent für die Artefakt-/Off-Host-Ebene; MediaForge erzeugt restic-freundliche Strukturen statt eigene Dedup-Stores zu erfinden.

## Architekturentscheidung

**Drei Sicherungsgegenstände, ein Orchestrierungs-Job, ein Manifest:**

1. **Datenbank**: `pg_dump` (custom format, komprimiert) aus dem Postgres-Container — logisch, versionsrobust, selektiv restaurierbar. Vor dem Dump: kurze Quiesce-Phase (neue Jobs der Klassen `assemble`/`ai` pausieren, laufende laufen weiter — der Dump ist ohnehin transaktionskonsistent (Snapshot), die Pause verhindert nur, dass frisch registrierte Artefakte knapp nach dem DB-Snapshot entstehen und die Manifest-Prüfung Rauschen meldet).
2. **Artefakte**: kein Kopieren durch MediaForge (potenziell hunderte GB) — stattdessen erzeugt der Job ein **Artefakt-Inventar** (Pfade, Größen, Checksums, Signaturen aus der `artifacts`-Tabelle) im Backup-Satz; die eigentliche Dateisicherung übernimmt das Betreiber-Werkzeug (restic/rsync/NAS-Snapshot) über das ro-lesbare `artifacts`-Volume. Die Restore-Prüfung gleicht Inventar gegen Bestand ab und meldet Lücken konkret (rekonstruierbar via Signatur: die Build-Jobs können fehlende Artefakte neu erzeugen — teuer, aber deterministisch).
3. **Konfiguration**: `.env` (verschlüsselt, s. u.), Compose-Overrides, Settings-Export (Settings liegen zwar in der DB, der separate Export macht den Satz menschenlesbar prüfbar).

Der **Backup-Satz** ist ein Verzeichnis `backup-<timestamp>/` mit `manifest.json` (Bestandteile, Checksums, Schema-Version, App-Version, Zähler-Kennzahlen: Items, Mappings, Watch-States — die Plausibilitätsanker der Restore-Probe), `db.dump`, `config.tar.enc`, `artifacts-inventory.json.gz`. Ablage auf ein konfigurierbares Ziel-Volume (`/backups`, ausdrücklich ≠ `pgdata`-Platte; der Setup-Check warnt bei gleichem Filesystem); Rotation: konfigurierbar (Default: 7 täglich, 4 wöchentlich, 6 monatlich).

**Verschlüsselung**: Der Config-Anteil enthält Secrets ⇒ immer verschlüsselt (age/AES-GCM mit Backup-Passphrase, die **nicht** im System liegt — der Betreiber verwahrt sie; ohne sie ist ein Restore der Secrets unmöglich, was die Doku unmissverständlich sagt). DB-Dump-Verschlüsselung optional (Default an), gleiche Passphrase.

**Restore-Probe** (das Alleinstellungsmerkmal): `php artisan mediaforge:restore --verify <satz>` spielt den Dump in eine **temporäre Datenbank** (eigener Schema-Namespace bzw. Wegwerf-DB im selben Cluster), prüft Migrations-Kompatibilität, vergleicht die Manifest-Kennzahlen gegen den eingespielten Bestand und verwirft — ohne den Betrieb zu berühren. Der Health-Check „letzte erfolgreiche Restore-Probe < 30 Tage" ([health-monitoring](health-monitoring.md)) macht ungeprobte Backups zum sichtbaren Befund.

## Alternativen

PITR/pgBackRest eingebaut (Komplexität über Zielgruppe, s. o.); Artefakte selbst kopieren (Speicher-/Zeitkosten, Doppelung zu restic-Klasse); Backup in die Artefakt-Ablage (Selbstbezug — ein Volume-Verlust nähme beide); DB-Dump via `pg_dumpall` (Cluster-weite Dumps mischen Zuständigkeiten; custom-format pro DB ist selektiver).

## Datenmodell und SQL-Schema

```sql
CREATE TABLE backup_runs (
    id             CHAR(26) PRIMARY KEY,
    kind           TEXT        NOT NULL CHECK (kind IN ('scheduled','manual','pre_upgrade')),
    status         TEXT        NOT NULL DEFAULT 'running'
        CHECK (status IN ('running','succeeded','failed','pruned')),
    target_path    TEXT        NOT NULL,
    manifest       JSONB,                          -- Kopie des manifest.json (Kennzahlen, Checksums)
    size_bytes     BIGINT,
    error_detail   TEXT,
    started_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    finished_at    TIMESTAMPTZ
);

CREATE TABLE restore_probes (
    id             CHAR(26) PRIMARY KEY,
    backup_run_id  CHAR(26)    REFERENCES backup_runs(id) ON DELETE SET NULL,
    outcome        TEXT        NOT NULL CHECK (outcome IN ('passed','failed')),
    report         JSONB       NOT NULL,           -- Kennzahlen-Vergleich, Dauer, Warnungen
    probed_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

`pre_upgrade`-Backups erzeugt der Upgrade-Pre-Flight ([migrations](../database/migrations.md)) automatisch — das Migrations-Kapitel-Versprechen „Backup ist Teil des Upgrade-Pfads" wird hier eingelöst, nicht dem Betreiber überlassen.

## Laravel-Klassen

| Klasse | Typ | Vertrag |
|---|---|---|
| `RunBackupJob` | ResumableJob (Scheduler, Queue `default`) | Schritte `quiesce`, `dump`, `config`, `inventory`, `manifest`, `unquiesce`, `rotate` |
| `BackupTargetInterface` | Interface | lokales Verzeichnis als Default-Treiber; S3/WebDAV als Erweiterung (Plugin-fähig) |
| `RestoreProbeJob` | Job (Scheduler, monatlich Default) | Wegwerf-Restore + Kennzahlen-Report |
| `mediaforge:backup`, `mediaforge:restore [--verify|--execute]` | Artisan | CLI-Pfade; `--execute` verlangt Bestätigungs-Token und gestoppte Worker (geführter Ablauf mit Checkliste) |
| `PruneBackupsJob` | Job | Rotationsschema; nie das letzte erfolgreiche Backup löschen (harte Regel) |

Der echte Restore (`--execute`) ist bewusst CLI-only, nie Web-UI: Wer restauriert, hat Konsolenzugriff und Ruhe — ein „Restore"-Button neben „Backup" ist eine Einladung zum Desaster-Klick.

## API und UI

`GET /api/v1/backups` (Läufe + Proben, admin), `POST /api/v1/backups` (manueller Lauf). UI im [Admin-Dashboard](admin-dashboard.md): Backup-Karte (letzter Satz, Größe, Alter, letzte Probe mit Ampel), Rotations-/Ziel-Settings, Passphrase-Einrichtung (mit dem unmissverständlichen Hinweis zur Verwahrung), manueller Lauf. Restore-Anleitung als Doku-Link, absichtlich ohne Button (s. o.).

## Edge Cases

* **Backup-Ziel voll**: Fachfehler mit Health-Befund; der Lauf räumt seinen Teilsatz ab (kein korruptes Halb-Backup im Rotationsbestand — Sätze werden atomar via Verzeichnis-Rename gültig).
* **Restore auf neuere App-Version**: Dump älterer Schema-Version + Migrationskette = unterstützt (der normale Fall nach Totalverlust: neueste Version installieren, alten Dump einspielen, Migrationen laufen); Restore auf **ältere** Version als der Dump: abgelehnt mit klarer Meldung (Schema-Version im Manifest).
* **Passphrase verloren**: DB-Restore möglich (falls Dump unverschlüsselt konfiguriert war), Secrets nicht — Connectoren/Tokens müssen neu eingerichtet werden; der Restore-Ablauf hat dafür einen dokumentierten „Secrets neu"-Pfad (Instanzen bleiben, Secret-Neueingabe je Instanz).
* **Artefakt-Lücken nach Restore**: Inventar-Abgleich listet sie; ein Rebuild-Workflow (Signatur-basiert, [Workflow Engine](workflow-engine.md)) arbeitet sie ab — Priorisierung nach Zugriffshäufigkeit.
* **Backup während großem Import**: zulässig (Dump ist snapshot-konsistent); das Manifest vermerkt laufende Batches als Kontext für spätere Plausibilitätsfragen.

## Performance

`pg_dump` der Referenzgröße (~10 GB DB): Minuten, nachts, ohne Betriebsstörung (Snapshot-Isolation); Kompression im Dump-Format. Inventar-Erzeugung ist ein Tabellen-Export (Sekunden). Restore-Probe: teuerster Schritt ist das Einspielen (~Minuten) — monatlich vertretbar, konfigurierbar.

## Security

Config-Anteil immer verschlüsselt; Backup-Ziel-Zugriff ist Betriebssystem-Sache (die Doku fordert dedizierte Rechte); Backups enthalten das volle Audit-Log und restriktive Kataloginhalte — die Backup-Datei ist so schutzwürdig wie die Datenbank selbst (Doku-Kernsatz). `backup_runs.manifest` enthält Kennzahlen, nie Inhalte. Die Restore-Probe läuft mit denselben REVOKE-Härtungen (Audit bleibt auch in der Wegwerf-DB append-only — Proben sind kein Umgehungspfad).

## Tests

End-to-End im CI: Backup eines Fixture-Bestands → Restore-Probe → Kennzahlen-Gleichheit; Verschlüsselungs-Roundtrip; Rotations-Regeln (inkl. „letztes Backup nie löschen"); Atomaritäts-Test (Abbruch mitten im Lauf hinterlässt keinen gültigen Teilsatz); Versions-Matrix (Dump N-1 → HEAD via Migrationskette — teilt Fixtures mit dem Upgrade-Pfad-Test der Migrationen).

## ADR-Verweise

Sicherungsklassen aus [architecture/deployment.md](../architecture/deployment.md); „Downgrade = Restore" aus [database/migrations.md](../database/migrations.md); Redis-Verzichtbarkeit aus [ADR-0001](../adr/0001-technology-stack.md)-Kontext.

## Offene Punkte

* **Off-Host-Ziele eingebaut** (S3/WebDAV-Treiber): Interface vorhanden, Treiber-Priorität nach Nutzerfeedback.
* **Selektiver Restore** (nur Watch-States, nur eine Bibliothek): mit custom-format-Dumps technisch möglich; Ablauf-Spezifikation vertagt (Komplexität vs. Ernstfall-Nutzen).
* **Audit-Log-Export** ([audit](audit.md), offener Punkt): der Backup-Satz deckt Aufbewahrung ab; ein getrennter revisionsfähiger Export bleibt offen.
