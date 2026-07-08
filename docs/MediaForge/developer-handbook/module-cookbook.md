# Modul-Anlage: durchgerechnetes Kochrezept

Vertiefung zu [developer-handbook/getting-started.md](getting-started.md), Abschnitt „Modul-Anlage (Kochrezept)". Das Elternkapitel nennt acht Schritte; dieses Dokument führt sie **vollständig durch** am NFO-Export ([Enrichment](../modules/enrichment.md), Artefakt-Export). Der Cookbook-Durchlauf ist zugleich die Kurzspezifikation dieses kleinen Moduls und verweist auf das konsolidierte Modulkapitel [NFO Export](../modules/nfo-export.md) — ein Nebeneffekt, der zeigt, dass das Rezept trägt: Eine Modulanlage nach diesen acht Schritten *ist* eine vollständige Spezifikation, kein Beiwerk dazu.

## Der Beispielfall: `NfoExport`

Kodi und andere NFO-lesende Tools erwarten pro Werk eine `.nfo`-Datei (XML) neben den Mediendateien. MediaForge liest NFOs beim Import (Connector-Ingest, geplant), schreibt aber nie welche (Architekturregel 4 — keine Schreibungen neben Originale). Der offene Punkt aus dem Enrichment-Kapitel: ein **Artefakt-Export**, der NFO-Dateien in die Artefakt-Ablage schreibt (nicht neben die Originale) und über einen Symlink-freien Export-Mechanismus (analog dem ABS-Export des Assemblers) verfügbar macht — für Nutzer, die einen zweiten, NFO-lesenden Player (Kodi ohne MediaForge-Anbindung) parallel betreiben wollen.

## Schritt 1: Modulkapitel zuerst

Die Spezifikation entsteht vor dem Code. Kurzfassung des Kapitels, das unter `docs/MediaForge/modules/nfo-export.md` entstünde (nach dem [Modul-Template](../MediaForge_Master_Engineering.md#dokumentkonventionen)):

* **Motivation**: NFO-Interoperabilität für Kodi-Parallelbetrieb ohne Architekturregel-4-Verletzung.
* **Architekturentscheidung**: Export-Artefakt (wie ABS-Export), gespeist aus dem Katalogstand zum Export-Zeitpunkt, mit `input_signature` über (Item-Feldstand, Template-Version) — identisches Idempotenz-Muster wie jeder andere Artefakt-Builder.
* **Datenmodell**: keine neue Tabelle nötig — der Export nutzt ausschließlich `artifacts` (Fundament) mit `artifact_type='nfo_export'`.
* **Abhängigkeiten**: [Enrichment](../modules/enrichment.md) (Feldquelle), [database/core-schema.md](../database/core-schema.md) (Artefakt-Modell).

## Schritt 2: Namespace mit Service Provider

```
app/Modules/NfoExport/
├── NfoExportServiceProvider.php
├── Services/NfoTemplateRenderer.php     (pure Service, Muster 2)
├── Jobs/BuildNfoExportJob.php
├── Actions/RequestNfoExport.php
└── Http/NfoExportController.php
```

```php
final class NfoExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NfoTemplateRenderer::class);
    }

    public function boot(HealthCheckRegistry $health, DashboardCardRegistry $cards): void
    {
        // Registry-Beiträge, s. Schritt 5 — Registrierung geschieht hier, nicht zentral.
    }
}
```

Registrierung in `config/app.php`-Provider-Liste (Standard-Laravel-Mechanismus, keine MediaForge-Sonderform).

## Schritt 3: Migrationen

Keine neue Tabelle (Schritt-1-Entscheidung); trotzdem eine Migration nötig — die Erweiterung des Fundament-`CHECK`-Constraints:

```php
// database/migrations/2026_08_01_000000_add_nfo_export_artifact_type.php
public function up(): void
{
    DB::statement("ALTER TABLE artifacts DROP CONSTRAINT artifacts_artifact_type_check");
    DB::statement("ALTER TABLE artifacts ADD CONSTRAINT artifacts_artifact_type_check
        CHECK (artifact_type IN ('m4b','cue','flac_upscale','wav_upscale','export_abs',
                                  'nfo_export','waveform_json','analysis_report','thumbnail','other'))");
}
```

Nach [migrations.md](../database/migrations.md)-Konvention: additive Enum-Erweiterung, keine editierte Alt-Migration (Schema-Konventionen, [core-schema.md](../database/core-schema.md)).

## Schritt 4: Models, Actions, Jobs

Kein neues Model (Fundament-`Artifact`-Model wird verwendet); die Fach-Action:

```php
final class RequestNfoExport extends AuditableAction
{
    public function __construct(
        private readonly NfoTemplateRenderer $renderer,
        AuditRecorder $audit, DatabaseManager $db,
    ) { parent::__construct($audit, $db); }

    public function execute(NfoExportRequest $input): OperationRef
    {
        // Idempotenz-Gate: input_signature aus Item-Feldstand + Template-Version (Muster: Fundament-Artefaktmodell)
        // dispatcht BuildNfoExportJob, liefert Operations-Referenz (202-Muster, api/conventions.md)
    }
}
```

```php
final class BuildNfoExportJob extends ResumableJob
{
    public function checkpointKey(): string { return "nfo-export:{$this->mediaItemId}"; }
    public function steps(): array
    {
        return [
            Step::make('render', fn () => $this->renderer->render($this->mediaItem)),
            Step::make('write', fn () => $this->writeAtomically('.nfo.partial', '.nfo')),
            Step::make('register', fn () => RegisterArtifact::run(/* ... */)),
        ];
    }
}
```

`NfoTemplateRenderer` ist der pure Service (Muster 2, [contracts-reference.md](contracts-reference.md)): nimmt einen Katalog-DTO, liefert einen XML-String — keine I/O, vollständig Golden-File-testbar.

## Schritt 5: Registry-Beiträge

Der Schritt, der das Modul „gut integriert" statt nur „funktionsfähig" macht — gegen die vier Registries aus [contracts-reference.md](contracts-reference.md) geprüft:

* **Health-Check**: `nfo_export.template_valid` — prüft, ob die ausgelieferte NFO-Vorlage gegen das erwartete XML-Schema validiert (fail bei Drift nach einem Template-Update ohne Versionsbump). Eintrag in [health-check-reference.md](../modules/health-monitoring/health-check-reference.md) mit `remedyRef` auf einen neuen `runbooks.md`-Anker.
* **Quality-Check**: keiner (NFO-Export ist ein Ausgabe-Artefakt, kein Katalog-Vollständigkeitsmerkmal — bewusst **kein** Beitrag; nicht jedes Modul muss jede Registry bedienen).
* **Dashboard-Card**: `NfoExportBacklogCard` (`admin`-Rolle) — zeigt Items mit veraltetem NFO-Export (Signatur-Divergenz), Link in eine Massenaktion.
* **Rule-Prädikat**: `nfo.export_stale` (optional, für Betreiber, die den Export per Regel automatisieren wollen — analog dem Assembler-Auto-Rebuild-Regel-Beispiel).

## Schritt 6: Architektur-Tests erweitern

```php
arch('NfoExport nutzt nur Core und eigene Services')
    ->expect('App\Modules\NfoExport')
    ->toOnlyUse(['App\Core', 'App\Modules\NfoExport', /* framework, vendor */]);

arch('NfoTemplateRenderer ist ein pure Service')
    ->expect('App\Modules\NfoExport\Services\NfoTemplateRenderer')
    ->not->toUse([DB::class, Http::class, Cache::class]);
```

Die zweite Regel ist die maschinelle Durchsetzung von Muster 2 (Pure Service) — nicht nur Konvention, sondern CI-Gate.

## Schritt 7: Testfälle nach Modulkapitel

Nach der [Test-Gesamtstrategie](testing.md): Golden-File-Test für `NfoTemplateRenderer` (bekannter Katalog-DTO ⇒ byte-identisches XML), Idempotenz-Test für `BuildNfoExportJob` (`assertJobIsIdempotent`-Harness), Action-Audit-Test (`assertActionIsAudited`-Harness), Contract-Test der neuen Health-Check-Registrierung (`remedyRef` löst auf). Invarianten-Kandidat: keiner (der Export ist ein Komfort-Feature ohne Watch-State-/Sicherheitsrelevanz — nicht jedes Modul liefert einen Beitrag zur Invarianten-Suite, und das ist der korrekte Befund, nicht eine Lücke).

## Schritt 8: Masterdatei-TOC aktualisieren

```
| NFO-Export | [modules/nfo-export.md](../modules/nfo-export.md) | ✅ |
```

Zeile unter „Fach-Engines" (oder einer passenderen Kategorie, falls das Modul eher „Betrieb und Qualität" ist — Einordnungsfrage im PR-Review, nicht automatisch).

## Was das Rezept sichtbar macht

Der durchgerechnete Fall zeigt zwei Dinge, die die Kurzfassung nicht zeigt: **Erstens**, nicht jeder Schritt erzeugt zwangsläufig neuen Code — Schritt 3 (Migration) war hier nur eine Enum-Erweiterung, Schritt 4 (Models) brauchte kein neues Model. Das Rezept ist eine **Prüfliste**, kein Boilerplate-Generator; ein Modul, das ehrlich „keine neue Tabelle nötig" für Schritt 3 begründet, hat den Schritt trotzdem durchlaufen. **Zweitens**, Schritt 5 (Registry-Beiträge) ist der Schritt, der am leichtesten übersprungen wird und am meisten über die Integrationsqualität aussagt — ein Modul ohne jeden Registry-Beitrag ist meist ein Zeichen, dass eine der vier Fragen aus [contracts-reference.md](contracts-reference.md) nicht gestellt wurde, nicht dass keine passt.

## Häufige Abweichungen vom Rezept (und wann sie legitim sind)

| Abweichung | Legitim, wenn |
|---|---|
| Kein Quality-Check-Beitrag | das Modul erzeugt keine Katalog-Vollständigkeits-/Verlässlichkeits-Dimension (NFO-Export-Beispiel) |
| Kein Rule-Prädikat | kein sinnvoller Bedingungs-Anwendungsfall über den Katalogzustand (viele Betriebs-Module) |
| Kein neues Model | das Modul nutzt ausschließlich Fundament-Entitäten (NFO-Export-Beispiel: nur `Artifact`) |
| Kein Invarianten-Suite-Beitrag | keine Watch-State-/Sicherheits-/Architekturregel-Berührung |
| Connector statt Fach-Engine-Schnitt | die Domäne ist eine externe Systemanbindung — dann gilt das [Connector-SDK-Rezept](../connectors/connector-sdk.md) statt dieses, mit Manifest/Client/Übersetzer/Handler statt der acht Schritte hier |

Eine Abweichung **ohne** eine der genannten Begründungen ist im PR-Review zu hinterfragen — das Rezept ist der Default, nicht eine von vielen gleichwertigen Optionen.

## Tests dieses Kapitels

Der NFO-Export-Durchlauf selbst ist kein lauffähiger Code (dieses Dokument bleibt didaktisch), aber das Modul ist als Dokumentationskapitel vorhanden: [modules/nfo-export.md](../modules/nfo-export.md). Dieses Cookbook bleibt die Schritt-für-Schritt-Herleitung; das Modulkapitel ist der Einstiegspunkt für Querverweise, Roadmap und spätere Vertiefungen. Jede Code-Zeile hier folgt den [Code-Standards](coding-standards.md), jedes Muster ist in [contracts-reference.md](contracts-reference.md) verankert.
