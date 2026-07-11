# Admin-Dashboard

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: praktisch alle Module — das Dashboard ist reine Anzeige- und Einstiegsfläche über deren Daten; es besitzt **keine eigene Fachlogik** (Architekturregel 2 in Reinform: alles Serverseitige kommt vorgerechnet als Props). Design-Grundlagen: [ui/design-system.md](../ui/design-system.md), [ui/page-catalog.md](../ui/page-catalog.md).

## Motivation

Jedes Modul hat Betriebssichten (Connector-Aktivität, Workflow-Liste, Review-Inbox, Health-Befunde, Quality-Arbeitslisten, Backup-Status) — verstreut sind sie wertlos, weil niemand acht Seiten abklappert. Das Admin-Dashboard ist die eine Startseite für Betreiber und Manager: **Zustand auf einen Blick, Arbeit auf einen Klick.** Es aggregiert, verlinkt und priorisiert; es dupliziert keine Modul-UIs, sondern führt in sie hinein (das *arr-„System"-Seiten-Muster, konsequent zu Ende gedacht).

## Problemstellung

**Rollen-Perspektiven.** `admin` will Systemzustand (Health, Queues, Backups, Connectoren); `manager` will Arbeitsvorrat (Reviews, Mapping-Lücken, Quality-Listen, wartende Workflows); beide Rollen überlappen. Das Dashboard braucht Rollen-Zuschnitt ohne zwei getrennte Produkte.

**Informations-Ökonomie.** Ein Dashboard, das alles zeigt, zeigt nichts. Jede Kachel muss die Frage beantworten „Muss ich handeln — und wo?"; reine Bestandszahlen („12.482 Filme") sind Deko und fliegen raus bzw. in eine bewusst nachgeordnete Statistik-Sektion.

**Kein Schatten-Backend.** Die Versuchung: das Dashboard baut eigene Aggregations-Queries quer über Modultabellen. Das koppelt es an Interna und bricht bei jeder Modul-Änderung. Es braucht einen definierten Liefervertrag der Module.

## Architekturentscheidung

**Dashboard-Cards als registrierte Provider** (viertes Auftreten des Registry-Musters — Rule-Prädikate, Quality-Checks, Health-Checks, jetzt Cards; bewusst dieselbe Mechanik): Jedes Modul registriert `DashboardCardInterface`-Implementierungen mit `key()`, `roles()`, `data(): CardData` (Kennzahl, Zustand, Handlungs-Link, optional Sparkline-Metrik-Key) und `cacheTtl()`. Das Dashboard rendert registrierte Cards nach Rollen-Filter und Nutzer-Anordnung — es kennt keine Modultabellen, nur den Vertrag. Neue Module bringen ihre Cards mit; das Dashboard-Kapitel muss dafür nie angefasst werden.

**Struktur der Seite** (normativ): (1) **Zustandszeile** — der aggregierte Health-Status ([health-monitoring](health-monitoring.md)) plus Verursacher-Chips; immer oben, für alle Rollen. (2) **Arbeitsvorrat** — Review-Inbox-Zähler nach Typ (Disc-Mappings, Dubletten, Sequenzen, Konflikte, Kapitel — jeder Chip verlinkt gefiltert), wartende Workflows, Quality-Top-Listen; die Manager-Hälfte. (3) **Betrieb** — Queue-/Worker-Karte (Horizon-Auszug: Tiefen, Failed-Serie), Connector-Karte (Instanz-Ampeln, Outbox-Rückstau), Acquisition-Karte ([arr-family](../connectors/arr-family.md)-Übersicht kompakt), Backup-Karte, AI-Karte (Worker/Modelle); die Admin-Hälfte. (4) **Aktivität** — jüngste bemerkenswerte Audit-Operationen (System-Actor-Ereignisse: abgeschlossene Batches, Auto-Pausierungen, Health-Wechsel) als Feed mit Kausalketten-Links. (5) **Statistik** — bewusst zuletzt: Bestands- und Trend-Zahlen (Speicher je Bibliothek, Wachstum, Feuerraten) aus der `metrics`-Zeitreihe; hier lebt auch die im Fingerprinting offene Speicher-Redundanz-Zahl und die Prowlarr-Trend-Frage (Metrik-Keys liefern die Module).

**Review-Inbox** ist Teil dieses Moduls (die Fundament-`review_tasks` brauchen genau eine UI-Heimat): Liste mit Typ-/Prioritäts-Filter, Snooze, Massenaktionen wo fachlich zulässig (Dismiss ja, fachliche Auflösung nein — die geschieht in den Modul-UIs, in die jede Zeile verlinkt; Fundament-Regel „Auflösung ist Fach-Action" bleibt unangetastet). Vollständige Typkatalog-, Priorisierungs- und Snooze-Spezifikation: [modules/review-system.md](review-system.md).

## Alternativen

**Grafana/extern**: für Metriken legitim (Prometheus-Export existiert), aber der Arbeitsvorrat (Reviews, Workflows) ist fachlich-interaktiv — kein Grafana-Fall. **Konfigurierbares Widget-Framework** (Nutzer baut Dashboards frei): Overkill; Version 1 bietet Anordnen/Ausblenden der registrierten Cards pro Nutzer, nicht mehr. **Modul-Queries direkt im Dashboard**: verworfen (Schatten-Backend, s. o.).

## Datenmodell

Ein einziges eigenes Objekt — Nutzer-Präferenzen:

```sql
CREATE TABLE dashboard_preferences (
    user_id     CHAR(26) PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    card_order  TEXT[]  NOT NULL DEFAULT '{}',
    hidden_cards TEXT[] NOT NULL DEFAULT '{}',
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

Alles andere gehört den liefernden Modulen (der Kern der Architekturentscheidung).

## Laravel-Klassen

| Klasse | Typ | Vertrag |
|---|---|---|
| `DashboardCardRegistry` | Service | Registrierung, Rollen-Filter, Cache-Orchestrierung (TTL je Card, Redis) |
| `DashboardController` | Inertia | sammelt Card-Daten (parallel via Lazy-Props für langsame Cards), Präferenzen |
| `ReviewInboxController` | Inertia | Fundament-Reviews mit Filter/Snooze/Dismiss |
| `SnoozeReviewTask` | Action | `snoozed_until`-Erweiterung der review_tasks (kleine Fundament-Migration); Audit |
| Kern-Cards | Klassen | `HealthCard`, `ReviewInboxCard`, `QueueCard`, `ConnectorsCard`, `BackupCard`, `AiCard`, `AcquisitionCard`, `LibraryStatsCard` — je im liefernden Modul beheimatet, hier nur benannt |

## API und UI

Das Dashboard ist Inertia-only (eigene UI-Fläche — die API-Konvention „kein UI-Unterbau" gilt; externe Statusabfragen nutzen `GET /api/v1/health` und die Modul-Routen). React-Komponenten: `Dashboard/Index` (Card-Grid mit Drag-Anordnung), `DashboardCard` (generischer Rahmen: Titel, Zustand-Badge, Kennzahl, Sparkline aus Metrik-Key, Aktions-Link), `Reviews/Inbox`. Kern-Flow morgendlicher Check: Zustandszeile grün? → Arbeitsvorrat: „7 Disc-Mappings warten" → Klick → Mapping-Review → zurück → „Backup 6 h alt, Probe vor 12 Tagen" — drei Minuten, vollständiges Bild.

## Edge Cases

* **Card-Provider wirft** (Modul-Bug): Card rendert als Fehler-Kachel mit Modul-Nennung — ein kaputtes Modul reißt nie das Dashboard (Try-Catch pro Card, Meta-Befund analog Health-Check-Defekt).
* **Langsame Cards** (Acquisition bei totem *arr): Lazy-Props mit Skeleton; Timeout 3 s ⇒ Fehler-Kachel statt hängender Seite.
* **Rolle ohne Cards** (`member` landet auf /admin): Redirect auf die Bibliotheks-Startseite — das Dashboard ist keine member-Fläche.
* **Widersprüchliche Zähler** (Inbox-Chip sagt 7, Liste zeigt 6 — Cache-Versatz): Chips tragen das Cache-Alter als Tooltip; TTLs der Arbeitsvorrat-Cards sind kurz (30 s).

## Performance

Cards cachen nach eigenem TTL (Zustandszeile 10 s, Statistik 1 h); die Dashboard-Anfrage selbst liest fast nur Redis. Lazy-Props verhindern, dass die langsamste Card die Seite blockiert. Die Review-Inbox nutzt die partiellen Indizes des Fundaments.

## Security

Rollen-Filter der Registry plus Policy je Card-Datenquelle (eine Card zeigt nie mehr, als ihr Modul der Rolle zeigen würde — der Vertrag verlangt, dass `data()` unter der Rollen-Policy des Moduls rechnet). Sichtbarkeitsregeln ([security](../architecture/security.md)): restriktive Inhalte erscheinen in keinem Feed und keiner Statistik-Aufschlüsselung (nur Aggregat).

## Tests

Registry-/Rollen-Matrix (Card-Sichtbarkeit je Rolle); Fehler-Isolation (werfender Provider ⇒ Fehler-Kachel, Rest intakt); Card-Verträge als Contract-Tests im jeweiligen Modul (Kennzahl gegen bekannten Fixture-Zustand); Inbox-Filter/Snooze; Sichtbarkeits-Suite-Erweiterung (Dashboard-Flächen).

## ADR-Verweise

Registry-Muster (vierte Anwendung — die Konsistenz ist die Entscheidung); Architekturregeln 1–2 (keine Fachlogik in Controllern/Komponenten — dieses Modul ist ihr Schaufenster).

## Offene Punkte

* **member-Startseite** (Bibliotheks-Home mit „Weiterschauen"): eigenes UI-Kapitel außerhalb des Admin-Scopes; die Fundament-Daten (Container-Progress-Cache) existieren.
* **Statistik-Tiefe** (Wachstums-Trends, Hörstatistiken): wartet auf Metrik-Bestand und das offene ABS-Statistik-Thema.
