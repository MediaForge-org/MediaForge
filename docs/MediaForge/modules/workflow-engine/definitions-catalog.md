# Workflow-Definitions-Katalog

Vertiefung zu [modules/workflow-engine.md](../workflow-engine.md). Normativer Katalog aller Workflow-Definitionen des Systems, der Schritt-Primitive im Detail und der Konkurrenz-Policies. Das Modulkapitel definiert die Engine (Runner, Signale, Datenmodell); dieses Dokument definiert **was tatsächlich läuft** — jede Definition mit `key()`, Schrittfolge, Bedingungen und Kompensationsverhalten, damit ein Betreiber, der `GET /workflow-definitions` aufruft, hier die vollständige Erklärung jeder zurückgelieferten Definition findet.

## Schritt-Primitive: vollständige Semantik

Das Modulkapitel nennt die Primitive; hier ihre exakten Ausführungsregeln.

| Primitiv | Ausführung | Abschluss-Signal | Fehlerverhalten |
|---|---|---|---|
| `Step::job(name, JobClass)` | dispatcht den Job, Schritt geht auf `waiting_job` | `job_progress.outcome` (`succeeded`/`failed`/`superseded`) oder das jobspezifische Fundament-Event | `failed` ⇒ Instanz `failed` (außer `retryable(n)` deklariert, dann Re-Dispatch bis n Versuche) |
| `Step::action(name, ActionClass)` | synchron in der Runner-Transaktion (`AuditableAction::transact`) | sofortiger Rückgabewert | Exception ⇒ Schritt `failed`, Instanz `failed`, Transaktion rollt zurück (kein Teilzustand) |
| `Step::waitForReview(name, taskType)` | erzeugt/sucht `review_tasks`-Zeile passenden Typs zum Subjekt, Schritt geht auf `waiting_review` | `ReviewTaskResolved` | Dismiss-Verhalten konfigurierbar (`fail`/`skip`/`cancel`, Modulkapitel), Default `fail` |
| `Step::parallel([...])` | dispatcht alle Kind-Schritte gleichzeitig, Schritt bleibt `waiting` bis alle Kinder `succeeded`/`skipped` | letztes Kind-Signal löst Prüfung aus | ein `failed`-Kind ⇒ Gruppe `failed` (keine Teilfortsetzung) |
| `.onlyIf(fn)` / `.skipIf(fn)` | Bedingung über `Ctx` ausgewertet **vor** Schritt-Start | — | Bedingung `false` bei `onlyIf` bzw. `true` bei `skipIf` ⇒ Schritt `skipped`, Kontext vermerkt Begründung |
| `.compensate(fn)` | läuft nur bei Instanz-Abbruch (`cancelled`/`failed`) für bereits `succeeded`-Schritte dieser Definition, in umgekehrter Reihenfolge | — | Kompensationsfehler werden geloggt, brechen den Abbruch nicht ab (Best-Effort, Modulkapitel: „Version 1 kompensiert nur eigene `.partial`-Reste") |
| `.retryable(n)` | Modifikator auf `job`/`action`: bei `failed` bis zu `n`-mal neu versuchen (Backoff wie Fundament-Jobklasse) | — | nach `n` Versuchen normales `failed`-Verhalten |

`Ctx` ist ein readonly Kontext-Objekt mit Zugriff auf `subject()` (lazy geladenes Fach-Model), `setting(key)` (Instanz-/Definition-Settings), `stepResult(name)` (Outcome vorheriger Schritte) und `param(key)` (Start-Parameter der Instanz).

## Konkurrenz-Policy je Definition

`concurrencyKey()` liefert den Schlüssel; jede Definition deklariert zusätzlich eine **Policy** für den Fall einer bereits aktiven Instanz mit demselben Schlüssel:

| Policy | Verhalten bei Kollision |
|---|---|
| `reject` (Default) | `StartWorkflow` scheitert mit `workflow.subject_conflict` (409) |
| `supersede` | die alte Instanz wird `superseded` (Kompensation läuft), die neue startet |
| `queue` | die neue Instanz wartet (Status `waiting_job`-artig auf einen internen „Vorgänger frei"-Signal) bis die alte terminiert |

Die Policy ist Teil der Definition, nicht der Start-Anfrage — ein Aufrufer kann sie nicht überstimmen (sonst wäre die Konkurrenzgarantie ein Vorschlag statt einer Garantie).

## Vollständiger Definitions-Katalog

### `audiobook.assemble-and-export`

Subjekt: `media_edition`. Policy: `reject`. Vollständige Schrittfolge (Modulkapitel zeigt einen Auszug; hier vollständig inkl. Kompensation):

```
1. sequence         Step::job(SequenceAudiobookJob)
                     .skipIf(assembly.status !== 'draft')
2. sequence-review  Step::waitForReview('audiobook_sequence')
                     .onlyIf(assembly.sequence_confidence < 0.95)
                     dismissBehavior: 'fail'
3. collect          Step::job(CollectChapterSourcesJob)
4. select           Step::action(SelectActiveChapterSet)
                     .orWaitForReview('chapter_proposal')
5. build-cue        Step::job(BuildCueJob)
                     .compensate(fn: delete .partial CUE artifact if present)
6. build-m4b        Step::job(BuildM4bJob)
                     .onlyIf(setting('build_m4b_enabled'))
                     .retryable(1)
                     .compensate(fn: purge chunk tmp dir)
7. export           Step::job(BuildAbsExportJob)
                     .onlyIf(setting('abs_export_enabled'))
```

Start-Parameter: keine Pflichtparameter (liest Assembly-Zustand aus dem Subjekt). Typischer Starter: Rule-Engine-Regel „neues Hörbuch importiert" oder manueller API-Aufruf.

### `disc.reanalyze-and-remap`

Subjekt: `disc_image`. Policy: `reject` (deckt sich mit `disc.analysis_running` der API-Ebene — der Workflow ist die Orchestrierungs-Sicht desselben Ausschlusses).

```
1. analyze     Step::job(AnalyzeDiscImageJob)
2. review      Step::waitForReview('disc_episode_mapping')
                .onlyIf(disc.mapping_summary.review_open === true)
                dismissBehavior: 'skip'   -- unresolved review blockiert nicht den restlichen Ablauf
3. notify      Step::action(NotifyDiscReady)
                .onlyIf(setting('notify_on_reanalyze'))
```

Der abweichende `dismissBehavior: 'skip'` (statt Default `fail`) ist bewusst: Ein Reanalyse-Workflow soll nicht scheitern, nur weil ein Mapping-Review offen bleibt — die Disc ist trotzdem nutzbar (teilweise gemappt), der Workflow dient hier primär der Analyse-Orchestrierung, nicht dem vollständigen Mapping-Abschluss.

### `upscale.request-and-notify`

Subjekt: `media_edition`. Policy: `reject` (deckt sich mit dem Duplikat-Unique-Index des Upscalers).

```
1. preflight   Step::job(PreflightUpscaleJob)
2. dispatch    Step::job(DispatchUpscaleChunksJob)
                .skipIf(preflight.result.status === 'rejected_pointless')
3. finalize    Step::job(FinalizeUpscaleJob)
4. notify      Step::action(NotifyUpscaleComplete)
                .onlyIf(setting('notify_on_upscale'))
```

Kompensation: keine (Upscale-Artefakte werden über den regulären `orphaned`-GC bereinigt, nicht über Workflow-Kompensation — ein abgebrochener Upscale-Lauf ist bereits über den Run-Status des Fachmoduls nachvollziehbar).

### `enrichment.bulk-refresh` (Batch-Definition)

Subjekt: `media_item`. Policy: `queue` (ein Bulk-Refresh pro Item soll nicht mit einem parallel laufenden Einzel-Refresh kollidieren, aber auch nicht scheitern — er wartet).

```
1. refresh     Step::job(EnrichEntityJob)
                .retryable(2)
```

Wird ausschließlich über `StartWorkflowBatch` gestartet (nie einzeln über die UI) — der typische Aufruf: „alle Items eines Genres nach Provider-Reihenfolgen-Änderung neu anreichern".

### `catalog.acquire-wanted-item` (*arr-Klammer)

Subjekt: `media_item`. Policy: `reject`.

```
1. request-search  Step::action(RequestArrSearch)
2. wait-import      Step::waitForReview('media_match')
                     .onlyIf(false)   -- deaktivierter Erweiterungsschritt fuer zukuenftige Bestaetigungspflicht
```

Hinweis: Schritt 2 ist bewusst als deaktivierter Erweiterungsschritt dokumentiert (Modulkapitel-Konsistenz: Definitionen sind Code, aber künftige Zweige müssen sichtbar, nicht versteckt sein) — der Import-Abschluss kommt aktuell über den regulären `ScanPathJob`-Trigger der *arr-Connectoren, nicht über diesen Workflow-Schritt.

## Registrierungs-Konvention

Definitionen wohnen im Modul ihres fachlichen Schwerpunkts (`App\Modules\AudiobookAssembler\Workflows\AssembleAndExportAudiobook`) oder unter `App\Modules\WorkflowEngine\Definitions`, wenn sie modulübergreifend sind (`catalog.acquire-wanted-item` verknüpft *arr-Connector und Katalog-Fundament). Jede Definitionsklasse registriert sich im `WorkflowRegistry` über den Service Provider ihres Heimatmoduls — **nicht** zentral in der Workflow Engine (das würde die Modulgrenzen-Regel verletzen: `WorkflowEngine → Modules` ist so wenig erlaubt wie umgekehrt; die Registrierung läuft über Interface-Discovery, nicht über Imports).

## Versions-Migrationsregel (Ergänzung zum Modulkapitel)

Ein Versionssprung (`version()` erhöht) ist nur zulässig, wenn die Klasse für die alte Version einen `legacySteps(int $version): array`-Zweig behält, bis `SELECT count(*) FROM workflow_instances WHERE definition_key=? AND definition_version=? AND status IN ('running','waiting_review','waiting_job')` null ergibt (CI-Regel des Modulkapitels operationalisiert: der Architektur-Test prüft, dass jede im Code vorhandene `version()` < aktuell entweder in `legacySteps()` behandelt wird oder per Migrations-Skript nachweislich keine aktiven Instanzen mehr hat).

## Batch-Staffelungs-Parameter (Ergänzung zu `StartWorkflowBatch`)

| Definition | Default-Rate | Begründung |
|---|---|---|
| `audiobook.assemble-and-export` | 10/min | I/O-lastige Analyse-Schritte, `scan`/`analyze`-Queue-Schonung |
| `enrichment.bulk-refresh` | 5/min | Provider-Rate-Limits dominieren ohnehin (Enrichment-Referenz) |
| `disc.reanalyze-and-remap` | 3/min | Analyse ist NAS-I/O-teuer |
| `upscale.request-and-notify` | 1/min | GPU-Serialität macht höhere Raten wirkungslos |

## Test-Anker

Jede Definitionszeile dieses Katalogs hat einen Tabellentest im Modul-Testkatalog: gegebene Signal-Sequenz ⇒ erwartete Schrittfolge inkl. `skipped`-Begründungen; Kompensations-Pfad bei künstlichem Abbruch nach jedem einzelnen Schritt (Kompensation muss für Schritt n den Zustand von Schritt n-1 wiederherstellen, nicht weiter); Konkurrenz-Policy-Matrix (`reject`/`supersede`/`queue` × Kollisionsfall). Der Registrierungs-Contract-Test prüft, dass jede hier dokumentierte Definition tatsächlich im `WorkflowRegistry` erscheint und umgekehrt (Drift-Schutz wie bei den API-Katalogen).
