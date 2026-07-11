# Test-Gesamtstrategie

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Dieses Kapitel konsolidiert die Test-Abschnitte aller Module zu einer Strategie: Ebenen, Infrastruktur, Fixtures, CI-Gates. Modulspezifische Testfälle bleiben in den Modulen; hier steht, **wie** getestet wird und was systemweit verpflichtend ist.

## Grundsätze

1. **PostgreSQL ist Teil des Vertrags.** Kein SQLite in Tests — die Spezifikation lehnt sich bewusst an Postgres-Exklusivfeatures (partielle Unique-Indizes, Exclusion Constraints, Partitionierung, pgvector); Tests laufen gegen echte Postgres-Instanzen (Testcontainer), sonst testen sie ein anderes System.
2. **Constraints werden getestet wie Code.** Jeder CHECK, jeder partielle Unique-Index, jedes Exclusion Constraint hat einen Test, der die Verletzung versucht — die Datenbank-Invarianten sind Spezifikationsbestandteil ([core-schema](../database/core-schema.md)).
3. **Pure Services tragen die Fachlast.** Die Architektur legt Intelligenz in pure, deterministische Services (Classifier, Mapper, Aligner, Reducer, Compiler — durchgängiges Muster); diese werden erschöpfend unit-getestet (Fixtures, Property-Tests, Golden Files) — der teuerste Testboden (Integration) bleibt dünn.
4. **Architektur-Tests sind Pflicht-Gates.** Modulgrenzen, Controller-Reinheit, Client-Oberflächen, Registrar-Umgehung — die per Pest-Arch fixierten Regeln aus allen Kapiteln laufen als eigene CI-Stufe; ein Architekturverstoß bricht den Build wie ein roter Unit-Test.
5. **Fakes gegen Interfaces, Fixtures gegen Formate.** Externe Systeme (Connectoren, Worker, Player) werden über ihre Interfaces gefakt (Szenario-Skripte: Ausfall, 429, Duplikate, Drift); externe **Formate** (Disc-Strukturen, Audio, API-Antworten) über eingefrorene Fixtures — nie Live-Systeme in CI.

## Test-Ebenen

| Ebene | Werkzeug | Umfang | Budget |
|---|---|---|---|
| Architektur | Pest Arch | Grenzen, Reinheit, Oberflächen | < 30 s |
| Unit (pure) | Pest | Services, DTOs, Writer (Golden Files), Property-Tests (Mapper/Timeline) | < 2 min |
| Integration | Pest + Postgres/Redis-Testcontainer | Actions (Audit-Atomarität!), Jobs (Idempotenz-Harness), Constraints, Queries | < 10 min |
| Contract | Pest + Fixtures | Connector-Übersetzer, media-tools-JSON, ai-job/v1, OpenAPI-Drift | < 3 min |
| End-to-End | Pest + Compose-Stack | Release-Smoke: Scan→Analyse→Mapping→Watch-State; Assembler-Kette; Backup→Probe; Upgrade-Pfad | < 30 min, Release-Gate |
| Browser | Playwright (schmal) | die drei kritischen Flows: Mapping-Review, Assembly-Werkbank, Setup | < 10 min, Release-Gate |

## Systemweite Pflicht-Harnesse

Diese wiederverwendbaren Harnesse sind Fundamentbestandteil; jedes Modul nutzt sie statt Eigenbau:

* **`assertJobIsIdempotent(job)`** — führt ResumableJobs doppelt aus, vergleicht Datenbank-Snapshots (Regel 10; [architecture/overview.md](../architecture/overview.md)).
* **`assertActionIsAudited(action, input)`** — führt die Action aus und verifiziert Operation+Entries in derselben Transaktion; plus Rollback-Variante (Wurf nach Fachänderung ⇒ nichts persistiert) (Regel 6).
* **Sichtbarkeits-Suite** — registrierbare Leseflächen-Prüfungen gegen den Grant-/Rollen-Fixture-Bestand ([security](../architecture/security.md)); jede neue Lesefläche registriert sich, CI prüft Vollständigkeit über eine Flächen-Inventarliste.
* **Doppelform-Harness** — für alle SQL+In-Memory-Doppelimplementierungen (Rule-Prädikate, Quality-Checks): Ergebnisgleichheit auf Zufallsbeständen.
* **Fake-Connector / Fake-AI-Worker / Fake-Kodi** — Szenario-getriebene Gegenstellen-Simulationen (SDK-, AI-, Player-Kapitel), zentral gepflegt.

## Fixture-Bestände

Drei kuratierte, versionierte Bestände unter `tests/fixtures/`:

* **Disc-Bibliothek**: `disc-analysis/v1`-JSONs realer anonymisierter Strukturen (Serien-BD, Obfuskations-BD, Fassungs-BD, DVD-Box, Play-All-DVD, Doppelfolgen-Disc) mit Golden-Klassifikationen und -Alignments — der Regressionsanker der Disc-Engine; erweitert um jeden real aufgetretenen Problemfall (Betriebs-Feedback-Schleife).
* **Audio-Bestand**: synthetisch generiert im Test-Setup (Sinus/Sweeps mit definierten Tags, Laufzeiten, Defekten — VBR-kaputt, Clipping, Stille-Muster); klein im Repo (Generator statt Binärdateien), deterministisch.
* **Katalog-Bestand**: Seeder für den Referenz-Mix (Serien mit Lücken, Hörbücher aller Chaos-Grade, Dubletten-Konstruktionen, restriktive Bibliothek, Benutzer aller Rollen) — die gemeinsame Bühne für Integration, Sichtbarkeit und E2E.

API-Antwort-Fixtures der Connectoren (Jellyfin 10.8–10.10, ABS 2.x, *arr v3, Stash, Immich, Audnexus) liegen pro Connector mit Versions-Etiketten; ein vierteljährlicher manueller Verifikationslauf gegen echte Instanzen (Compose-Testumgebung in `deploy/testing/`) hält sie ehrlich — dokumentiert als Wartungsprozess, nicht CI.

## Der unverhandelbare Kern

Eine kleine Menge Tests ist als **Invarianten-Suite** markiert und kann von keinem Merge umgangen werden (kein Skip, kein Quarantäne-Tag): das Disc-Kernszenario (6 Folgen, eine geschaut ⇒ genau eine watched, Disc partial — Regel 11); KI-Set-Aktivierung ohne Benutzer schlägt fehl + `is_official`-CHECK (Regel 5); `RecordPlaybackProgress` lehnt Container/Discs ab; Audit-Atomarität; Originale-ro (Storage-Service kennt keine Schreiboperation — Architekturtest); Echo-Oszillations-Test des SDK; Sichtbarkeits-Kernfälle (restriktiv leakt nicht in Suche/Dashboard/Notifications). Diese Suite ist die ausführbare Form der Architekturregeln — wenn sie rot ist, ist es kein Testproblem, sondern ein Architekturbruch.

## CI-Pipeline

Stufen (jede bricht den Build): Lint/Static (Pint, PHPStan Level max, `tsc --noEmit`) → Architektur → Unit → Integration (parallelisiert über Testcontainer-Pool) → Contract (inkl. OpenAPI-Drift, [api/conventions.md](../api/conventions.md)) → Invarianten-Suite (redundant enthalten, separat berichtet). Release-Kandidaten zusätzlich: E2E-Compose (inkl. Upgrade-Pfad-Test mit Vorversions-Snapshot, [migrations](../database/migrations.md)), Browser-Flows, Migrations-Laufzeitbudget. Coverage wird gemessen und berichtet, aber nicht als Gate erzwungen (Coverage-Gates züchten Assertion-freie Tests); stattdessen: neue Module ohne Tests zu ihren spezifizierten Testfällen fallen im Review.

## Edge Cases der Teststrategie selbst

* **Flaky-Tests**: Quarantäne-Tag mit Pflicht-Issue und 14-Tage-Frist (dann fix oder Löschung mit Begründung); die Invarianten-Suite ist von Quarantäne ausgenommen (flaky dort = Alarm).
* **Zeitabhängigkeit**: Carbon-Test-Uhr verpflichtend (keine `sleep`/Echtzeit-Tests); Partitions-/Retention-Logik testet über Zeitreisen.
* **Testcontainer-Ressourcen** (CI-Läufer klein): Integration parallelisiert über einen Container-Pool mit Schema-Templates (Template-DB klonen statt migrieren pro Test — Sekunden statt Minuten).
* **Golden-File-Updates**: bewusster Zwei-Schritt (`--update-goldens` + Review des Diffs) — Golden-Drift ist Spezifikationsänderung, kein Reparatur-Klick.

## Security

Die Testinfrastruktur enthält keine echten Secrets (Fixture-Secrets sind markiert generiert); Fixture-Bestände enthalten keine realen Medieninhalte (Disc-Strukturen sind Navigationsdaten, Audio ist synthetisch) — das Repo bleibt frei von Rechte-Fragen.

## ADR-Verweise

Operationalisiert die Testpflichten aus praktisch allen ADRs; die Invarianten-Suite ist die Laufzeit-Form der Architekturregeln 1–11.

## Offene Punkte

* **Mutation Testing** (Infection) für die puren Kern-Services: wertvoll, CI-Kosten abzuwägen — Pilotlauf auf Mapper/Aligner geplant.
* **Last-/Mengentests** (500k-Dateien-Scan, 20M-Events-Queries): als periodischer Nightly gegen einen generierten Großbestand skizziert; Aufbau nach dem ersten Release.
