# UI-Design-System

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [architecture/overview.md](../architecture/overview.md) (Architekturregel 2: Komponenten treffen keine fachlichen Entscheidungen), [api/conventions.md](../api/conventions.md) (Inertia/REST-Trennung). Dieses Kapitel ist normativ für **jede** React-/Inertia-Komponente aller Module; die Modulkapitel (z. B. [Disc-Engine-UI-Referenz](../modules/disc-engine/ui-reference.md), [Assembler-API/UI/Tests](../modules/audiobook-assembler/api-ui-tests.md)) verweisen hierher für alles, was nicht modulspezifisch ist, und definieren nur ihre eigenen Komponenten und Flows.

**Vertiefung**: [Seiten- und Komponenten-Gesamtkatalog](page-catalog.md)

## Warum ein zentrales Design-System

Die UI-/UX-Mission ist ein Kernziel von MediaForge. Nach der Installation sollen Jellyfin, Audiobookshelf und MediaForge-Systembereiche wie eine konsistente moderne Medienumgebung wirken, während Jellyfin und Audiobookshelf im Hintergrund getrennt, lokal und updatefähig bleiben. Dieses Kapitel wird durch die produktorientierten UI-/UX-Dokumente unter [ui-ux/](../ui-ux/design-system.md) ergänzt.

Elf Fach-Engines plus Fundament produzieren Confidence-Badges, Status-Chips, Fortschrittsringe und Review-Flächen — unabhängig entwickelt, würden sie elf Grün-Töne für „bestätigt" und elf Interpretationen von „Confidence 0.87" hervorbringen. Das Design-System zieht diese Entscheidungen einmal: Farben, Typografie, Zustandsmuster, Zugänglichkeitsregeln. Module bauen **auf** diesem System, sie erfinden es nicht neu — ein Prüfpunkt jedes Modul-PR-Reviews ist, ob eine neue Komponente ein bestehendes Token/Muster hätte wiederverwenden können, statt ein eigenes zu erfinden.

## Design-Tokens

Alle Tokens dieses Kapitels sind als Tailwind-CSS-Theme-Erweiterung implementiert (`tailwind.config`), nicht als separates CSS-System ([ADR-0001](../adr/0001-technology-stack.md)) — Farbnamen, Abstandsraster und Breakpoints dieses Kapitels sind Tailwind-Utility-Klassen mit MediaForge-eigenen Werten, keine handgeschriebenen CSS-Variablen.

> **Umsetzung (Fundament)**: Die Tokens leben in `resources/css/app.css` als Tailwind-v4-`@theme inline`-Bindungen auf semantische CSS-Variablen (`--accent`, `--surface`, `--status-*`, …), die pro Theme umschalten (`prefers-color-scheme` als Default, `data-theme` als manuelle Überschreibung). Daraus entstehen die Utility-Klassen `bg-surface`, `text-fg`, `border-line`, `bg-accent`/`text-on-accent`, `text-success|warning|error|neutral` usw. Die wiederverwendbaren Primitive (`StatusChip`, `StatCard`, `AppButton`, `Icon`) liegen unter `resources/js/components/`. Der Akzent ist warm (Amber, Audiobookshelf-nah); `status-warning` ist bewusst ein abgesetztes Amber und trägt — wie alle Status — immer Icon + Text (nie Farbe allein).

### Farben

Tokens sind semantisch benannt (nie `blue-500` in Komponenten-Code, immer `color-confidence-high`) — das erlaubt Theming (Light/Dark) und spätere Palette-Änderungen ohne Komponenten-Anfassen.

| Token | Verwendung | Light | Dark |
|---|---|---|---|
| `color-confidence-high` | Confidence ≥ 0.90 (Disc-Mapping, Chapter-Alignment, Enrichment-Merge) | Grün, WCAG-AA gegen Weiß | hellerer Grünton, AA gegen Dunkelgrund |
| `color-confidence-medium` | 0.60–0.90 | Amber/Gelb | dito |
| `color-confidence-low` | < 0.60 | Rot-Orange | dito |
| `color-status-success` | abgeschlossen/bestätigt/gesund | Grün (kann mit `confidence-high` denselben Hex teilen, semantisch getrennt) | |
| `color-status-warning` | Review offen, Degraded-Health, Staleness | Amber | |
| `color-status-error` | Fehlgeschlagen, Unreachable, Konflikt | Rot | |
| `color-status-neutral` | unklassifiziert, unbekannt, ungemappt | Grau | |
| `color-origin-provider` | Herkunfts-Badge: Provider-Daten | Blau | |
| `color-origin-manual` | Herkunfts-Badge: manuell | Violett | |
| `color-origin-ai` | Herkunfts-Badge: KI-generiert/-vorgeschlagen | dediziert auffällig (nie mit `provider`/`manual` verwechselbar — Architekturregel 5 verlangt visuelle Unmissverständlichkeit) | |
| `color-restricted` | Sichtbarkeits-Marker restriktiver Inhalte ([optionaler Stash-Import/Connector](../connectors/stash.md)) | dezent, nie alarmierend (kein „Warnfarbe"-Ton — Vertraulichkeit ist kein Fehlerzustand) | |

**Zonen-Schwellen sind Setting, nicht Token-Grenze**: Die Farbe `color-confidence-high` beginnt visuell bei 0.90, weil das der **Default** der Disc-Engine-Schwelle ist ([Mapping-Algorithmus](../modules/disc-engine/mapping-algorithm.md)); ändert ein Betreiber die Schwelle, bleibt die Farbskala als **kontinuierlicher Gradient** zwischen den drei Ankerfarben korrekt (die Komponente interpoliert über den tatsächlichen Zahlenwert, sie hat keine hartkodierte 0.90-Grenze im Rendering).

### Typografie

Eine Schriftfamilie (System-Font-Stack, keine Web-Font-Nachladung — Offline-Fähigkeit, [architecture/security.md](../architecture/security.md)-Prinzip „Selfhosted ohne Fremdabhängigkeit" auf UI-Ebene übertragen). Größenskala in sechs Stufen (`text-xs` … `text-2xl`), Zeilenhöhe an jede Stufe gekoppelt. Monospace-Token (`font-mono`) ausschließlich für: Timecodes (`00:12:34.567`), ULIDs, Datei-Pfade, Signaturen/Hashes — überall sonst ist Monospace ein Stilfehler.

### Abstand und Grid

8px-Basisraster (`space-1` = 4px … `space-12` = 96px). Seiten-Layout: 12-Spalten-Grid mit Breakpoints (`sm`/`md`/`lg`/`xl`), Content-Max-Breite 1440px zentriert (Bibliotheks-Grids und Datentabellen nutzen die volle Breite, Formulare und Detailtexte sind auf 720px begrenzt für Lesbarkeit).

### Radius und Elevation

Zwei Radius-Stufen (`radius-sm` für Chips/Badges, `radius-md` für Karten/Panels — keine dritte Stufe, Konsistenz vor Vielfalt). Drei Elevation-Stufen (flach, Karte, Modal/Overlay) über Schatten-Tokens, nicht über beliebige `box-shadow`-Werte pro Komponente.

## Wiederverwendbare Komponenten-Kategorien

Diese Kategorien sind **Muster**, keine konkreten React-Komponenten — jedes Modul implementiert seine eigene Instanz (z. B. `ConfidenceBadge` der Disc-Engine, `ConfidenceBadge` des Assemblers), aber alle folgen demselben Vertrag:

### Confidence-Badge

Props-Vertrag (modulübergreifend identisch): `{ value: number (0–1), zones?: {high, medium}, showValue?: boolean }`. Rendert Farbe nach Zonen-Interpolation (oben), Text „87 %" bei `showValue`, sonst nur Farbe + Icon-Form (Kreis gefüllt/halb/leer) — die redundante Form-Kodierung ist Pflicht (Zugänglichkeit unten). Beispiele im System: [Disc-Engine `ConfidenceBadge`](../modules/disc-engine/ui-reference.md), Assembler-Kapitel-Vergleich, Enrichment-Merge-Anzeige.

### Status-Chip

`{ status: enum, label?: string }` — feste Farbzuordnung nach `color-status-*`. Jeder Modul-Status-Enum (Disc `analysis_status`, Assembly `status`, Connector `health_status`, Workflow `status`) mappt auf genau eine der vier `color-status-*`-Familien; die Zuordnungstabelle je Modul steht im [Seiten-Katalog](page-catalog.md).

### Herkunfts-Badge

`{ source: 'provider'|'manual'|'ai'|'heuristic'|'import'|'connector' }` — Farbfamilie nach `color-origin-*` (heuristisch/connector/import teilen sich neutrale Grautöne mit unterschiedlichen Icons, da sie seltener UI-Priorität brauchen als der scharfe Provider/Manual/KI-Dreiklang). Die KI-Herkunft ist **immer** sichtbar, nie nur im Tooltip (Architekturregel 5 — „dauerhaft sichtbar", wörtlich aus mehreren Modulkapiteln übernommen, hier als Systemregel kodifiziert).

### Fortschritts-Ring/-Balken

Zwei Formen: **Ring** (kompakte Kartenansicht, z. B. `DiscProgressRing`, Container-Fortschritt „3/6") und **Balken** (Detailansicht, Segment-/Kapitel-Zeitachsen wie `SegmentTrack`/`ChapterAnatomyBar`). Beide teilen die Farblogik: vollständig = `color-status-success`, teilweise = Gradient nach Anteil, leer = `color-status-neutral`.

### Evidence-/Begründungs-Popover

Jede Automatik-Entscheidung (Klassifikation, Mapping, Merge, Qualitäts-Score) zeigt ihre Begründung über ein einheitliches Popover-Muster: ein vorformulierter Satz (serverseitig generiert, Architekturregel 2 — die Komponente rendert Text, sie interpretiert kein Evidence-JSON selbst) plus ein aufklappbarer strukturierter Baum für Diagnose. Beispiele: `evidenceSummary` der Disc-Engine, `alignment_report`-Anzeige des Assemblers, Trace-Anzeige der Rule Engine.

### Leerzustände (Empty States)

Drei Unterarten, konsistent unterschieden: **„nichts vorhanden"** (echte Leere, z. B. Bibliothek ohne Discs — neutrale Illustration + Hinweis, bei Manager-Rolle mit Handlungsvorschlag), **„Filter ergebnislos"** (Zurücksetzen-Aktion prominent), **„noch nicht analysiert/geladen"** (Skeleton/Spinner, nie eine Leerzustand-Illustration — das würde „nichts da" suggerieren, wo nur „noch nicht fertig" gemeint ist).

### Fehler-Panels

Ein Muster für alle Fach-Fehlerzustände (Disc `analysis='failed'`, Workflow `failed`, Connector `unreachable`): Titel (was ist fehlgeschlagen), Detailtext (aus der Fach-Fehlermeldung, nie eine rohe Exception), primäre Wiederholungs-Aktion, sekundärer Diagnose-Link (Audit-Timeline, Logs). Nie ein generisches „Etwas ist schiefgelaufen" ohne Handlungsoption.

## Interaktionsmuster

**Kein optimistisches Fachverhalten** (wörtlich aus dem Disc-Engine-Kapitel übernommen, hier als Systemregel): Jede Mutation, die eine fachliche Action aufruft, zeigt einen Pending-Zustand und wartet auf den Server-Roundtrip. Ausnahme: rein clientseitige UI-Zustände ohne Server-Wirkung (Akkordeon auf/zu, Tab-Wechsel, Sortierung einer bereits geladenen Liste).

**Tastaturbedienbarkeit**: Jede primäre Interaktionsfläche (Review-Flächen, Editoren, Listen mit Massenaktionen) ist vollständig ohne Maus bedienbar — `Tab`-Reihenfolge folgt der visuellen/logischen Reihenfolge, `Enter`/`Leertaste` bestätigen den fokussierten Hauptvorschlag, `?` öffnet eine Shortcut-Übersicht. Modul-spezifische Tastenkürzel (z. B. `R` für Remap in der Disc-Review) sind im jeweiligen Modulkapitel dokumentiert und dürfen sich nicht mit dieser globalen Liste überschneiden (Registry-Prüfung im [Seiten-Katalog](page-catalog.md)).

**Zugänglichkeit (WCAG 2.1 AA als Mindestmaß)**: Farbcodierung trägt immer eine redundante Form-/Text-/Icon-Kodierung (nie Farbe allein als Informationsträger — durchgängige Regel aller Modul-UI-Referenzen, hier zentral verankert). Kontrastverhältnisse der Tokens sind gegen beide Themes geprüft. Interaktive SVG-Overlays (z. B. `MappingLineLayer`) sind `aria-hidden`, die zugrundeliegenden Daten sind parallel als `aria-describedby`-Text zugänglich. Audio-/Video-Vorschauelemente haben Tastatur-Play/Pause.

**Telemetrie-Zurückhaltung**: Wie im Disc-Engine-Kapitel festgelegt („Telemetrie-freie Gestaltung"), gilt systemweit: keine Interaktions-Telemetrie (Klickpfade, Verweildauer) über alle Module hinweg. Die einzige Aktivitätsspur ist das Audit-System — konsistent mit dem Selfhosting-Datenminimierungs-Prinzip.

## Theme-Mechanik

Light/Dark über CSS-Custom-Properties, umgeschaltet per `data-theme`-Attribut am Root; System-Präferenz (`prefers-color-scheme`) ist der Default, manuelle Umschaltung wird pro Benutzer persistiert (`users`-Präferenz-Feld, Fundament-Migration). Jeder Farb-Token hat zwingend beide Werte definiert — ein Token ohne Dark-Wert ist ein Review-Defekt (Architektur-Test: Token-Datei-Linting prüft Vollständigkeit beider Theme-Blöcke).

## Layout-Grundmuster

Drei wiederkehrende Seitenformen, denen jede neue Modul-Seite folgen soll (Abweichung braucht Begründung im PR):

| Muster | Verwendung | Beispiele |
|---|---|---|
| **Index/Grid** | Bibliotheks-/Bestandsübersichten | `Discs/Index`, `Connectors/Index`, `Rules/Index` |
| **Detail (Kopf + Tabs/Bereiche)** | Einzelobjekt-Vertiefung | `Discs/Show`, `Audiobooks/Assembly` |
| **Arbeitsfläche (zweispaltig, Review)** | Entscheidungs-Flächen mit Kandidaten/Zielraum | `Discs/MappingReview`, `Rules/Builder` |

Jedes Muster hat einen Referenz-Wireframe im [Seiten-Katalog](page-catalog.md); neue Seiten wählen das passende Muster, statt ein viertes zu erfinden.

## Governance: neue Komponenten und Tokens

Ein neues Token entsteht nur, wenn keine bestehende semantische Bedeutung passt (Prüfliste: Zonen-Farben, Status-Familien, Herkunfts-Familien — neun Token-Familien decken praktisch jeden bisherigen Anwendungsfall). Eine neue wiederverwendbare Komponentenkategorie durchläuft: (1) Nachweis, dass ≥ 2 Module den Bedarf haben (sonst gehört sie ins Modul, nicht ins System), (2) Props-Vertrag hier dokumentiert, (3) Zugänglichkeits-Prüfliste erfüllt, (4) Zeile im [Seiten-Katalog](page-catalog.md)-Komponenten-Inventar. Modul-lokale Komponenten (die nur ein Modul braucht, wie `MappingLineLayer` der Disc-Engine) bleiben im Modulkapitel dokumentiert und werden hier nur referenziert, nicht dupliziert.

## Tests

Visuelle Regressionstests (Snapshot-Suite) für jede Komponentenkategorie in beiden Themes; Kontrast-Linting als CI-Gate (automatisierte WCAG-AA-Prüfung der Token-Paare); Tastatur-Navigations-Tests für die drei Layout-Muster (Tab-Reihenfolge, Fokus-Fallen-Freiheit); Token-Vollständigkeits-Test (jeder Light-Token hat einen Dark-Gegenpart); Komponenten-Props-Contract-Tests (jede Kategorie gegen ihre hier dokumentierte Schnittstelle, modulübergreifend parametrisiert wie die *arr-Familie-Contract-Tests).

## ADR-Verweise

Keine eigene ADR (das Design-System ist eine Konventions-Konsolidierung bestehender Modul-Festlegungen, keine neue Architekturentscheidung); es operationalisiert Architekturregel 2 (keine Fachlogik in Komponenten) und Regel 5 (KI-Kennzeichnung) auf UI-Ebene systemweit.

## Offene Punkte

* **Komponenten-Bibliothek als Paket** (npm-Workspace-Package `@mediaforge/ui` statt lose Konvention): sinnvoll ab mehreren externen Frontend-Konsumenten (derzeit keiner — das UI ist Inertia-only, [api/conventions.md](../api/conventions.md)); vertagt.
* **Animations-Tokens** (Übergangsdauern, Easing-Kurven): bisher pro Komponente ad hoc; Konsolidierung nach mehr Betriebserfahrung mit den bestehenden Interaktionsmustern.
* **Print-/Export-Stylesheet** (z. B. Audit-Berichte als PDF): kein benannter Anwendungsfall; vertagt.
