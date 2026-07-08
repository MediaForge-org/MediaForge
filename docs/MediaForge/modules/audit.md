# Audit-System

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [architecture/overview.md](../architecture/overview.md) (Action-Basisvertrag), [database/core-schema.md](../database/core-schema.md) (Schema-Konventionen). Das Audit-System ist Querschnittsfundament: Es wird vor allen Fachmodulen spezifiziert, weil jede Action jedes Moduls es benutzt.

## Motivation

MediaForge verändert Zustand aus vier grundverschiedenen Quellen: Menschen im UI, geplante Jobs, Connectoren fremder Systeme und KI-Modelle. In einem solchen System ist die Frage „Warum steht dieser Wert hier?" ohne Audit-Trail unbeantwortbar — und sie stellt sich täglich: Warum ist diese Episode als gesehen markiert (habe ich das getan, oder hat Jellyfin das synchronisiert)? Wer hat den Titel geändert (Benutzerkorrektur oder Enrichment-Lauf)? Welches Modell hat diese Kapitelvorschläge erzeugt, mit welchen Parametern? Architekturregel 6 („Jede Änderung ist auditierbar") ist deshalb keine Compliance-Übung, sondern Betriebsvoraussetzung: Konfliktauflösung der Connectoren, Rücknahme fehlerhafter Massenoperationen und das Vertrauen in KI-Beiträge (Regel 5) stehen alle auf dem Audit-Trail.

## Problemstellung

Vier Anforderungen stehen in Spannung: **Vollständigkeit** (jede fachliche Änderung, aus jeder Quelle), **Atomarität** (Audit-Eintrag und Fachänderung committen zusammen oder gar nicht — ein Audit-Trail mit Lücken bei Crashes ist wertlos), **Erträglichkeit** (Playback-Progress-Events kommen im Sekundentakt; naive Voll-Auditierung würde die Audit-Tabelle zur größten des Systems machen) und **Abfragbarkeit** (die Fragen von oben müssen ohne forensische Handarbeit beantwortbar sein). Zusätzlich braucht das System eine einheitliche Antwort auf die Actor-Frage: Wer ist „der Verursacher", wenn ein Scheduler einen Job startet, der einen Connector-Sync auslöst, der eine Action aufruft?

## Analyse bestehender Lösungen

Die Referenzprojekte sind hier durchgehend schwach — was die Entscheidung, Audit als Fundament zu bauen, gerade begründet. **Jellyfin** hat ein Activity-Log (grobe Ereignisse, keine Diffs, keine Kausalität). **Sonarr/Radarr** führen eine `history`-Tabelle pro Medium (brauchbar für Beschaffungsvorgänge, aber nicht generisch und ohne Vorher/Nachher-Werte). **Audiobookshelf** loggt serverseitig, aber nicht abfragbar strukturiert. **Immich** auditiert Löschungen für Sync-Zwecke, nicht allgemein. Aus dem Laravel-Ökosystem sind `spatie/laravel-activitylog` und `owen-it/laravel-auditing` etabliert: Beide hängen sich an Eloquent-Model-Events. Genau das ist für MediaForge die falsche Ebene — Model-Events feuern pro Zeile, nicht pro fachlicher Operation; ein Massen-Sync, der 500 Watch-States setzt, wäre 500 zusammenhanglose Einträge statt eine Operation mit 500 Positionen; Upserts und Bulk-Queries umgehen Model-Events ganz. MediaForge auditiert deshalb auf **Action-Ebene**, nicht auf Model-Ebene.

## Architekturentscheidung

Das Audit-System besteht aus drei Teilen:

1. **Audit-Log als append-only-Tabellenpaar** (`audit_operations` + `audit_entries`): Eine Operation entspricht einem Action-Aufruf (fachlicher Vorgang, Actor, Kausalkette); ihre Entries sind die betroffenen Entitäten mit Feld-Diffs. Damit ist die Massenoperation ein Vorgang mit N Positionen — abfragbar in beide Richtungen („was tat dieser Sync?" und „wer änderte dieses Item?").
2. **Actor-Auflösung als Kontext-Stack**: Ein prozessweiter `ActorContext` wird an den Systemgrenzen gesetzt (HTTP-Middleware: User; Job-Middleware: Job-Identität samt auslösender Kausalität; Connector-Sync: Connector-Identität; AI-Aufrufe: Modell-Identität) und von `Actor::current()` gelesen. Verschachtelung wird als Kausalkette gespeichert (`caused_by`), nicht plattgedrückt: Ein von einem Connector-Sync gestarteter Job behält beide Identitäten.
3. **Integration über die Action-Basisklasse**: `AuditableAction::transact()` ([architecture/overview.md](../architecture/overview.md)) schreibt Operation und Entries in derselben Transaktion wie die Fachänderung. Es gibt keinen zweiten Weg: Wer Zustand ohne Action schreibt, umgeht das Audit — und genau das verbieten die Architektur-Tests.

Der Erträglichkeit dient eine bewusste Grenzziehung: **Hochfrequente Zustandsfortschreibung (Playback-Progress) wird nicht voll auditiert.** `watch_state_events` ([database/core-schema.md](../database/core-schema.md)) ist bereits die append-only-Historie dieser Domäne; das Audit-Log verzeichnet nur Zustands-**Übergänge** (watched/unwatched/reset) und Verwaltungsoperationen. Die Regel dahinter ist generisch und verbindlich: Domänen mit eigener append-only-Historie auditieren Übergänge, nicht Fortschreibungen — die Historie ist dann Teil des Audit-Konzepts, nicht seine Umgehung.

## Alternativen

**Model-Event-basierte Pakete** (Spatie/Owen-IT): verworfen, Begründung oben (falsche Granularität, Bulk-Lücken). **Datenbank-Trigger-Audit** (generische Row-Level-Trigger, `hstore`-Diffs): lückenlos auch bei Bulk-SQL, aber ohne fachlichen Kontext (kein Actor, keine Operation, keine Kausalität) und mit erheblicher Migrationslast; als ergänzendes Sicherheitsnetz erwogen und für Version 1 verworfen — die Architektur-Tests gegen Schreibpfade an Actions vorbei decken das Risiko billiger ab. **Event Sourcing** (Zustand als Event-Strom, Audit gratis): architektonisch verführerisch, aber ein Paradigmenwechsel, der jedes Modul verteuert und das Team-Risiko des Gesamtprojekts dominiert hätte; verworfen per [ADR-0006](../adr/0006-action-level-audit.md). **Externes Log (Loki/ELK)**: Logs sind Betriebsdiagnostik, kein transaktionaler Persistenzspeicher für fachliche Änderungen; Audit gehört transaktional zur Fachänderung.

## Datenmodell

Eine **Operation** ist ein fachlicher Vorgang: Action-Klasse, Actor, Kausalität, Zeitpunkt, zusammenfassende Beschreibung. Ein **Entry** ist die Wirkung auf genau eine Entität: Referenz (Morph-Typ + ULID), Änderungsart (created/updated/deleted/restored/state_changed) und Feld-Diff als Vorher/Nachher-Paare. Diffs enthalten nur geänderte Felder; sensible Felder (`password_hash`) sind per Denyliste von Diffs ausgeschlossen (der Entry verzeichnet die Änderung, aber Werte als `"[redacted]"`). Operationen ohne Entries sind zulässig (fehlgeschlagene, aber auditwürdige Versuche — z. B. abgelehnte Connector-Schreibversuche auf Container-Watch-State).

## SQL-Schema

```sql
CREATE TABLE audit_operations (
    id             CHAR(26) PRIMARY KEY,
    action_class   TEXT        NOT NULL,        -- FQCN der Action
    actor_type     TEXT        NOT NULL
        CHECK (actor_type IN ('user','job','connector','ai','system','cli')),
    actor_id       TEXT        NOT NULL,        -- User-ULID | Job-Klasse | Connector-Kennung | Modell@Version
    caused_by      CHAR(26)    REFERENCES audit_operations(id) ON DELETE SET NULL,
    correlation_id CHAR(26)    NOT NULL,        -- konstant über eine Kausalkette (Scan → Analyse → Mapping)
    summary        TEXT        NOT NULL,        -- menschenlesbar: "Episode-Mapping bestätigt (6 Playlists)"
    context        JSONB       NOT NULL DEFAULT '{}',   -- IP/UA bei Usern, Sync-Cursor bei Connectoren, …
    occurred_at    TIMESTAMPTZ NOT NULL DEFAULT now()
) PARTITION BY RANGE (occurred_at);

CREATE INDEX audit_operations_actor_idx ON audit_operations (actor_type, actor_id, occurred_at DESC);
CREATE INDEX audit_operations_corr_idx  ON audit_operations (correlation_id);

CREATE TABLE audit_entries (
    id            CHAR(26) PRIMARY KEY,
    operation_id  CHAR(26)    NOT NULL,          -- FK auf audit_operations; Partition-lokal erzwungen
    subject_type  TEXT        NOT NULL,
    subject_id    CHAR(26)    NOT NULL,
    change_kind   TEXT        NOT NULL
        CHECK (change_kind IN ('created','updated','deleted','restored','state_changed')),
    diff          JSONB       NOT NULL DEFAULT '{}',   -- {"title": {"old": "...", "new": "..."}}
    occurred_at   TIMESTAMPTZ NOT NULL DEFAULT now()
) PARTITION BY RANGE (occurred_at);

CREATE INDEX audit_entries_subject_idx ON audit_entries (subject_type, subject_id, occurred_at DESC);
CREATE INDEX audit_entries_operation_idx ON audit_entries (operation_id);
```

Beide Tabellen sind monatlich partitioniert (Retention per `DROP PARTITION`; Default: 36 Monate, konfigurierbar, aber nie unter 12 — die Konfliktauflösung der Connectoren referenziert bis zu zwölf Monate zurück). Append-only wird technisch erzwungen: Der Postgres-Anwendungsrolle werden `UPDATE`/`DELETE` auf beiden Tabellen per `REVOKE` entzogen; Eloquent-seitig werfen die Models bei Update-Versuchen. `diff` ist legitimes JSONB nach den Regel-8-Kriterien: nie gejoint, nie teilaktualisiert, reine Anzeige- und Diagnose-Struktur. Der FK von `audit_entries.operation_id` ist als `NOT VALID`-freier logischer Verweis dokumentiert, weil Postgres FKs zwischen unabhängig partitionierten Tabellen nicht direkt unterstützt; die Actions schreiben beide Seiten in einer Transaktion, ein Waisen-Check läuft im Datenqualitätsmodul mit.

## Laravel-Klassen

```php
namespace App\Core\Audit;

interface AuditRecorder
{
    /** Beginnt eine Operation im aktuellen Actor-Kontext; gibt Operation-ULID zurück. */
    public function begin(string $actionClass, string $summary, array $context = []): string;

    /** Verzeichnet eine Entitätswirkung innerhalb der laufenden Operation. */
    public function entry(Model $subject, ChangeKind $kind, AuditDiff $diff): void;

    /** Kurzform für Einzel-Entity-Operationen (der 90%-Fall aus AuditableAction). */
    public function record(Model $subject, AuditChange $change, Actor $actor): void;
}

final readonly class Actor
{
    public function __construct(
        public ActorType $type,       // enum: User|Job|Connector|Ai|System|Cli
        public string $id,
        public ?string $causedByOperationId,
        public string $correlationId,
    ) {}

    public static function current(): self;   // liest den ActorContext-Stack
}
```

Der `ActorContext` wird an den Systemgrenzen gesetzt und via Job-Payload propagiert: Wenn eine Action einen Job dispatcht, serialisiert eine Dispatch-Middleware `correlation_id` und die aktuelle Operation-ID als `caused_by` in die Job-Daten; die Job-Middleware stellt beim Ausführen den Kontext wieder her. Dadurch ist die Kausalkette Scan → Analyse → Mapping → Review über Prozessgrenzen hinweg eine zusammenhängende `correlation_id` — die wichtigste Diagnose-Abkürzung des Betriebs.

Diff-Erzeugung: `AuditDiff::fromModel(Model $m)` liest `getChanges()`/`getOriginal()` nach dem Speichern innerhalb der Transaktion, filtert die Denyliste und `updated_at`. Für Nicht-Eloquent-Wirkungen (Artefakt-Dateien, externe Systeme) gibt es `AuditDiff::external(array $facts)` — der Diff beschreibt dann die Außenwirkung („M4B erzeugt: Pfad, Größe, Checksum").

## API-Endpunkte

Lesend, `manager`-Rolle (eigene Watch-State-Historie: jeder Benutzer für sich):

| Route | Zweck |
|---|---|
| `GET /api/v1/audit/operations?actor_type=&actor_id=&from=&to=&q=` | Operationsliste, paginiert, Volltext über `summary` |
| `GET /api/v1/audit/operations/{ulid}` | Operation mit allen Entries und Kausalkette (caused_by-Kette + gleiche correlation_id) |
| `GET /api/v1/audit/subjects/{type}/{ulid}` | vollständige Änderungshistorie einer Entität |

Schreibende Audit-APIs existieren nicht — das Audit-Log ist ausschließlich Nebenwirkung von Actions.

## Vue-/Inertia-Komponenten

Zwei UI-Bausteine, beide wiederverwendet statt pro Modul neu gebaut: **`AuditTimeline`** (Props: `subjectType`, `subjectId`) zeigt die Historie einer Entität als Zeitleiste mit Actor-Badges (User-Avatar, Job-Zahnrad, Connector-Logo, AI-Kennzeichnung samt Modell@Version — die dauerhafte Sichtbarkeit der KI-Herkunft aus Regel 5 endet nicht in der Datenbank, sie muss im UI ankommen) und aufklappbaren Feld-Diffs. Eingebettet in jede Detailseite (Medium, Disc, Hörbuch, Einstellungen). **`AuditExplorer`** (Admin-Bereich) ist die Recherche-Sicht: Filter nach Actor, Zeitraum, Action-Klasse, Freitext; Sprung von jeder Operation zur Kausalkette und zu den Subjekten.

## UI-Flows

Der Kernflow ist die **Rückverfolgung**: Benutzer sieht verdächtigen Zustand (Episode gesehen, war sie aber nicht) → öffnet `AuditTimeline` auf der Episode → sieht `state_changed` durch `connector:jellyfin` gestern 23:12, `caused_by` Sync-Operation X → öffnet X im `AuditExplorer` → sieht 214 Entries desselben Syncs → erkennt Fehlkonfiguration (falscher Jellyfin-User gemappt). Der Flow von der Beobachtung zur Ursache braucht drei Klicks und keine Logs.

## Edge Cases

* **Crash zwischen Fachänderung und Audit**: unmöglich per Konstruktion — dieselbe Transaktion.
* **Actions, die Actions aufrufen**: Die innere Action erzeugt eine eigene Operation mit `caused_by` auf die äußere; keine Vermischung der Entries.
* **Job-Retry**: Jeder Versuch, der Zustand schreibt, ist eine eigene Operation; idempotente Wiederholungen ohne Änderung erzeugen mangels Diff keine Entries (Operation mit leerer Wirkung wird unterdrückt, konfigurierbar für Diagnose).
* **Riesenoperationen** (Erstimport, 300k Entries): Entries werden gebatcht eingefügt (Chunked Insert, 1000er-Blöcke) innerhalb der Schritt-Transaktionen des ResumableJob — eine Operation pro Job-Schritt, verkettet über `correlation_id`, statt einer Monster-Transaktion.
* **Zeit**: `occurred_at` ist Transaktionszeit der Wirkung; für nachgelieferte Connector-Ereignisse steht die Fachzeit im Kontext der Domäne (`watch_state_events.occurred_at`), nicht im Audit — das Audit dokumentiert, wann MediaForge etwas tat, nicht wann es in der Welt geschah.

## Performance

Schreiblast: zwei Inserts pro auditierter Operation plus ein Insert pro Subjekt — vernachlässigbar gegen die Fachtransaktion, solange die Progress-Ausnahme gilt (siehe Architekturentscheidung). Die Partitionierung hält Indizes klein; Abfragen sind zeitlich eingrenzbar und treffen Partition-Pruning. Für den `AuditExplorer`-Volltext genügt ein Trigram-Index auf `summary` in den jüngsten Partitionen (Index-Anlage pro Partition, Teil des Partitions-Housekeeping-Jobs).

## Security

Das Audit-Log ist selbst schützenswert: Es enthält Verhaltensdaten aller Benutzer. Zugriff auf fremde Historien erfordert `manager`; `member` sieht nur eigene Operationen und die Historien von Entitäten ohne Personenbezug. Die `REVOKE`-Härtung (kein UPDATE/DELETE für die App-Rolle) schützt auch gegen Anwendungsfehler. Backups des Audit-Logs unterliegen derselben Retention wie die Live-Daten ([modules/backup-restore.md](backup-restore.md), geplant). `context` darf keine Secrets enthalten (kein Token, kein Passwort) — der Recorder filtert eine Denyliste von Schlüsselmustern (`*token*`, `*secret*`, `*password*`).

## Tests

Constraint- und Härtungstests (UPDATE auf `audit_entries` muss als DB-Fehler scheitern); Atomaritätstest (Action wirft nach Fachänderung ⇒ weder Fachänderung noch Audit persistiert); Actor-Propagation über Dispatch-Grenzen (Action dispatcht Job, Job-Operation trägt `caused_by` und gleiche `correlation_id`); Diff-Denyliste (`password_hash` erscheint nur redigiert); Batch-Verhalten der Riesenoperation (Entry-Zahl, Operation-pro-Schritt); Unterdrückung wirkungsloser Operationen.

## ADR-Verweise

[ADR-0006](../adr/0006-action-level-audit.md) (Audit auf Action-Ebene, gegen Model-Events und Event Sourcing). Setzt um: Architekturregeln 5 und 6.

## Offene Punkte

* Export des Audit-Logs (CSV/JSONL für externe Aufbewahrung) ist gewünscht, aber unspezifiziert; folgt mit dem Backup-Kapitel.
* Ob wirkungslose Operationen standardmäßig unterdrückt oder verzeichnet werden, soll nach ersten Betriebserfahrungen entschieden werden; bis dahin: unterdrückt, per Setting umschaltbar.
* Eine Sampling-Strategie, falls sich die Progress-Ausnahme als zu grob erweist (z. B. ein Audit-Eintrag pro Session statt pro Übergang), ist skizziert, aber nicht spezifiziert.
