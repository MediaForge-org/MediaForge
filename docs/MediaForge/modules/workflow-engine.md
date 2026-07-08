# Workflow Engine

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [architecture/overview.md](../architecture/overview.md) (Jobs, Events), [database/core-schema.md](../database/core-schema.md), [modules/audit.md](audit.md). Verwandt: [Rule Engine](rule-engine.md) (Regeln starten Workflows; die Abgrenzung ist unten normativ).

**Vertiefung**: [Workflow-Definitions-Katalog](workflow-engine/definitions-catalog.md) (alle Definitionen, Konkurrenz-Policies, Versions-Migrationsregel)

## Motivation

Einzelne Jobs erledigen einzelne Arbeiten; reale Vorgänge sind Ketten: „Neues Hörbuch: sequenzieren → Quellen sammeln → Kapitel wählen → CUE bauen → ABS-Export → ABS-Scan anstoßen". Heute existieren solche Ketten implizit als Event-Kaskaden — funktional, aber unsichtbar: Niemand sieht, wo Vorgang X steht, was fehlschlug, was auf ein Review wartet. Die Workflow Engine macht mehrstufige Vorgänge zu **erstklassigen, beobachtbaren, wiederaufnehmbaren Objekten** mit definierten Schritten, Zuständen und Wartepunkten (inkl. „wartet auf Mensch" — Reviews als Workflow-Schritt). Das Desired-State-Vorbild der *arr-Familie (Masterdatei-Referenzanalyse) liefert das Muster: gewünschtes Ergebnis deklarieren, Differenz abarbeiten, Zustand zeigen.

## Problemstellung

**Orchestrierung vs. Choreografie.** Event-Kaskaden (Choreografie) skalieren organisatorisch schlecht: Der Gesamtvorgang steht nirgends, Fehlerbehandlung ist verstreut, „nochmal ab Schritt 3" ist unmöglich. Zentrale Orchestrierung droht dafür zum Gott-Modul zu werden, das Fachlogik an sich zieht. Die Grenze muss scharf sein: Die Engine kennt **Ablauf**, nie **Inhalt** — Schritte delegieren an die Actions/Jobs der Fachmodule und interpretieren nur deren Ergebnis-Signale.

**Langlebigkeit.** Workflows leben Tage (Review-Wartepunkte) bis Wochen (Batch über 2.000 Hörbücher). Sie überleben Deployments, Neustarts und Fehlerserien — der Zustand gehört nach PostgreSQL, die Ausführung in idempotente Schritte (Fundament-Konventionen greifen unverändert, die Engine hebt sie nur auf Vorgangs-Ebene).

**Nebenläufigkeit und Konvergenz.** Zwei Workflows dürfen nicht dasselbe Subjekt gegenläufig bearbeiten (Assembly bauen vs. Assembly neu sequenzieren); derselbe Workflow-Typ auf demselben Subjekt darf nicht doppelt laufen; und ein Workflow, dessen Vorbedingungen zwischenzeitlich zerfallen (Datei gelöscht), muss sauber terminieren statt zu zombifizieren.

## Analyse bestehender Lösungen

**Temporal/Airflow-Klasse**: mächtig, aber ein eigener Infrastruktur-Stack (Server, Worker, DB) — für den Heimserver-Kontext disqualifiziert ([ADR-0002](../adr/0002-modular-monolith.md)-Logik). **Laravel Workflow-Pakete** (`laravel-workflow`, State-Machine-Pakete): brauchbare Bausteine, aber entweder Saga-orientiert (Code-definierte Abläufe ohne Beobachtbarkeits-Modell) oder reine Zustandsmaschinen ohne Schritt-Ausführung; die Engine übernimmt Konzepte (deterministische Schritt-Definition, Kompensation), implementiert aber eigen — die Review-Wartepunkte und die Fundament-Integration (Checkpoints, Audit, `job_progress`) sind zu MediaForge-spezifisch. ***arr-Queue/Activity-Modell**: Vorbild für die UI-Semantik (Vorgangsliste mit Status, manuelles Eingreifen pro Eintrag).

## Architekturentscheidung

**Definitionen sind Code, Instanzen sind Daten.** Ein Workflow-Typ ist eine PHP-Klasse (versioniert, getestet, im Modul des fachlichen Schwerpunkts oder in `App\Modules\WorkflowEngine\Definitions` für modulübergreifende):

```php
final class AssembleAndExportAudiobook extends WorkflowDefinition
{
    public function key(): string { return 'audiobook.assemble-and-export'; }
    public function subjectType(): string { return 'media_edition'; }

    public function steps(): array
    {
        return [
            Step::job('sequence', SequenceAudiobookJob::class)
                ->skipIf(fn (Ctx $c) => $c->assembly()?->status !== 'draft'),
            Step::waitForReview('sequence-review', taskType: 'audiobook_sequence')
                ->onlyIf(fn (Ctx $c) => $c->assembly()->sequence_confidence < 0.95),
            Step::job('collect', CollectChapterSourcesJob::class),
            Step::action('select', SelectActiveChapterSet::class)
                ->orWaitForReview(taskType: 'chapter_proposal'),
            Step::job('build-cue', BuildCueJob::class),
            Step::job('export', BuildAbsExportJob::class)
                ->onlyIf(fn (Ctx $c) => $c->setting('abs_export_enabled')),
        ];
    }

    public function concurrencyKey(Ctx $c): string { return 'assembly:'.$c->subjectId; }
}
```

Die Schritt-Primitive sind bewusst wenige: `job` (dispatcht, wartet auf Abschluss-Signal), `action` (synchron in der Engine-Transaktion), `waitForReview` (pausiert bis Review-Auflösung — die Brücke zwischen Automatik und Mensch), `onlyIf`/`skipIf` (Bedingungen über den Kontext), `parallel` (Gruppe unabhängiger Schritte, join-all), `compensate` (Aufräum-Schritt bei Abbruch, z. B. `.partial`-Artefakte verwerfen). Keine Schleifen, keine dynamische Schritt-Generierung, kein Sub-Workflow-Spawning in Version 1 — Workflows bleiben statisch analysierbar; Batch-Verarbeitung („2.000 Hörbücher") ist **eine Instanz pro Subjekt** plus eine Batch-Klammer (unten), nie ein Mega-Workflow.

**Signal-Kopplung statt Rückruf-Kopplung:** Die Engine lauscht auf die vorhandenen Fundament-Events (`AudiobookSequenced`, `ReviewTaskResolved`, Job-Outcome über `job_progress`) und schaltet Instanzen weiter. Fachmodule wissen nicht, dass Workflows existieren — null Rückwärtsabhängigkeit (Modulgrenzen-Test erweitert: `Modules/* !→ WorkflowEngine`).

**Abgrenzung zur Rule Engine (normativ):** Die Rule Engine entscheidet **ob/wann** (Bedingung über Katalogzustand ⇒ Trigger); die Workflow Engine führt **wie** aus (Schrittfolge). Regeln starten Workflows (oder Einzeljobs); Workflows evaluieren keine Bibliotheksbedingungen. Wer eine Schleife „Regel triggert Workflow, Workflow ändert Zustand, Regel triggert erneut" baut, wird von der Konvergenz-Dämpfung der Rule Engine gebremst (dort spezifiziert).

## Alternativen

Externe Engines und reine Event-Choreografie: verworfen (siehe Analyse/Motivation). **Workflows als Daten** (Admin baut Abläufe im UI zusammen): verlockend, aber Bedingungen/Kontexte sind Code-Verträge; ein UI-Baukasten entartet zur schlechten Programmiersprache ohne Tests. Kompromiss: Definitionen sind Code, aber **Parameter** (Schwellen, Ziel-Bibliotheken, Feature-Schalter) sind Settings — der Admin konfiguriert, der Entwickler definiert. **Sub-Workflows/dynamische Graphen**: auf später vertagt; die dokumentierten Anwendungsfälle kommen mit statischen Ketten aus.

## Datenmodell und SQL-Schema

```sql
CREATE TABLE workflow_instances (
    id              CHAR(26) PRIMARY KEY,
    definition_key  TEXT        NOT NULL,          -- 'audiobook.assemble-and-export'
    definition_version INTEGER  NOT NULL,          -- Klassen-Version beim Start (eingefroren)
    subject_type    TEXT        NOT NULL,
    subject_id      CHAR(26)    NOT NULL,
    status          TEXT        NOT NULL DEFAULT 'running'
        CHECK (status IN ('running','waiting_review','waiting_job','succeeded',
                          'failed','cancelled','superseded')),
    current_step    TEXT,
    context         JSONB       NOT NULL DEFAULT '{}',   -- Schritt-Ergebnisse für Folgebedingungen
    concurrency_key TEXT        NOT NULL,
    batch_id        CHAR(26),                            -- Klammer für Massenstarts
    started_by      TEXT        NOT NULL,                -- Actor-Kennung (User/Rule/API)
    error_detail    TEXT,
    started_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    finished_at     TIMESTAMPTZ,
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Nur EIN aktiver Workflow pro Konkurrenzschlüssel:
CREATE UNIQUE INDEX workflow_one_active_per_key
    ON workflow_instances (concurrency_key)
    WHERE status IN ('running','waiting_review','waiting_job');

CREATE INDEX workflow_subject_idx ON workflow_instances (subject_type, subject_id, started_at DESC);
CREATE INDEX workflow_batch_idx   ON workflow_instances (batch_id) WHERE batch_id IS NOT NULL;

CREATE TABLE workflow_step_runs (
    id            CHAR(26) PRIMARY KEY,
    instance_id   CHAR(26)    NOT NULL REFERENCES workflow_instances(id) ON DELETE CASCADE,
    step_name     TEXT        NOT NULL,
    attempt       INTEGER     NOT NULL DEFAULT 1,
    status        TEXT        NOT NULL
        CHECK (status IN ('running','waiting','succeeded','failed','skipped','compensated')),
    outcome       JSONB       NOT NULL DEFAULT '{}',   -- Signal-Auszug (Job-Ergebnis, Review-Resolution)
    started_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    finished_at   TIMESTAMPTZ,
    UNIQUE (instance_id, step_name, attempt)
);
```

`context` ist Regel-8-konform (Schritt-Ergebnis-Auszüge für Folgebedingungen, nie relational genutzt; der fachliche Zustand liegt in den Fachtabellen). `definition_version` friert den Ablauf ein: Laufende Instanzen alter Version laufen nach altem Plan zu Ende (die Klasse behält alte `steps()`-Zweige bis keine Instanz mehr lebt — Deployment-Regel, per Test erzwungen: Version-Bump ohne Migrationspfad für aktive Instanzen schlägt CI fehl).

## Laravel-Klassen

| Klasse | Typ | Vertrag |
|---|---|---|
| `WorkflowDefinition` | Abstract | `key()`, `steps()`, `subjectType()`, `concurrencyKey()`, `version()` |
| `WorkflowRegistry` | Service | sammelt Definitionen (Service Provider), validiert Eindeutigkeit |
| `WorkflowRunner` | Service | Zustandsmaschine: nimmt Signal, lädt Instanz row-locked, ermittelt Folgeschritt, führt aus/dispatcht/pausiert — die einzige Schreibstelle für Instanz-Zustand |
| `StartWorkflow` | Action | Konkurrenz-Index-Prüfung (aktive Instanz ⇒ Konflikt oder `supersede` je Definition-Policy); Audit |
| `CancelWorkflow`, `RetryWorkflowStep` | Action | manuelles Eingreifen; Kompensation; Audit |
| `StartWorkflowBatch` | Action | N Instanzen + Batch-Klammer; gestaffeltes Dispatchen (Rate, Default 10/min) gegen Queue-Fluten |
| `WorkflowSignalListener` | Listener | übersetzt Fundament-Events in Runner-Signale (Queue `default`) |
| `WorkflowInstanceFinished` | Event | für Rule Engine/Monitoring |

Der Runner verarbeitet Signale idempotent (Signal für bereits abgeschlossenen Schritt: no-op) und seriell pro Instanz (`SELECT … FOR UPDATE` auf der Instanz-Zeile — zwei gleichzeitige Signale können keinen Schritt doppelt schalten).

## API-Endpunkte

| Route | Zweck | Rolle |
|---|---|---|
| `GET /api/v1/workflows?definition=&status=&subject=` | Instanzliste | manager |
| `GET /api/v1/workflows/{ulid}` | Instanz mit Schritt-Historie | manager |
| `POST /api/v1/workflows` | Start `{definition_key, subject_id, params}` | manager |
| `POST /api/v1/workflows/{ulid}/cancel` / `…/retry-step` | Eingreifen | manager |
| `POST /api/v1/workflow-batches` | Massenstart über Subjekt-Filter | manager |
| `GET /api/v1/workflow-definitions` | verfügbare Typen mit Parameter-Schema | manager |

## UI und Flows

**`Workflows/Index`** — Vorgangsliste (*arr-Activity-artig): Filter nach Definition/Status, Wartende-auf-Review prominent (Direktlink in die Review-Inbox), Batch-Gruppierung mit Fortschrittsbalken. **`Workflows/Show`** — Instanz-Detail: Schrittleiste (erledigt/aktiv/wartend/übersprungen mit Bedingungs-Begründung), Schritt-Outcomes, Retry/Cancel-Aktionen, eingebettete `AuditTimeline`. Kern-Flow: Batch „alle unassemblierten Hörbücher" starten → Liste zeigt 1.847 Instanzen, 210 warten auf Sequenz-Review → Reviews abarbeiten (jede Auflösung schaltet ihre Instanz automatisch weiter) → Batch konvergiert über Tage sichtbar gegen „succeeded".

## Edge Cases

* **Subjekt verschwindet mid-flight** (Edition gelöscht): Runner-Signalverarbeitung erkennt das tote Subjekt ⇒ `cancelled` mit Kompensation, kein Zombie.
* **Review wird dismissed statt resolved**: Definition deklariert pro `waitForReview` das Dismiss-Verhalten (`fail` | `skip` | `cancel`); Default `fail` — stilles Weiterlaufen an einer bewusst nicht getroffenen Entscheidung vorbei ist verboten.
* **Job-Fachfehler** (Fundament: Subjekt fehlerhaft markiert, Job „erfolgreich"): Das Outcome-Signal trägt die Fachfehler-Kennung; die Definition entscheidet (`failOnSubjectError` Default true).
* **Deployment mit Definitionsänderung**: eingefrorene Version, siehe Datenmodell.
* **Instanz-Stau durch verwaiste Wartepunkte** (Review gelöscht, Job-Signal verloren): Sweeper-Job prüft `waiting_*`-Instanzen gegen die Realität (existiert der Review noch? läuft der Job noch?) und heilt bzw. failt mit Diagnose — kein Wartepunkt ohne Verfallsprüfung.

## Performance

Instanzen sind leichtgewichtig (Signal-getrieben, kein Polling pro Instanz); der Sweeper läuft stündlich über die Wartenden. Batch-Staffelung schützt die Queues. Signal-Verarbeitung: ein Row-Lock + wenige Writes, < 10 ms — auch 10k parallele Instanzen erzeugen nur die Last ihrer eigentlichen Fach-Jobs.

## Security

Start/Eingreifen: `manager`; Definitionen mit systemweiter Wirkung (Massen-Export) können per Definition `admin` verlangen. Der Kontext speichert keine Secrets (Denyliste wie Audit). Workflows laufen unter dem Actor ihres Starters (User/Rule) — die Kausalkette im Audit zeigt „Rule X startete Workflow Y, Schritt Z änderte…" durchgängig (correlation_id über die gesamte Instanz).

## Tests

Definitions-Tests als Tabelle (gegebene Signale ⇒ erwartete Schrittfolge; Bedingungs-Zweige; Dismiss-/Fachfehler-Verhalten) über einen In-Memory-Runner-Harness. Konkurrenz-Tests (paralleler Start ⇒ genau eine Instanz; parallele Signale ⇒ serielle Verarbeitung). Versions-Einfrier-Test (CI-Regel, siehe Datenmodell). Batch-Staffelung. End-to-End: die Assembler-Kette gegen die Fixture-Bibliothek inklusive Review-Wartepunkt.

## ADR-Verweise

[ADR-0009](../adr/0009-workflow-definitions-as-code.md) (Definitionen als Code, Instanzen als Daten). Setzt um: Regeln 9, 10 auf Vorgangs-Ebene.

## Offene Punkte

* **Sub-Workflows und dynamische Parallelität**: vertagt bis ein realer Anwendungsfall die statischen Ketten sprengt.
* **Zeitpläne als Trigger** („jeden Sonntag Export-Workflow"): gehört zur Rule Engine (zeitbasierte Bedingungen), nicht hierher — Verweis dort.
* **Kompensations-Tiefe**: Version 1 kompensiert nur eigene `.partial`-Reste; echte Rollbacks fachlicher Schritte (Mapping zurücknehmen) bleiben bewusst manuell (zu gefährlich für Automatik).
