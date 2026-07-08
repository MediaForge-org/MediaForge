# Seiten- und Komponenten-Gesamtkatalog

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Vertiefung zu [ui/design-system.md](design-system.md). Konsolidierte Sicht auf **jede** Inertia-Seite des Systems: Route, Rolle, Layout-Muster, Kern-Props und Zugehörigkeit. Modulkapitel definieren ihre Seiten normativ (vollständige Props-Verträge, Flows); dieser Katalog beantwortet, was nur über alle Seiten hinweg sichtbar wird — Navigationsstruktur, Muster-Konsistenz, geteilte Komponenten — und ist die Prüfliste des Contract-Tests „jede Inertia-Route ist hier gelistet".

## Navigationsstruktur

```
├── Dashboard (Startseite admin/manager)
│   ├── Reviews/Inbox
│   └── (Card-Links in alle Module)
├── Library/Home (Startseite member — Bibliotheks-Browsing, offener Punkt Admin-Kapitel)
├── Discs/*, DiscSets/*
├── Audiobooks/*
├── Upscaler/*
├── Connectors/*
├── Rules/*, Workflows/*
├── Quality/*, Duplicates/*
├── Acquisition/Overview
└── Admin/* (Settings, PlayerDevices, AiEngine, Backups)
```

Jeder Navigationseintrag ist rollen-sichtbarkeitsgefiltert (Fundament-Policy-Muster, [database/schema-reference.md](../database/schema-reference.md) Zugriffs-Matrix); `member` sieht nur `Library/Home` und die konsumierenden Sub-Flächen (Item-Detail, Suche), keine Betriebsflächen.

## Vollständiges Seiten-Inventar

**Muster**: I = Index/Grid, D = Detail, A = Arbeitsfläche (Design-System-Layout-Muster).

### Fundament

| Seite | Route | Rolle | Muster | Kapitel |
|---|---|---|---|---|
| `Dashboard/Index` | `/admin` (bzw. `/`) | admin/manager | I (Card-Grid) | [admin-dashboard.md](../modules/admin-dashboard.md) |
| `Reviews/Inbox` | `/reviews` | manager | I | admin-dashboard.md |
| `Library/Home` | `/` (member) | member | I | *offener Punkt, s. u.* |
| `Items/Show` | `/items/{ulid}` | member | D | Fundament-Katalog (Enrichment-Panel, Assets, Beziehungen eingebettet) |
| `Search/Results` | `/search` | member | I | [search.md](../modules/search.md) |

### Disc-Engine

| Seite | Route | Rolle | Muster |
|---|---|---|---|
| `Discs/Index` | `/libraries/{ulid}/discs` | member | I |
| `Discs/Show` | `/discs/{ulid}` | member | D |
| `Discs/MappingReview` | `/discs/{ulid}/mapping-review` | manager | A |
| `DiscSets/Show` | `/disc-sets/{ulid}` | member/manager | D |

Vollständige Props-Verträge: [Disc-Engine-UI-Referenz](../modules/disc-engine/ui-reference.md).

### Hörbuch-Assembler

| Seite | Route | Rolle | Muster |
|---|---|---|---|
| `Audiobooks/Assembly` | `/audiobooks/{ulid}/assembly` | member/manager | D (drei Zonen) |

Vollständiger Props-Vertrag: [Assembler-API/UI/Tests](../modules/audiobook-assembler/api-ui-tests.md).

### Audio-Upscaler

| Seite | Route | Rolle | Muster |
|---|---|---|---|
| `Upscaler/RunDetail` | `/upscale/runs/{ulid}` | member | D |
| `Upscaler/Comparison` | `/upscale/runs/{ulid}/comparison` | member | D (A/B-Sonderform) |

### Connectoren

| Seite | Route | Rolle | Muster |
|---|---|---|---|
| `Connectors/Index` | `/admin/connectors` | admin | I |
| `Connectors/Activity` | `/admin/connectors/{ulid}/activity` | manager | D |
| `Admin/PlayerDevices` | `/admin/player-devices` | admin | I |

### Workflow/Rule Engine

| Seite | Route | Rolle | Muster |
|---|---|---|---|
| `Workflows/Index` | `/workflows` | manager | I |
| `Workflows/Show` | `/workflows/{ulid}` | manager | D |
| `Rules/Index` | `/admin/rules` | admin | I |
| `Rules/Builder` | `/admin/rules/{ulid}/edit` bzw. `/new` | admin | A (Baum-Editor) |

### Datenqualität/Dubletten

| Seite | Route | Rolle | Muster |
|---|---|---|---|
| `Quality/Dashboard` | `/admin/quality` | manager | I |
| `Duplicates/Review` | `/admin/duplicates` | manager | A |

### *arr-Familie

| Seite | Route | Rolle | Muster |
|---|---|---|---|
| `Acquisition/Overview` | `/acquisition` | manager | I (Klammer-Ansicht) |

### Admin/Betrieb

| Seite | Route | Rolle | Muster |
|---|---|---|---|
| `Admin/AiEngine` | `/admin/ai` | admin | D |
| `Admin/Backups` | `/admin/backups` | admin | D |
| `Admin/Settings` | `/admin/settings/{module?}` | admin | D |
| `Admin/Health` | eingebettet im Dashboard, kein eigener Vollpfad | admin | — |

## Komponenten-Inventar (systemweit wiederverwendet)

Ergänzend zum modul-lokalen Inventar der einzelnen UI-Referenzen (z. B. [Disc-Engine-Komponenten-Inventar](../modules/disc-engine/ui-reference.md)) die Komponenten, die **mehrfach modulübergreifend** instanziiert werden — Kandidaten für eine künftige gemeinsame Bibliothek ([Design-System](design-system.md), offener Punkt):

| Komponente | Kategorie (Design-System) | Instanzen in |
|---|---|---|
| `ConfidenceBadge` | Confidence-Badge | Disc-Engine, Assembler (Chapter-Vergleich), Enrichment (Feld-Provenienz), Knowledge Graph (Kantenherkunft) |
| `ClassificationChip`/`StatusChip` | Status-Chip | Disc-Engine, Workflow-Schrittleiste, Connector-Health |
| Herkunfts-Badge (`OriginBadge`) | Herkunfts-Badge | Assembler (KI-Kapitel-Band), Enrichment (Feld-Quelle), Knowledge Graph (Kanten-Quelle), Upscaler (Rekonstruktions-Badge) |
| `DashboardCard` | Karte (Elevation-Stufe „Karte") | Admin-Dashboard, generischer Rahmen für alle registrierten Cards |
| `AuditTimeline` | Feed-Muster | Disc-Engine, Assembler, Workflow-Detail — überall dort eingebettet, wo eine Entität eine Historie zeigt |
| Evidence-Popover | Evidence-/Begründungs-Popover | Disc-Engine (`classification_evidence`), Rule Engine (Trace), Assembler (`alignment_report`) |

Diese Tabelle ist der Nachweis-Mechanismus der Design-System-Governance-Regel „≥ 2 Module" — jede Zeile hier ist bereits qualifiziert; eine künftige `@mediaforge/ui`-Extraktion beginnt mit dieser Liste.

## Tastenkürzel-Registry (Konflikt-Vermeidung)

Globale Kürzel (Design-System): `Cmd/Ctrl+K` (Suchpalette), `?` (Shortcut-Hilfe der aktuellen Seite). Modul-lokale Kürzel dürfen diese nie überschreiben; Registrierungstabelle zur Kollisionsprüfung:

| Kürzel | Kontext | Wirkung |
|---|---|---|
| `Enter` | Disc-Review, Rule-Builder-Knoten-Fokus | Hauptvorschlag bestätigen |
| `R` | Disc-Review | Remap-Suchfeld öffnen |
| `S` | Disc-Review, Assembler-Kapitel-Vergleich | Segment-/Kapitel-Editor öffnen |
| `Entf` | Disc-Review, Rule-Builder | Element lösen/löschen |
| `Alt` (gehalten) | Disc-Segment-Editor, Assembler-Kapitel-Editor | Snapping temporär deaktivieren |
| `Pfeiltasten` | Assembler-Kapitel-Editor (Grenzen) | ±100 ms, mit Shift ±1 s |
| `Leertaste` | Audio-Vorschau-Marken (Assembler, Upscaler-Vergleich) | Play/Pause an fokussierter Marke |

Ein neues Modul-Kürzel wird gegen diese Tabelle geprüft (Boot-Zeit-Lint über eine zentrale Kürzel-Registrierung, analog dem Health-Check-`remedyRef`-Contract-Test) — Kollisionen brechen den Frontend-Build.

## Layout-Muster-Zuordnung (Vollständigkeit)

Jede Seite des Inventars ordnet sich einem der drei Design-System-Muster zu (Spalte „Muster" oben); Seiten außerhalb des Schemas (`Upscaler/Comparison` als A/B-Sonderform, `Rules/Builder` als Baum-Editor-Variante der Arbeitsfläche) sind als dokumentierte Varianten geführt, nicht als viertes Muster — sie teilen die Grundstruktur (zweispaltig bzw. Kopf+Bereiche) mit modul-spezifischer Interaktionsschicht.

## Governance und Contract-Test

Jede Inertia-Route, die ein Controller registriert, muss eine Zeile in diesem Katalog haben (Route, Rolle, Muster, Verweis auf das Modulkapitel mit dem vollständigen Props-Vertrag) — der Contract-Test scannt die Route-Definitionen und gleicht sie gegen dieses Dokument ab, identisch zum Mechanismus des [API-Endpunkt-Katalogs](../api/endpoint-catalog.md). Eine neue Seite ohne Katalog-Zeile bricht den Build; eine Katalog-Zeile ohne registrierte Route wird als Drift-Warnung gemeldet.

## Offene Punkte

* **`Dashboard/Index` (aktueller Stand)**: als **Foundation-Vorstufe** implementiert — Kennzahl-Kacheln, Bibliotheks-Tabelle mit Scan-Status und eine provisorische Bibliotheks-Verwaltung (anlegen / „jetzt scannen", `POST /libraries[/…]`). Noch **ohne** die `DashboardCardRegistry` (Card-Grid, [admin-dashboard.md](../modules/admin-dashboard.md)) und ohne Auth/Rollen (im Dev offen). Die Card-Registry-Fassung ersetzt diese Vorstufe, sobald Module Card-Beiträge liefern; eine dedizierte Bibliotheks-Admin-Fläche kann die Verwaltung dann aus dem Dashboard herauslösen.
* **`Library/Home` + `Items/Show` (aktueller Stand)**: als **Foundation-Vorstufe** implementiert — der lokale Fundament-Katalog (Video/Filme) verdichtet gescannte Dateien zu `media_items` und zeigt sie als **Poster-Wand** je Bibliothek (`GET /libraries/{ulid}`, serverseitig paginiert) plus **Item-Detailseite** (`GET /items/{ulid}`) und lokale Poster-Auslieferung (`GET /media/items/{ulid}/poster`, klein gerechnet + lokal gecacht). Bewusst **ohne Wiedergabe** (Betreiber-Vorgabe: kein Streaming, alles lokal). Route-Abweichung zum Sollzustand (`Library/Home` bei `/` fuer member): vorerst bibliotheks-skopiert und ueber die Dashboard-Karte („Öffnen") erreichbar, bis Auth/Rollen + die member-Startseite stehen. Noch nicht abgebildet: „Weiterschauen"/`user_container_progress`, TV-Hierarchie, Hoerbuecher (folgen als eigene Katalog-Inkremente).
* **Statistik-Tiefe der Seiten** (Wachstumstrends je Bibliothek, Hörstatistiken): wartet auf Metrik-Bestand ([health-monitoring](../modules/health-monitoring.md)) und das offene ABS-Statistik-Thema ([audiobookshelf.md](../connectors/audiobookshelf.md)).
* **`@mediaforge/ui`-Paket-Extraktion**: siehe Design-System, offener Punkt — dieser Katalog liefert die Kandidatenliste, sobald der Bedarf real wird.
