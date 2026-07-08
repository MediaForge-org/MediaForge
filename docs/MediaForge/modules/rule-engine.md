# Rule Engine

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [database/core-schema.md](../database/core-schema.md), [modules/audit.md](audit.md), [Workflow Engine](workflow-engine.md) (Regeln starten Workflows; Abgrenzung dort normativ: Regeln entscheiden ob/wann, Workflows wie).

**Vertiefung**: [Prädikat- und Aktions-Referenz](rule-engine/predicate-reference.md) (vollständiger Katalog, SQL-Kosten-Einstufung, Trace-Format)

## Motivation

Betreiber wollen Automatik deklarieren, ohne Code zu schreiben: „Hörbücher unter 96 kbit/s → Upscale-Workflow", „neue Discs ohne Set-Zuordnung älter als 7 Tage → Review-Erinnerung", „Episoden mit `presence='wanted'` und Sonarr meldet verfügbar → Tag `system:incoming`". Die Rule Engine ist der deklarative Automatik-Layer: Bedingungen über den Katalogzustand, verknüpft mit einem kleinen Satz erlaubter Aktionen. Sie ist bewusst der **einzige** Ort, an dem Betreiber-definierte Automatik lebt — verstreute „Auto-Häkchen" in jedem Modul wären dieselbe Funktionalität ohne gemeinsame Beobachtbarkeit, Dämpfung und Auditierung.

## Problemstellung

**Ausdrucksstärke vs. Sicherheit.** Eine Regelsprache, die alles kann, ist eine Programmiersprache im UI (Workflow-Kapitel, Alternativen — dieselbe Falle). Eine zu schwache kann die realen Fälle nicht fassen. Der Schnitt: reiche, aber **endliche** Bedingungsgrammatik über definierte Felder; Aktionen nur aus einem registrierten, pro Aktion einzeln freigegebenen Katalog.

**Konvergenz.** Regeln, die Zustand ändern, der Regeln triggert, erzeugen Schleifen (Tag setzen ⇒ Änderung ⇒ Regel feuert ⇒ …). Die Engine braucht strukturelle Dämpfung, nicht Hoffnung auf disziplinierte Regel-Autoren.

**Mengen.** „Alle Items prüfen" bei 300k Items pro Änderungsevent ist indiskutabel; Evaluation muss inkrementell (geänderte Subjekte) und für Zeitregeln batch-weise laufen.

## Analyse bestehender Lösungen

***arr Custom Formats/Auto-Tagging**: das reifste Vorbild — deklarative Bedingungsbäume (JSON) mit Score-Semantik, im UI editierbar; übernommen: der Bedingungsbaum und die UI-Idee; nicht übernommen: die Score-Mechanik (MediaForge-Regeln entscheiden binär und handeln, sie ranken nicht). **Home Assistant Automations**: Trigger/Condition/Action-Dreiteilung mit Trace-Ansicht — die Trace-Idee (warum feuerte das?) wird als Pflichtfeature übernommen. **E-Mail-Filterregeln** (Sieve): beweist, dass endliche Grammatiken jahrzehntelang tragen.

## Architekturentscheidung

Eine Regel ist Daten (DB, UI-editierbar), bestehend aus **Trigger**, **Bedingung**, **Aktionen**:

* **Trigger**: `event` (Fundament-Events aus einem registrierten Katalog: `MediaItemCreated`, `FileFingerprinted`, `DiscImageAnalyzed`, `AudiobookSequenced`, `WorkflowInstanceFinished`, …) oder `schedule` (Cron-Ausdruck; das ist die Antwort auf den offenen Punkt der Workflow Engine — Zeitpläne leben hier).
* **Bedingung**: JSON-Baum aus `all`/`any`/`not`-Knoten über **registrierte Prädikate**. Prädikate sind PHP-Klassen mit Namen, Subjekt-Typ, Parametern und SQL-Übersetzung (`PredicateInterface::toQuery()`), z. B. `media.type`, `media.presence`, `audio.bitrate_below(kbps)`, `disc.mapping_status`, `assembly.status`, `tag.has(namespace,name)`, `age.days_since(field, n)`. Kein Freitext-SQL, kein Skripting — jede Bedingung ist statisch validierbar und indexfreundlich übersetzbar.
* **Aktionen**: registrierter Katalog mit Gefahrenklasse: `add_tag`/`remove_tag` (nur Namespace `rule:` — Regel 8-analoge Namespace-Hygiene aus dem Kernschema), `create_review`, `start_workflow(definition)`, `dispatch_job(whitelisted)`, `notify(channel)`. **Nicht** im Katalog und nie aufnehmbar: direkte Watch-State-Änderungen, Mapping-Bestätigungen, Löschungen, Chapter-Set-Aktivierungen — alles, was per Architekturregel menschliche Entscheidung oder Fach-Action-Kontext verlangt. Die Rule Engine kann anstoßen und markieren, nie fachlich entscheiden.

**Konvergenz-Dämpfung** (strukturell, dreifach): (1) Feuer-Protokoll `rule_firings` mit Unique-Fenster — dieselbe Regel feuert für dasselbe Subjekt höchstens einmal pro Cooldown (Default 24 h, pro Regel konfigurierbar, Minimum 1 h); (2) Regeln sehen von Regeln verursachte Tag-Änderungen nicht als Trigger (Event-Filter über den Audit-Actor `rule:*` — Regelketten müssen explizit über `start_workflow` gebaut werden, nie implizit über Tag-Kaskaden); (3) globaler Kill-Switch + Feuerraten-Alarm (Regel feuert > N/h ⇒ automatische Pausierung + Review `rule_runaway`).

## Alternativen

**Skript-Hooks** (Lua/JS-Snippets als Bedingung): maximale Macht, unauditierbar, Sandbox-Aufwand — verworfen; wer skripten will, schreibt ein Plugin ([Plugin SDK](../developer-handbook/plugin-sdk.md)) mit dessen Sandbox-Vertrag. **Score-Systeme** (*arr-artig): für Beschaffungs-Ranking sinnvoll, für MediaForge-Automatik unnötige Indirektion. **Pro-Modul-Automatikschalter**: siehe Motivation — verworfen zugunsten des einen Layers; bestehende Auto-Verhalten des Fundaments (Auto-Confirm der Disc-Mappings, Auto-Aktivierung von Chapter Sets) bleiben Modul-Settings, weil sie Fach-Confidence-Logik sind, keine Betreiber-Deklaration (die Grenze: was eine Confidence-Schwelle hat, ist Modul-Setting; was Katalogzustand verknüpft, ist Regel).

## Datenmodell und SQL-Schema

```sql
CREATE TABLE rules (
    id             CHAR(26) PRIMARY KEY,
    name           TEXT        NOT NULL,
    description    TEXT,
    enabled        BOOLEAN     NOT NULL DEFAULT true,
    trigger_kind   TEXT        NOT NULL CHECK (trigger_kind IN ('event','schedule')),
    trigger_ref    TEXT        NOT NULL,            -- Event-Klasse bzw. Cron-Ausdruck
    subject_type   TEXT        NOT NULL,            -- 'media_item','file','disc_image','media_edition'
    condition      JSONB       NOT NULL,            -- validierter Prädikat-Baum
    actions        JSONB       NOT NULL,            -- [{action:'start_workflow', params:{…}}]
    cooldown_hours INTEGER     NOT NULL DEFAULT 24 CHECK (cooldown_hours >= 1),
    paused_reason  TEXT,                            -- gesetzt bei Runaway-Pausierung
    created_by     CHAR(26)    REFERENCES users(id) ON DELETE SET NULL,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (name)
);

CREATE TABLE rule_firings (
    id           CHAR(26) PRIMARY KEY,
    rule_id      CHAR(26)    NOT NULL REFERENCES rules(id) ON DELETE CASCADE,
    subject_type TEXT        NOT NULL,
    subject_id   CHAR(26)    NOT NULL,
    trace        JSONB       NOT NULL,              -- Prädikat-Auswertung + Aktions-Ergebnisse
    fired_at     TIMESTAMPTZ NOT NULL DEFAULT now()
) PARTITION BY RANGE (fired_at);

-- Cooldown-Durchsetzung geschieht in der Engine (Zeitfenster-Query auf diesen Index):
CREATE INDEX rule_firings_cooldown_idx ON rule_firings (rule_id, subject_type, subject_id, fired_at DESC);
```

`condition`/`actions` sind gerechtfertigtes JSONB: Sie werden als Ganzes validiert, versioniert (Audit-Diff bei Regeländerung) und von der Engine interpretiert — nie relational zerlegt. `trace` beantwortet die Home-Assistant-Frage „warum feuerte das?" pro Feuerung (jedes Prädikat mit Ist-Wert und Ergebnis).

## Laravel-Klassen

| Klasse | Typ | Vertrag |
|---|---|---|
| `Rule`, `RuleFiring` | Model | wie Schema |
| `PredicateRegistry`, `RuleActionRegistry` | Service | Registrierung via Service Provider (Module steuern eigene Prädikate/Aktionen bei — z. B. registriert die Disc-Engine `disc.mapping_status`); Schema-Export für den UI-Builder |
| `ConditionCompiler` | Service (pure) | Prädikat-Baum → Eloquent/SQL-Query (für Batch) bzw. → In-Memory-Evaluator (für Einzel-Subjekt am Event) — **eine** Prädikat-Definition, zwei Ausführungsformen, per Test auf Ergebnisgleichheit verifiziert |
| `EvaluateRuleForSubjectJob` | Job (`default`) | Event-Pfad: Subjekt gegen alle passenden Event-Regeln; Cooldown-Prüfung; Aktionen ausführen; Trace schreiben |
| `RunScheduledRuleJob` | ResumableJob (`default`) | Schedule-Pfad: kompiliierte Query in 1000er-Chunks; pro Treffer wie oben |
| `CreateRule`, `UpdateRule`, `PauseRule` | Action | Baum-Validierung gegen Registries; Audit (Regeländerungen sind auditpflichtige Systemänderungen) |
| `RuleRunawayMonitor` | Listener/Scheduler | Feuerraten-Alarm, Auto-Pausierung |

Aktionen laufen unter Actor `rule:<name>` — jede Regel-Wirkung ist im Audit als solche sichtbar und über die correlation_id mit allem verknüpft, was sie auslöste (Kausalkette bis in den gestarteten Workflow, [modules/audit.md](audit.md)).

## API-Endpunkte

| Route | Zweck | Rolle |
|---|---|---|
| `GET /api/v1/rules` / `POST` / `PUT /{ulid}` / `DELETE` | CRUD | admin |
| `POST /api/v1/rules/{ulid}/test` | Dry-Run: Bedingung gegen Bestand, Trefferliste + Traces, **ohne** Aktionen | admin |
| `POST /api/v1/rules/{ulid}/pause` / `resume` | Betrieb | admin |
| `GET /api/v1/rules/{ulid}/firings` | Feuerungs-Historie mit Traces | manager |
| `GET /api/v1/rule-schema` | Prädikat-/Aktions-Katalog für den Builder | admin |

## UI und Flows

**`Rules/Builder`** — Bedingungsbaum-Editor (verschachtelbare all/any/not-Gruppen, Prädikat-Auswahl mit typisierten Parameterfeldern aus dem Schema-Export), Aktionsliste mit Gefahrenklassen-Kennzeichnung, Cooldown. Pflicht-Schritt vor dem Aktivieren: **Dry-Run** — „Diese Regel würde jetzt 214 Subjekte treffen" mit Stichproben-Traces; Aktivierung ohne Dry-Run-Ansicht ist im UI nicht erreichbar (der API-Weg erlaubt es, gedacht für Automatisierung — das UI erzieht, die API vertraut). **`Rules/Index`** — Regelliste mit Feuerraten-Sparkline, Pausiert-Status samt Grund, letzte Feuerungen. Kern-Flow Runaway: Regel feuert 400×/h ⇒ Auto-Pause + Review ⇒ Admin öffnet Traces, sieht das Schleifenmuster (eigenes Tag als Bedingungstreffer), korrigiert Bedingung, Resume.

## Edge Cases

* **Regeländerung während Batch-Lauf**: Läufe arbeiten auf dem Regel-Snapshot ihres Starts (Instanz-Kopie im Job-Payload); die geänderte Regel gilt ab dem nächsten Lauf.
* **Subjekt trifft mehrere Regeln mit widersprüchlichen Aktionen** (`add_tag X` vs. `remove_tag X`): keine Orchestrierung zwischen Regeln — beide feuern, Reihenfolge undefiniert, das Ergebnis ist instabil **und sichtbar** (Traces + Audit); der Feuerraten-Monitor erkennt das Ping-Pong über den Cooldown hinweg und pausiert beide mit `rule_conflict`-Review. Bewusst keine Prioritätsmechanik in Version 1 (Komplexitätsfalle).
* **Prädikat-Registrierung verschwindet** (Modul deaktiviert/Plugin entfernt): Regeln mit unbekannten Prädikaten werden automatisch pausiert (`paused_reason='missing_predicate'`), nie stillschweigend teil-evaluiert.
* **Schedule-Regel über riesigem Treffer-Set**: ResumableJob-Chunks + Aktions-Staffelung (Workflow-Batch-Mechanik wird wiederverwendet, Rate-Setting).

## Performance

Event-Pfad: Regel-Lookup über `(trigger_kind, trigger_ref, subject_type)`-Index im Speicher-Cache (Regeln sind wenige Dutzend, Cache-Invalidierung bei Regeländerung); Einzel-Subjekt-Evaluation in-memory, < 5 ms. Schedule-Pfad: kompilierte SQL nutzt die Fundament-Indizes (der `ConditionCompiler` lehnt Prädikat-Kombinationen ab, die zwangsläufig Seq-Scans über die größten Tabellen erzeugen würden — solche Regeln brauchen ein vorbereitendes Prädikat mit Index; die Ablehnung nennt das konkret). `rule_firings` partitioniert, 12 Monate Retention.

## Security

Regel-CRUD ist `admin` (Regeln sind Systemautomatik mit Massenwirkung). Der Aktionskatalog ist die Sicherheitsgrenze: Neue Aktionen erfordern Code-Review gegen die Verbotsliste (fachliche Entscheidungen). Prädikat-Parameter werden typgeprüft und nie in SQL interpoliert (Query-Builder-Bindings). Traces enthalten Ist-Werte aus dem Katalog — die Firings-Ansicht unterliegt daher `manager` wie andere Katalog-Querschnitte.

## Tests

Prädikat-Doppelform-Tests (SQL-Pfad ≡ In-Memory-Pfad auf Zufallsbeständen — der wichtigste Invariantentest des Moduls). Cooldown-/Dämpfungs-Matrix (Schleifenkonstruktionen müssen in Pausierung enden, als Regressionstests der drei Dämpfungen). Baum-Validierung (unbekannte Prädikate, Tiefe, Typfehler). Dry-Run-Äquivalenz (Dry-Run-Treffer = Echtlauf-Treffer bei eingefrorenem Bestand). Runaway-Monitor-Zeitfenster.

## ADR-Verweise

[ADR-0009](../adr/0009-workflow-definitions-as-code.md) (gemeinsame Entscheidung: Abläufe als Code, Betreiber-Automatik als beschränkte Deklaration — die Rule Engine ist die deklarative Hälfte). Setzt um: Regel 6 (Regel-Wirkungen voll auditiert), Namespace-Hygiene der Tags.

## Offene Punkte

* **Prioritäten/Konfliktauflösung zwischen Regeln**: bewusst vertagt (siehe Edge Case); erst mit Betriebserfahrung entscheiden.
* **Benachrichtigungskanäle** für `notify` (Mail, Gotify, ntfy): Kanal-Abstraktion gehört ins Admin-/Monitoring-Umfeld; hier nur der Aktions-Hook.
* **Regel-Vorlagen** (mitgelieferte Best-Practice-Regeln, deaktiviert ausgeliefert): Betriebsdokumentations-Thema.
