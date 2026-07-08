# Developer Handbook: Setup, Standards, Release

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Zielgruppe: Entwickler, die an MediaForge selbst arbeiten. Dieses Kapitel bündelt Entwicklungsumgebung, Code-Standards und Release-Prozess; die Teststrategie steht in [testing.md](testing.md), das Plugin-SDK in [plugin-sdk.md](plugin-sdk.md).

**Vertiefungen**: [Code-Standards: vollständige Referenz](coding-standards.md) · [Modul-Anlage: durchgerechnetes Kochrezept](module-cookbook.md) · [Wiederkehrende Architekturmuster: Vertragsreferenz](contracts-reference.md)

## Leseweg für neue Entwickler

Verbindlicher Einstieg (die Reihenfolge aus der Masterdatei, hier mit Auftrag): (1) [Masterdatei](../MediaForge_Master_Engineering.md) vollständig — insbesondere die elf Architekturregeln, sie sind der Prüfmaßstab jedes Reviews; (2) [architecture/overview.md](../architecture/overview.md) — Modulschnitt, Action/Job/Event-Verträge; (3) [database/core-schema.md](../database/core-schema.md) — Schema-Konventionen; (4) [modules/audit.md](../modules/audit.md) — weil jede Action darauf aufbaut; (5) das Modul des ersten Arbeitsauftrags. Wer eine fachliche Frage hat, prüft zuerst das Glossar und das betreffende Modulkapitel — die Dokumentation ist die Spezifikation, nicht deren Beschreibung: Weicht Code von ihr ab, ist eines von beiden falsch und der Fund ein Issue.

## Entwicklungsumgebung

Ein Setup-Weg, keine Varianten-Matrix: **Docker-Compose-Dev-Stack** (`deploy/dev/docker-compose.yml`) — dieselbe Topologie wie Produktion ([deployment](../architecture/deployment.md)) plus Dev-Komfort: Quellcode-Bind-Mount mit Hot-Reload (Vite für Vue, PHP-FPM-Neustart-frei), Mailpit, Xdebug (opt-in via Env), Fixture-Medienbaum (generiert, [testing.md](testing.md)) als `media_*`-Mounts. `make setup` orchestriert: Container, Composer/NPM-Install, Migrationen, Katalog-Seeder, Test-User (admin/manager/member). Ziel: **Clone bis lauffähiges System < 15 Minuten** — dieselbe Messlatte wie die Endbenutzer-Installation; der Dev-Stack ist Teil des Compose-Integrationstests, damit er nicht verrottet.

IDE-Grundausstattung (dokumentiert, nicht erzwungen): PHPStan/Pint/Vue-TSC-Integration; die `.editorconfig` und Git-Hooks (`pre-commit`: Pint + betroffene Architektur-Tests) liegen im Repo.

## Code-Standards

**PHP**: PHP 8.4, `declare(strict_types=1)` überall; Pint mit Laravel-Preset (die Formatierungsfrage ist damit beendet); PHPStan auf maximalem Level ohne Baseline-Wachstum (neue Fehler = Build-Bruch; die Baseline schrumpft nur). Namenskonventionen aus den Fundament-Kapiteln sind verbindlich: Actions `VerbObjekt` (`ConfirmDiscEpisodeMapping`), Jobs `VerbObjektJob`, Events `ObjektPartizip` (`DiscImageAnalyzed`), DTOs `final readonly`, Enums backed. Konstruktor-Injektion ausschließlich; Facades nur in dünnen Randschichten (Controller, Kommandos) — Services und Actions sind facade-frei (Testbarkeit).

**Vue/TypeScript**: `<script setup lang="ts">` durchgängig; Props-Verträge als exportierte Interfaces je Seite (die in den Modulkapiteln spezifizierten Props-Verträge sind diese Interfaces); keine Fach-Berechnung im Frontend (Regel 2 — wenn eine Komponente rechnet, gehört das Ergebnis in die Props); Komponenten-Bibliothek: die schmale MediaForge-eigene Basis (`resources/js/components/base/`) — kein UI-Framework-Wildwuchs, Erweiterungen der Basis sind Review-pflichtig.

**Dokumentationspflicht im Code**: PHPDoc nur, wo Signaturen nicht genügen (Invarianten, Einheiten — `position_ms`!, Nebenwirkungen); der wichtigste Kommentar ist der Verweis aufs Modulkapitel bei nicht offensichtlichen Fachentscheidungen (`// Siehe docs/MediaForge/modules/disc-engine.md, Confidence-Zonen`). Änderungen an spezifiziertem Verhalten ändern **zuerst** die Spezifikation (Doku-PR-Anteil ist Merge-Bedingung bei fachlichen Änderungen).

## Arbeitsablauf

Trunk-basiert mit kurzen Feature-Branches; PR-Pflicht mit den CI-Gates aus [testing.md](testing.md). Review-Checkliste (die Essenz der Architekturregeln als Frageform): Schreibt hier etwas außerhalb einer Action? Ist der Job doppelt ausführbar? Trägt jedes fremdbefüllte Feld seine Herkunft? Wäre dieser Controller-Zweig Fachlogik? Braucht diese JSONB-Spalte einen FK? Ist die neue Lesefläche in der Sichtbarkeits-Suite? Commits: Conventional Commits (`feat(disc-engine): …`) — die Release-Notes werden daraus generiert.

**ADR-Prozess** ([Masterdatei](../MediaForge_Master_Engineering.md), Dokumentkonventionen): Architekturentscheidungen über Modulgrenzen oder gegen bestehende Regeln brauchen eine ADR **vor** der Implementierung; der PR verlinkt sie. Der ADR-Index der Masterdatei ist die vollständige Liste.

## Modul-Anlage (Kochrezept)

Neues Fachmodul in acht Schritten: (1) Modulkapitel unter `docs/MediaForge/modules/` nach dem verbindlichen Template — die Spezifikation entsteht zuerst (dieses Handbuch ist der Beweis, dass das geht); (2) Namespace `app/Modules/<Name>` mit Service Provider; (3) Migrationen nach [migrations](../database/migrations.md)-Konventionen; (4) Models guarded, Actions auf `AuditableAction`, Jobs auf `ResumableJob`; (5) Registry-Beiträge (Health-Checks, Quality-Checks, Dashboard-Card, ggf. Prädikate) — die vier Registries sind der Standard-Integrationsweg; (6) Architektur-Tests der eigenen Grenzen erweitern; (7) Testfälle des Kapitels implementieren, Invarianten-Kandidaten markieren; (8) Masterdatei-TOC-Status aktualisieren. Ein Modul ohne Kapitel, ohne Registry-Beiträge oder ohne Architektur-Tests ist unvollständig — unabhängig davon, ob es „funktioniert".

## Release-Prozess

Semantische Versionierung des Produkts (die API versioniert unabhängig, [api/conventions.md](../api/conventions.md)). Release-Ablauf: Version-Bump + Changelog-Generierung → Release-CI (E2E, Browser, Upgrade-Pfad, Migrations-Budget, Image-Builds mit SBOM/Signierung) → Tag → Registry-Push (`vX.Y.Z`, `latest` nachgezogen) → Release-Notes mit Backfill-Hinweisen („Feature X vollständig nach Backfill Y", [migrations](../database/migrations.md)) und Breaking-Sektion (erwartet leer innerhalb einer Major). Support-Politik: aktuelle Minor erhält Fixes; Upgrade-Quellspanne gemäß Migrations-Kapitel (offener Punkt dort). `edge`-Images entstehen aus main nach jedem grünen CI-Lauf — ausdrücklich ohne Upgrade-Garantien.

## Edge Cases

* **Spezifikation und Code divergieren im Betrieb entdeckt**: Issue mit `spec-drift`-Label; Entscheidung (Code falsch vs. Spez veraltet) fällt im Review, beides wird synchron korrigiert — Drift-Issues altern nicht (14-Tage-Regel wie Flaky-Tests).
* **Notfall-Fix ohne vollen Release-Lauf**: Patch-Releases dürfen die Browser-Stufe überspringen, nie E2E/Upgrade-Pfad — der abgesicherte Upgrade-Weg ist genau im Notfall am wichtigsten.
* **Abhängigkeits-Updates** (Laravel-Major, Postgres-Major): eigener Migrationsfahrplan als ADR; Postgres-Major zusätzlich im Deployment-Kapitel (pg_upgrade-Anleitung) — nie beiläufig in einem Feature-Release.

## Security

Entwicklungs-Secrets sind generierte Dummies (`make setup`); echte Zugangsdaten haben im Dev-Stack nichts verloren (Fixture-Connectoren zeigen auf die Fake-Gegenstellen). Abhängigkeits-Audit (composer audit, npm audit) läuft in CI als Warnstufe, vor Releases als Gate. Der Umgang mit Sicherheitsmeldungen von außen (security.md im Repo-Root, Kontakt, Disclosure-Frist) ist Release-Voraussetzung der 1.0.

## Tests

Dieses Kapitel selbst wird getestet durch: den Dev-Stack-Anteil des Compose-Integrationstests (Setup-Messlatte), die Pre-Commit-Hook-Definitionen im Repo (Hook-Skripte haben Smoke-Tests) und die Release-CI-Definition (der Release-Prozess ist Pipeline-Code, kein Wiki-Text).

## ADR-Verweise

Bündelt die Arbeitsfolgen aus allen ADRs; neue ADRs entstehen nach dem hier beschriebenen Prozess.

## Offene Punkte

* **Contribution-Modell** (externe PRs, CLA-Frage, Maintainer-Struktur): vor Open-Source-Veröffentlichung zu klären.
* **Vorlagen-Repo für Plugins** (Skeleton mit PluginTestCase): gehört zum Plugin-SDK-Release.
* **Deutsch/Englisch-Frage der Codebasis-Doku**: Spezifikation ist deutsch (dieses Handbuch), Code englisch; ob README/Contributing englisch werden (Community-Reichweite), ist vor Veröffentlichung zu entscheiden.
