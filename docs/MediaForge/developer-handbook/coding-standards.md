# Code-Standards: vollständige Referenz

Vertiefung zu [developer-handbook/getting-started.md](getting-started.md), Abschnitt „Code-Standards". Das Elternkapitel nennt die Grundsätze; dieses Dokument ist die vollständige, durchsetzbare Fassung — jede Regel mit Positiv-/Negativ-Beispiel, dem Lint-Mechanismus, der sie erzwingt, und der Begründung, damit ein Reviewer nicht raten muss, ob eine Abweichung ein Stilproblem oder ein Architekturbruch ist.

## PHP: Namenskonventionen mit Beispielen

| Artefakt | Muster | Positiv | Negativ | Warum |
|---|---|---|---|---|
| Action | `VerbObjekt` | `ConfirmDiscEpisodeMapping` | `DiscMappingService::confirm()` | Actions sind eigenständige, auditierte Verben — kein Methoden-Grab in einem Service (Modulkapitel-Konvention aller Fach-Actions) |
| Job | `VerbObjektJob` | `AnalyzeDiscImageJob` | `DiscImageJob`, `ProcessDiscJob` | Verb macht die Wirkung sofort lesbar in Queue-Dashboards |
| Event | `ObjektPartizipPerfekt` | `DiscImageAnalyzed` | `AnalyzeDiscImageEvent`, `OnDiscAnalyzed` | Events sind Fakten der Vergangenheit, nie Befehle ([architecture/overview.md](../architecture/overview.md)) |
| DTO | `final readonly class` | `final readonly class PlaybackProgress { ... }` | mutable Klasse, assoziatives Array | Unveränderlichkeit über Modulgrenzen ist Vertragsbestandteil, kein Stil |
| Enum | Backed Enum | `enum WorkQueue: string { case Default = 'default'; }` | String-Literal `'default'` im Code verstreut | ein Tippfehler in einem String-Literal ist ein Laufzeitfehler, ein falscher Enum-Wert ein Compile-Fehler |
| Interface (Service-Grenze) | `<Zweck>Interface` | `DiscAnalyzerInterface` | `IDiscAnalyzer`, `DiscAnalyzerContract` | Konsistenz mit der Laravel-Konvention, kein Fremdpräfix |
| Pure Service | `<Substantiv>` ohne Suffix | `PlaylistClassifier`, `ChapterAligner` | `PlaylistClassifierService` | pure Services sind keine „Services" im DI-Sinn (kein Zustand, keine I/O) — das fehlende Suffix markiert das bewusst (siehe [contracts-reference.md](contracts-reference.md)) |

## PHP: Strukturregeln

**`declare(strict_types=1)`** in jeder Datei ohne Ausnahme (Pint-Regel, CI-Gate). **Konstruktor-Injektion ausschließlich**: kein `app()->make()`, kein `resolve()` außerhalb von Service-Provider-Bindings. **Facades nur in Randschichten** (Controller, Artisan-Kommandos, Job-`handle()`-Einstiegspunkten) — ein Service oder eine Action, die `Cache::`/`DB::` statt injizierter Abhängigkeiten nutzt, ist ein Review-Defekt (Testbarkeits-Begründung: Facade-Aufrufe in der Fachlogik erzwingen Laravel-Boot in jedem Unit-Test).

**Beispiel — korrekt vs. inkorrekt:**

```php
// Korrekt: Action mit injizierten Abhängigkeiten, kein Facade-Zugriff
final class ConfirmDiscEpisodeMapping extends AuditableAction
{
    public function __construct(
        private readonly DiscEpisodeMappingRepository $mappings,
        AuditRecorder $audit,
        DatabaseManager $db,
    ) { parent::__construct($audit, $db); }

    public function execute(ConfirmMappingInput $input): MappingResult
    {
        return $this->transact($mapping, $change, function () use ($input) {
            // ...
        });
    }
}

// Inkorrekt: Facade-Zugriff und Array statt DTO in der Fachlogik
final class ConfirmDiscEpisodeMapping
{
    public function execute(array $input): array
    {
        $mapping = DB::table('disc_episode_mappings')->find($input['id']); // Facade in Fachlogik
        Cache::forget('mapping:'.$input['id']); // Facade in Fachlogik
        return ['status' => 'ok'];              // Array statt typisiertem Result-DTO
    }
}
```

**Nullable vs. Optional-Parameter**: PHP-`?Type` für „kann fehlen", nie leere Strings/`-1`/`0` als Sentinel-Werte für „nicht gesetzt" (durchgängig in allen Modulkapitel-Schemata sichtbar: `resolved_at TIMESTAMPTZ` nullable, nicht `'0001-01-01'`).

## PHP: Einheiten-Disziplin

Jede Zeit-/Größenangabe trägt ihre Einheit im Namen — die Konvention, die sich durch **jedes** Modulkapitel zieht (`position_ms`, `duration_ms`, `size_bytes`, `runtime_ms`) und hier zur harten Regel erklärt wird: eine neue Spalte/Property ohne Einheitensuffix bei einer Zeit-/Größengröße ist ein Review-Defekt. Ausnahme: Datumswerte ohne Uhrzeitkomponente (`released_on DATE`) brauchen kein Suffix, da `DATE` selbst die Einheit trägt.

## PHPDoc-Disziplin

PHPDoc nur, wo die Signatur selbst nicht genügt: Invarianten (`@throws` bei Fachfehlern, die der Typ nicht ausdrückt), Einheiten bei primitiven Rückgabewerten (`@return int Millisekunden`), Nebenwirkungen, die nicht aus dem Namen hervorgehen. Ein PHPDoc-Block, der nur die Typen der Signatur wiederholt (`@param string $id`), ist Rauschen und wird im Review entfernt. Der wertvollste Kommentar im gesamten System ist der Modulkapitel-Verweis bei nicht offensichtlichen Fachentscheidungen:

```php
// Siehe docs/MediaForge/modules/disc-engine/mapping-algorithm.md, Stufe 3 (DP-Übergänge):
// gap_playlist ist bewusst teurer als gap_episode — eine unzugeordnete Episoden-Playlist
// ist ein Warnsignal, eine unbelegte Katalog-Episode der Normalfall bei Teil-Discs.
private const GAP_PLAYLIST_PENALTY = -0.15;
```

## React/TypeScript: Konventionen

Typisierte `.tsx`-Funktionskomponenten sind der einheitliche Schreibstil. Reines JavaScript ist neuem Frontend-Code nur bei zwingendem technischem Grund erlaubt, nie aus Bequemlichkeit — TypeScript ist verbindlicher Teil des Stacks ([ADR-0013](../adr/0013-react-inertia-typescript-and-roadmap-governance.md)). **Props-Verträge als exportierte Interfaces je Seite** — exakt die Interfaces, die die Modulkapitel unter „Props-Vertrag" zeigen, sind reale TypeScript-Verträge, keine Dokumentations-Fiktion. Ein Modulkapitel-Props-Beispiel, das vom tatsächlichen Interface abweicht, ist ein Doku-Bug (`spec-drift`-Issue, [getting-started.md](getting-started.md)).

```ts
// resources/js/types/disc-engine.ts — 1:1 der Vertrag aus modules/disc-engine/ui-reference.md
export interface DiscDetailProps {
  disc: {
    id: Ulid; kind: DiscKind; sourceForm: 'iso' | 'bdmv_folder' | 'video_ts_folder';
    // ...
  };
  playlists: PlaylistRow[];
}
```

**Keine Fach-Berechnung im Frontend** (Architekturregel 2, hier auf Code-Ebene): Eine Komponente, die eine Confidence-Zone selbst aus Schwellwerten berechnet, statt die vom Server gelieferte Zone/Farbe zu übernehmen, verstößt gegen die Regel — Ausnahme ist reine Anzeige-Arithmetik ohne Fachurteil (z. B. die Segment-Editor-Vorschau des Disc-Engine-Kapitels: „dieselbe Formel wie serverseitig, `position − start`" ist explizit als Anzeige-Spiegelung einer bereits servergültigen Formel erlaubt, nicht als eigenständige Entscheidung).

**Komponenten-Bibliothek**: `resources/js/components/base/` ist die einzige spätere gemeinsame Basis (Design-System-Primitive, [ui/design-system.md](../ui/design-system.md)); ein zusätzliches UI-Framework wird nicht eingeführt. V0 enthält bewusst noch keine leere Dummy-Komponentenbibliothek.

## Fehlerbehandlung: Fachfehler vs. Infrastrukturfehler

Durchgängige Unterscheidung (Fundament-Konvention, hier mit Code-Beispiel): Fachfehler sind erwartbare Datenzustände (defekte Datei, unparsebare Struktur) und werden **nie** als PHP-Exception nach oben geworfen, die den Job in die Failed-Queue schickt — sie markieren das Subjekt und terminieren den Job erfolgreich:

```php
// Korrekt: Fachfehler behandelt, Job terminiert erfolgreich
try {
    $result = $this->analyzer->analyze($path);
} catch (UnparsableDiscStructureException $e) {
    $this->discImage->update(['analysis_status' => 'failed', 'analysis_error' => $e->getMessage()]);
    CreateReviewTask::run('disc_analysis_failed', $this->discImage);
    return; // Job erfolgreich beendet — kein Retry, kein Failed-Job-Eintrag
}

// Inkorrekt: Fachfehler wird zur Infrastruktur-Exception hochgereicht
$result = $this->analyzer->analyze($path); // wirft ungefangen ⇒ Failed-Queue, Retry-Serie sinnlos
```

Infrastrukturfehler (Netzwerk, Timeout, Lock-Konflikt) werden **nicht** gefangen — sie sollen die normale Retry-/Backoff-Maschinerie durchlaufen ([architecture/overview.md](../architecture/overview.md)).

## PR-Checkliste (vollständige Fassung)

Die Kurzform aus `getting-started.md` als vollständige, abhakbare Liste:

**Architektur**: Schreibt der Code außerhalb einer Action fachlichen Zustand? · Ist jeder Job idempotent (natürlicher Schlüssel/Signatur-Gate/Unique-Job)? · Trägt jedes fremdbefüllte Feld seine Herkunft (Provenienz-Feld oder `source`-Spalte)? · Enthält ein Controller-Zweig eine Fachentscheidung (gehört in eine Action)? · Braucht eine neue JSONB-Spalte einen Eintrag im [JSONB-Register](../database/schema-reference.md)? · Ist eine neue Lesefläche in der Sichtbarkeits-Suite registriert?

**Schema**: siehe die vollständige [Namens- und Typ-Prüfliste](../database/schema-reference.md#namens-und-typ-prüfliste-für-schema-reviews).

**API**: siehe [Governance neuer Routen](../api/endpoint-catalog.md#governance-neuer-routen) und [Fehlercode-Konsistenzregeln](../api/error-catalog.md#konsistenzregeln-normativ-für-neue-codes).

**UI**: siehe [Design-System-Governance](../ui/design-system.md#governance-neue-komponenten-und-tokens) und [Seiten-Katalog-Contract](../ui/page-catalog.md#governance-und-contract-test).

**Jobs/Events**: siehe [Job-Referenz-Prüfliste](../architecture/jobs-reference.md#prüfliste-für-neue-jobs-pr-checkliste) und [Event-Referenz-Prüfliste](../architecture/events-reference.md#prüfliste-für-neue-events-pr-checkliste).

**Tests**: Ist das neue Verhalten in mindestens einem Testfall verankert ([testing.md](testing.md))? Bei pure Services: Golden-File oder Property-Test? Bei Registries: Contract-Test-Erweiterung ([contracts-reference.md](contracts-reference.md))?

Diese verteilten Prüflisten sind bewusst nicht hier dupliziert (Duplizierung würde bei einer Änderung an zwei Stellen driften) — dieser Abschnitt ist der Einstiegspunkt, der zu allen verweist.

## Lint- und CI-Durchsetzung je Regel

| Regel | Durchsetzung |
|---|---|
| `strict_types=1`, Formatierung | Pint (CI-Gate, kein Diskussionsspielraum) |
| PHPStan Level max, keine Baseline-Erweiterung | PHPStan-CI-Stufe; neue Fehler brechen den Build |
| Naming-Konventionen (Action/Job/Event-Suffixe) | Pest-Arch-Test (`expect(...)->toHaveSuffix('Job')` je Verzeichnis) |
| Facade-Verbot in Fachlogik | Pest-Arch-Test (`App\Modules\*\Actions`/`Services` `->not->toUse(Facade-Klassen)`) |
| Props-Interface = Modulkapitel-Beispiel | manueller Review + `spec-drift`-Issue-Pflicht bei Fund (kein automatischer Diff-Test — Markdown-Codeblock-Extraktion wäre fragiler als der Nutzen) |
| Einheiten-Suffix bei Zeit/Größe | Schema-Reviewer-Checkliste (nicht automatisiert — Spaltennamen sind kein Lint-Ziel ohne hohen False-Positive-Preis) |
| Keine Fach-Berechnung im Frontend | Review + die Architektur-Test-Erweiterung „Komponenten importieren keine Fachlogik-Module" (grobkörnige Heuristik: TSX-Dateien dürfen `app/Modules/*/Services` nicht referenzieren — gilt nur für den seltenen Fall gemeinsamer Build-Artefakte, meist reicht Review) |

## Tests dieses Kapitels

Die Namenskonventions- und Facade-Verbots-Regeln sind selbst als Pest-Arch-Tests im Repo — dieses Dokument beschreibt sie, es dupliziert sie nicht als separate Prüf-Suite. Ein neuer Abschnitt hier ohne zugehörigen Arch-Test (wo automatisierbar) ist unvollständig.
