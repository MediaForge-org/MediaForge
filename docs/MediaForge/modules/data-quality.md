# Datenqualitätsbewertung

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [database/core-schema.md](../database/core-schema.md), [modules/audit.md](audit.md). Zulieferer: [Fingerprinting](dedup-fingerprinting.md) (Dubletten-Signale), [Audioanalyse](audio-analysis.md) (technische Qualität), [Disc-Engine](disc-engine.md)/[Assembler](audiobook-assembler.md) (Vollständigkeits-Signale).

## Motivation

Ein Katalog mit 300k Einträgen ist nie „fertig gepflegt" — die Frage ist, **wo** die Pflege am nötigsten ist. Ohne Qualitätsbewertung ist Katalogpflege Stochern: Niemand weiß, welche Serien unvollständige Episodendaten haben, welche Hörbücher ohne verifizierte Provider-Mappings laufen, wo KI-Vorschläge unbestätigt altern. Das Modul macht Datenqualität **messbar, adressierbar und priorisierbar**: ein Scoring-Modell pro Entität, aggregierte Sichten pro Bibliothek, und Arbeitslisten, die Reviews dort erzeugen, wo Handeln lohnt. Es ist bewusst ein Mess- und Melde-Modul: Es korrigiert nichts selbst (Korrekturen sind Fach-Actions der Module), es zeigt.

## Problemstellung

**Qualität ist mehrdimensional.** „Gut gepflegt" zerfällt in unabhängige Dimensionen: **Vollständigkeit** (Pflichtfelder, Episodenlücken, fehlende Laufzeiten), **Verlässlichkeit** (Provider-Mappings verifiziert? Kapitel offiziell oder KI? Mapping-Confidence?), **Konsistenz** (Widersprüche: Editionsdauer vs. Summe der Trackdauern, Episodenzahl vs. Provider-Angabe), **technische Qualität** (Bitraten, Auflösungen — aus der Audioanalyse) und **Integrität** (Waisen: Provider-IDs ohne Entität, Audit-Entries ohne Operation — die im Kernschema versprochenen Waisen-Checks leben hier). Ein Einheits-Score, der alles vermengt, wäre unbrauchbar; das Modell muss Dimensionen getrennt halten und trotzdem eine priorisierbare Gesamtsicht bieten.

**Bewertung darf nicht kosten.** Scoring über 300k Items darf weder Live-Queries verlangsamen noch stundenlang rechnen — inkrementell bei Änderung, vollständig nur als Batch.

**Regeln altern.** Was „vollständig" heißt, ändert sich mit Modulen und Releases (neue Pflicht-Dimension „Disc-Mapping-Abdeckung" kam mit der Disc-Engine). Die Prüfungen müssen registrierbar und versioniert sein wie Prädikate der Rule Engine.

## Analyse bestehender Lösungen

***arr-Health/Wanted-Listen**: das Vorbild für „Lücken als Arbeitsliste" (fehlende Episoden, Cutoff-Unmet) — übernommen als Vollständigkeits-Dimension mit Arbeitslisten-UI. **Kodi/Jellyfin-Bibliotheks-Statistiken**: Zählwerke ohne Qualitätsbegriff — Negativ-Referenz. **Datenqualitäts-Frameworks** (Great-Expectations-Klasse): Checks als deklarative Suites mit Erfolgsraten — das Suite-Konzept (registrierte Checks, versioniert, mit Ergebnis-Historie) wird übernommen, die Infrastruktur nicht (Overkill; MediaForge-Checks sind SQL/PHP im Monolith).

## Architekturentscheidung

**Checks als registrierte Klassen** (`QualityCheckInterface`): Name, Dimension, Subjekt-Typ, Gewicht, `evaluate(subject): CheckResult` (pass/fail/inapplicable + Detail) und — für Batch — eine SQL-Formulierung analog zum Prädikat-Doppelmuster der [Rule Engine](rule-engine.md) (eine Definition, zwei Ausführungsformen; das Muster wird bewusst wiederverwendet statt neu erfunden). Module registrieren ihre Checks per Service Provider: Der Core bringt die Feld-/Integritäts-Checks, die Disc-Engine „alle Episoden-Kandidaten gemappt?", der Assembler „aktives Chapter Set vorhanden? offiziell?", das Fingerprinting „content_hash vorhanden?".

**Scores pro Dimension**, materialisiert je Entität (`quality_scores`): gewichteter Anteil bestandener anwendbarer Checks, 0–1, plus Zeitstempel und Check-Set-Version. Kein Gesamt-Score über Dimensionen als gespeicherte Zahl — die Gesamtsicht ist eine UI-Gewichtung (Betreiber-Preset), keine Datenbank-Referenzwert; das verhindert, dass ein willkürlicher Mix als objektiver Stand missverstanden wird.

**Inkrementell + Batch**: Listener auf die Fundament-Events (`MediaItemUpdated`, `DiscMappingConfirmed`, `ChapterSetActivated`, …) re-evaluieren betroffene Subjekte (Queue `default`, debounced pro Subjekt 60 s); der wöchentliche Batch (`RunQualitySweepJob`, ResumableJob) deckt Drift und neue Check-Versionen ab und führt die Waisen-Integritätsprüfungen aus (die nur als Batch sinnvoll sind).

**Arbeitslisten statt Alarmflut**: Check-Fehlschläge erzeugen **keine** Review-Tasks pro Item (300k offene Reviews wären Rauschen). Stattdessen speisen sie Arbeitslisten-Sichten (filterbar, sortiert nach Gewicht×Popularität); nur definierte Eskalationen erzeugen Reviews (Integritäts-Waisen; Konsistenz-Widersprüche über Schwellwert — Dinge, die auf Fehler statt Unvollständigkeit deuten).

## Alternativen

**Ein Gesamt-Score in der DB**: siehe oben, verworfen. **Reviews pro Fehlschlag**: Alarmflut, verworfen. **Qualität als Rule-Engine-Anwendung** (Checks = Regeln): verlockend nah, aber Regeln sind Betreiber-Automatik mit Aktionen, Checks sind Entwickler-definierte Messungen mit Historie — die Vermischung würde beide Kataloge verwässern; die gemeinsame Technik (Prädikat-Doppelform) wird geteilt, die Register bleiben getrennt. **Externe DQ-Werkzeuge**: Infrastruktur-Overkill.

## Datenmodell und SQL-Schema

```sql
CREATE TABLE quality_scores (
    id            CHAR(26) PRIMARY KEY,
    subject_type  TEXT        NOT NULL,
    subject_id    CHAR(26)    NOT NULL,
    dimension     TEXT        NOT NULL
        CHECK (dimension IN ('completeness','reliability','consistency','technical','integrity')),
    score         NUMERIC(4,3) NOT NULL,
    checks_passed INTEGER     NOT NULL,
    checks_failed INTEGER     NOT NULL,
    failed_checks JSONB       NOT NULL DEFAULT '[]',   -- [{check, detail}] für die Arbeitslisten-Anzeige
    check_set_version TEXT    NOT NULL,
    evaluated_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (subject_type, subject_id, dimension)
);

CREATE INDEX quality_scores_worklist
    ON quality_scores (dimension, score ASC, subject_type);

CREATE TABLE quality_sweep_runs (
    id            CHAR(26) PRIMARY KEY,
    check_set_version TEXT  NOT NULL,
    subjects_evaluated BIGINT NOT NULL DEFAULT 0,
    integrity_findings INTEGER NOT NULL DEFAULT 0,
    started_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    finished_at   TIMESTAMPTZ
);
```

`failed_checks` ist Anzeige-JSONB (Regel-8-konform); der Befund ist reproduzierbar (Checks sind deterministisch über dem Ist-Zustand — es gibt bewusst keine Check-Ergebnis-Historie pro Item; die Score-Historie auf Bibliotheksebene hält das Admin-Dashboard als Zeitreihe eigener Verantwortung).

## Laravel-Klassen

| Klasse | Typ | Vertrag |
|---|---|---|
| `QualityCheckRegistry` | Service | Registrierung, Set-Versionierung (Hash über registrierte Checks + Gewichte — Versionswechsel triggert Batch) |
| `QualityCheckInterface` | Interface | `dimension()`, `subjectType()`, `weight()`, `evaluate()`, `toQuery()` |
| `EvaluateSubjectQualityJob` | Job (`default`, debounced) | inkrementelle Re-Evaluation |
| `RunQualitySweepJob` | ResumableJob (`default`) | Batch in Chunks; Integritäts-Checks; Sweep-Protokoll |
| `IntegrityCheckInterface` | Interface | Batch-only-Checks (Waisen-Queries) mit Eskalations-Review |
| Kern-Checks | Klassen | `RequiredFieldsCheck`, `EpisodeGapCheck` (Provider-Episodenliste vs. Katalog), `ProviderMappingVerifiedCheck`, `RuntimeConsistencyCheck`, `OrphanedProviderIdsCheck`, `AuditEntryOrphansCheck` u. a. |

## API und UI

API: `GET /api/v1/quality/summary?library=` (Dimension-Scores aggregiert), `GET /api/v1/quality/worklist?dimension=&subject_type=&sort=` (paginierte Arbeitsliste, manager). UI **`Quality/Dashboard`**: Dimension-Kacheln pro Bibliothek (Score, Trend seit letztem Sweep, Top-Fehlschlag-Checks), Drilldown in Arbeitslisten; jede Zeile verlinkt direkt in die Korrektur-UI des zuständigen Moduls (Episodenlücke → Katalog-Editor; unbestätigtes Disc-Mapping → Mapping-Review; KI-Kapitel unbestätigt → Assembly-Werkbank) — das Modul misst, die Module heilen. Popularitäts-Sortierung (zuletzt gespielte/gemerkte Items zuerst) macht die Liste nach Nutzen abarbeitbar statt alphabetisch endlos.

## Edge Cases

* **Nicht anwendbare Checks** (Episodenlücken-Check auf einem Film): `inapplicable` zählt weder als pass noch fail — Scores vergleichen nur Anwendbares; ein Item ohne anwendbare Checks einer Dimension hat dort keinen Score (NULL, nicht 1.0).
* **Check-Set-Versionswechsel mitten im Betrieb**: gemischte `check_set_version`-Stände sind zulässig und sichtbar; der getriggerte Batch konvergiert; Arbeitslisten filtern optional auf aktuelle Version.
* **Provider-Lücken vs. Katalog-Lücken** (Provider kennt Episode 14 nicht, Katalog auch nicht): der Gap-Check arbeitet gegen die Provider-Episodenliste als Referenz und markiert Provider-Unvollständigkeit getrennt (`consistency`-Fund statt `completeness`-Fehlschlag) — die Referenz selbst kann falsch sein, und das Modell sagt das ehrlich.
* **Massenimport drückt Scores**: erwartbar und korrekt; das Dashboard zeigt den Import-Einbruch als Trend-Ereignis (Annotation über die Audit-Korrelation), damit niemand einen „Qualitätsverfall" jagt, der ein Wachstumsschub ist.

## Performance

Inkrementell: eine Re-Evaluation kostet die Checks eines Subjekts (< 20 ms, alles indexgestützt). Batch: SQL-Formulierungen der Checks laufen als Set-Operationen (ein Query pro Check über den Chunk, nicht pro Item) — der Sweep über 300k Items bleibt unter einer Stunde auf Referenz-Hardware; der Seq-Scan-Wächter des `ConditionCompiler`-Musters gilt auch hier. `quality_scores` (~5 Zeilen/Item) bleibt unter 2M Zeilen, Worklist-Index trägt die UI.

## Security

Arbeitslisten und Scores sind `manager`-Sicht (Katalog-Querschnitt). Checks führen keine Fremdaufrufe aus (reine Bestandsmessung; die Provider-Referenzlisten kommen aus dem Enrichment-Bestand, nicht live). Integritäts-Eskalationen laufen als System-Actor mit voller Audit-Kette.

## Tests

Check-Doppelform-Tests (evaluate() ≡ toQuery(), wie Rule Engine). Fixture-Bestände mit konstruierten Mängeln je Dimension ⇒ erwartete Scores und Arbeitslisten-Einträge. Inapplicable-Semantik. Versionswechsel-Konvergenz. Waisen-Checks gegen absichtlich beschädigte Test-Bestände (FK-lose Provider-IDs, Operation-lose Audit-Entries).

## ADR-Verweise

Wiederverwendet das Doppelform-Muster aus [ADR-0009](../adr/0009-workflow-definitions-as-code.md)-Kontext (Rule Engine); Integritäts-Checks lösen die Waisen-Versprechen aus [database/core-schema.md](../database/core-schema.md) und [modules/audit.md](audit.md) ein.

## Offene Punkte

* **Score-Historie auf Item-Ebene** (Verläufe pro Subjekt): bewusst weggelassen (Speicher vs. Nutzen); Bibliotheks-Trends übernimmt das Admin-Dashboard — dort spezifizieren.
* **Gewichts-Presets** (welche Checks wie schwer wiegen): Auslieferungs-Defaults sind mit Betriebserfahrung zu kalibrieren; bis dahin konservative Gleichgewichtung je Dimension.
