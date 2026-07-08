# Wiederkehrende Architekturmuster: Vertragsreferenz

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Vertiefung zu [getting-started.md](getting-started.md). MediaForge verwendet über alle Module hinweg eine kleine Zahl wiederkehrender Muster — bewusst dieselbe Mechanik statt N Neuerfindungen (die Modulkapitel nennen das explizit: „viertes Auftreten des Registry-Musters", „dasselbe Muster wie Disc-Klassifikator und Track-Sequencer"). Dieses Dokument konsolidiert jedes Muster **einmal** als Vertrag mit Checkliste — Module referenzieren hierher, statt das Muster in jedem Kapitel neu zu erklären.

## Muster 1: Registry (vier Anwendungen)

**Vertrag**: Ein Interface mit `key()`/Identifikation, einer typspezifischen Ausführungsmethode und optionalen Metadaten (Rollen, Gewicht, Intervall); Implementierungen registrieren sich über den Service Provider ihres Heimatmoduls; ein zentraler `*Registry`-Service sammelt sie beim Boot und validiert Eindeutigkeit/Konsistenz.

| Anwendung | Interface | Registry-Service | Konsument |
|---|---|---|---|
| Rule-Prädikate | `PredicateInterface` | `PredicateRegistry` | [Rule Engine](../modules/rule-engine/predicate-reference.md) |
| Quality-Checks | `QualityCheckInterface` | `QualityCheckRegistry` | [Datenqualität](../modules/data-quality.md) |
| Health-Checks | `HealthCheckInterface` | `HealthCheckRegistry` | [Health Monitoring](../modules/health-monitoring/health-check-reference.md) |
| Dashboard-Cards | `DashboardCardInterface` | `DashboardCardRegistry` | [Admin-Dashboard](../modules/admin-dashboard.md) |

**Warum immer dasselbe Muster**: Ein Modul, das eine neue Fachdimension beisteuert (ein Disc-Mapping-Vollständigkeits-Check, ein `disc.mapping_status`-Prädikat, ein Connector-Health-Befund, eine Acquisition-Karte), tut das **ohne** die Registry-Codebasis anzufassen — es implementiert das Interface und registriert sich im eigenen Service Provider. Die Registry-Seite kennt keine Modultypen, nur den Vertrag (dieselbe Eigenschaft, die die Modulgrenzen-Architektur-Tests verlangen, [architecture/overview.md](../architecture/overview.md)).

**Checkliste für eine neue Registry-Anwendung** (bevor ein fünftes Muster erfunden wird, prüfen: passt eines der vier?): (1) Gibt es eine begrenzte, aber über Module wachsende Menge von „Dingen, die geprüft/bewertet/angezeigt werden"? (2) Sollen Module unabhängig voneinander Beiträge liefern, ohne den zentralen Code zu ändern? (3) Braucht jeder Beitrag dieselbe Metadaten-Form (Subjekt-Typ, Gefahrenklasse/Gewicht, Ausführungsmethode)? Drei Ja ⇒ Registry-Muster; sonst eine gezielte Prüfung, ob eines der vier bestehenden Interfaces erweiterbar ist, bevor ein fünftes entsteht.

## Muster 2: Pure Service (Klassifikator/Mapper/Aligner/Compiler)

**Vertrag**: Eine Klasse ohne Konstruktor-Abhängigkeiten auf I/O (keine DB, kein HTTP, keine Queue) mit einer oder wenigen Methoden, die DTOs entgegennehmen und DTOs zurückgeben — deterministisch, vollständig fixture-testbar ohne Datenbank oder Mocks.

| Anwendung | Klasse | Modul |
|---|---|---|
| Disc-Klassifikation | `PlaylistClassifier` | [Disc-Engine](../modules/disc-engine/classification-rules.md) |
| Episoden-Mapping | `EpisodeMapper` | [Disc-Engine](../modules/disc-engine/mapping-algorithm.md) |
| Playback-Spannen | `PlaybackSpanReducer` | [Disc-Engine](../modules/disc-engine/playback-translation.md) |
| Track-Sequenzierung | `TrackSequencer` | [Assembler](../modules/audiobook-assembler/sequencing-rules.md) |
| Kapitel-Alignment | `ChapterAligner` | [Assembler](../modules/audiobook-assembler/alignment-algorithm.md) |
| Merge-Entscheidungen | `MergeEngine` | [Enrichment](../modules/enrichment.md) |
| Einbettungstext | `EmbeddingTextBuilder` | [Suche](../modules/search/embedding-spec.md) |
| Bedingungs-Kompilierung | `ConditionCompiler` | [Rule Engine](../modules/rule-engine/predicate-reference.md) |

**Warum**: Die fachliche Intelligenz des Systems (Heuristik-Kaskaden, Scoring-Formeln, Alignment-Algorithmen) ist überall dort konzentriert, wo sie am gründlichsten getestet werden kann — pure Funktionen erlauben Property-Tests und Golden-Files ohne Testcontainer-Overhead. Jobs und Actions **um** einen pure Service herum erledigen nur Orchestrierung (Laden, Speichern, Events) — die Trennung ist wörtlich aus dem Disc-Engine-Kapitel: „die gesamte fachliche Intelligenz der Engine liegt in reinen, deterministischen Funktionen; Jobs und Actions erledigen nur Orchestrierung und Persistenz."

**Checkliste**: Eine neue Fachberechnung (Scoring, Klassifikation, Alignment) ist ein Kandidat für einen pure Service, wenn sie (1) aus Eingabe-DTOs einen Ergebnis-DTO berechnet, (2) keine Datenbank-/Netzwerk-Aufrufe während der Berechnung braucht (Daten werden **vorher** geladen, **nachher** gespeichert), (3) bei gleicher Eingabe immer dasselbe Ergebnis liefert. Trifft das zu, gehört die Berechnung nicht in eine Action oder einen Job, sondern in einen benannten, ohne DI-Container instanziierbaren Service.

## Muster 3: SQL/In-Memory-Doppelform

**Vertrag**: Eine fachliche Bedingung existiert in **zwei** Ausführungsformen — einer SQL-Übersetzung (`toQuery(Builder): Builder`, für Batch-Verarbeitung über große Bestände) und einer In-Memory-Auswertung (`evaluate(Model): bool`, für Einzel-Subjekt-Prüfung am Event) — beide aus **einer** Definition, mit einem Contract-Test, der Ergebnisgleichheit auf Zufallsbeständen erzwingt.

| Anwendung | Interface | Modul |
|---|---|---|
| Regel-Bedingungen | `PredicateInterface::toQuery()`/`evaluate()` | [Rule Engine](../modules/rule-engine/predicate-reference.md) |
| Qualitäts-Checks | `QualityCheckInterface::toQuery()`/`evaluate()` | [Datenqualität](../modules/data-quality.md) |

**Warum zwei Formen statt einer**: Ein Event („diese eine Datei wurde geändert") verlangt eine schnelle Einzel-Prüfung; ein Batch/Sweep über 300k Items verlangt eine mengenbasierte SQL-Formulierung (sonst 300k Einzel-Aufrufe mit N+1-Charakter). Beide Pfade **müssen** dasselbe Ergebnis liefern — das ist keine Performance-Optimierung, die man später nachziehen kann, sondern ein Korrektheitsvertrag von Anfang an (Modulkapitel: „der wichtigste Invariantentest des Moduls").

**Checkliste für eine neue Doppelform-Anwendung**: Nur einführen, wenn wirklich **beide** Ausführungskontexte real gebraucht werden (Event-Einzelfall **und** Batch-Sweep) — eine Bedingung, die nur je im Batch gebraucht wird, braucht keine `evaluate()`-Form (unnötige Pflege-Verdopplung).

## Muster 4: Kandidaten/Aktiv-Auswahl mit Herkunft

**Vertrag**: Für ein Subjekt existieren mehrere konkurrierende Kandidaten unterschiedlicher Herkunft (Provider, Heuristik, manuell, KI); genau einer ist „aktiv" (partieller Unique-Index: „höchstens ein aktiver Kandidat je Subjekt"); Aktivierung ist immer eine auditierte Action, nie ein impliziter Seiteneffekt.

| Anwendung | Kandidaten-Tabelle | Aktiv-Flag | Modul |
|---|---|---|---|
| Disc-Episoden-Mapping | `disc_episode_mappings` | `status='confirmed'` (partieller Unique) | [Disc-Engine](../modules/disc-engine.md) |
| Kapitel-Strukturen | `chapter_sets` | `is_active` (partieller Unique) | [Assembler](../modules/audiobook-assembler.md) |
| Cover/Asset-Kandidaten | `asset_candidates` | `is_active` (partieller Unique je Slot) | [Enrichment](../modules/enrichment.md) |
| Beziehungs-Kanten | `entity_relations` | `status='confirmed'` | [Knowledge Graph](../modules/knowledge-graph/relation-reference.md) |

**Warum**: Konkurrierende Kandidaten (welche Kapitelliste ist richtig? welches Cover?) verlangen Vergleichbarkeit (alle Kandidaten bleiben sichtbar für den Vergleich) **und** Eindeutigkeit (genau eine gilt). Die Datenbank garantiert die Eindeutigkeit (partieller Unique-Index — [Katalog der partiellen Unique-Indizes](../database/schema-reference.md#katalog-der-partiellen-unique-indizes-höchstens-eins-muster)), die Action garantiert die Nachvollziehbarkeit (Audit, Provenienz-Feld). KI-Kandidaten sind in jeder Anwendung dieses Musters strukturell an eine menschliche Bestätigung gebunden (nie automatische Aktivierung) — die wörtliche Wiederholung von Architekturregel 5 über vier verschiedene Module hinweg.

**Checkliste**: Ein neues „mehrere Quellen, eine aktive Auswahl"-Problem ist ein Kandidat für dieses Muster, wenn (1) Herkunft für die Entscheidung relevant bleibt (nicht nur für die Anzeige), (2) ein Rollback „zurück zur vorherigen Wahl" ein legitimer Anwendungsfall ist (Kandidaten werden nie gelöscht, nur deaktiviert), (3) mindestens eine Quelle KI-generiert sein könnte (dann ist die Bestätigungspflicht nicht verhandelbar).

## Muster 5: Preflight/Sinnhaftigkeitsprüfung vor teurer Arbeit

**Vertrag**: Vor einer teuren, irreversiblen oder GPU-/I/O-intensiven Operation läuft eine günstige Prüfung, die die Operation ablehnt, wenn sie sinnlos oder riskant wäre — mit fachlicher Begründung, nicht als Fehler, sondern als eigener Fachzustand.

| Anwendung | Preflight | Modul |
|---|---|---|
| Audio-Upscale | `PreflightUpscaleJob` (Bandbreite/Rauschen/Clipping bereits ausreichend?) | [Audio-Upscaler](../modules/audio-upscaler/profiles-metrics.md) |
| Disc-Klassifikation | Konfidenz-Schwellenprüfung vor Auto-Mapping | [Disc-Engine](../modules/disc-engine/classification-rules.md) |
| Backup-Speicherplatz | Schätzprüfung vor Encode | [Audio-Upscaler](../modules/audio-upscaler.md) (Edge Case „Plattenplatz") |

**Warum**: GPU-Stunden, NAS-I/O und menschliche Review-Zeit sind die knappsten Ressourcen des Systems (Heimserver-Kontext); ein Preflight, der eine sinnlose Operation für einen Bruchteil der Kosten erkennt, ist strukturell billiger als die Operation selbst abzubrechen.

## Zusammenfassung: wann welches Muster

```
Neue Fachdimension, die Module unabhängig beisteuern? ────────► Registry (Muster 1)
Neue deterministische Berechnung ohne I/O?           ────────► Pure Service (Muster 2)
Bedingung sowohl für Einzel-Event als auch Batch?     ────────► SQL/In-Memory-Doppelform (Muster 3)
Mehrere Quellen konkurrieren um eine aktive Auswahl?         ────────► Kandidaten/Aktiv-Auswahl (Muster 4)
Teure Operation mit möglichem Sinnlosigkeits-Fall?    ────────► Preflight (Muster 5)
```

Ein neues Modul, das eines dieser fünf Probleme löst, ohne das entsprechende Muster zu verwenden, braucht eine explizite Begründung im PR (warum passt keines der fünf?) — die Vermutung ist immer zugunsten der Wiederverwendung, nicht der Neuerfindung (dieselbe Haltung, die die Modulkapitel selbst durchgängig zeigen: „bewusst dieselbe Mechanik").

## Tests

Jedes Muster hat eine generische Test-Vorlage im Test-Harness (`testing.md`): Registry-Contract-Test (Eindeutigkeit der `key()`-Werte, Vollständigkeits-Abgleich gegen Dokumentation — wie bei Health-Checks/API-Routen), Doppelform-Äquivalenz-Test (Zufallsbestand-Vergleich `toQuery()` vs. `evaluate()`), Kandidaten-Invarianten-Test (partieller Unique-Index-Verletzung muss scheitern, KI-Aktivierung ohne Bestätigung muss scheitern). Ein neues Modul, das ein bestehendes Muster verwendet, erbt die passende Test-Vorlage, statt sie neu zu schreiben.
