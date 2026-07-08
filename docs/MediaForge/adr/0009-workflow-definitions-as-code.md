# ADR-0009: Abläufe als Code, Betreiber-Automatik als beschränkte Deklaration

Status: accepted · Bezug: [modules/workflow-engine.md](../modules/workflow-engine.md), [modules/rule-engine.md](../modules/rule-engine.md)

## Kontext

MediaForge braucht mehrstufige Verarbeitungsketten (Workflows) und betreiberdefinierte Automatik (Regeln). Die Kernfrage beider Module: Wie viel davon ist Code (versioniert, getestet, vom Entwickler), wie viel Daten (UI-editierbar, vom Betreiber)?

## Entscheidung

Zweiteilung entlang der Verantwortung: **Workflow-Definitionen sind PHP-Klassen** (Schrittfolgen, Bedingungen, Kompensation — Code mit Tests und eingefrorener Version pro laufender Instanz); ihre **Parameter** sind Settings. **Regeln sind Daten** (UI-editierbar), aber über eine endliche Grammatik: registrierte Prädikate mit SQL- und In-Memory-Übersetzung, registrierter Aktionskatalog mit Verbotsliste (keine fachlichen Entscheidungen wie Watch-State, Mapping-Bestätigung, Löschung). Regeln entscheiden ob/wann, Workflows wie; Regeln starten Workflows, nie umgekehrt. Konvergenz wird strukturell erzwungen (Cooldown, Actor-Filter gegen Tag-Kaskaden, Runaway-Pausierung).

## Konsequenzen

* Kein UI-Baukasten entartet zur ungetesteten Programmiersprache; kein Betreiber kann per Regel fachliche Invarianten (Regel 5, 11) umgehen.
* Neue Automatik-Fähigkeiten sind bewusste Entwicklungsentscheidungen (Prädikat/Aktion registrieren), keine Konfigurationsunfälle.
* Der Preis: Betreiber können keine neuartigen Abläufe ohne Release bauen — akzeptiert; das Plugin SDK ist das Ventil für Fortgeschrittene.
* Beide Engines teilen Beobachtbarkeits-Muster (Traces, Audit-Actors `rule:*`, Kausalketten in Workflows).

## Erwogene Alternativen

Workflows als Daten/UI-Baukasten (Bedingungen und Kontexte sind Code-Verträge; Baukasten wird schlechte Sprache ohne Tests); Skript-Hooks in Regeln (Sandbox-Aufwand, Unauditierbarkeit); externe Workflow-Engines (Infrastruktur-Overkill, ADR-0002); alles nur Code ohne Regel-Layer (Betreiber-Automatik wäre Feature-Request-Stau).
