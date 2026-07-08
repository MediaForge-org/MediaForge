# Disc-Engine: UI-Referenz

Vertiefung zu [modules/disc-engine.md](../disc-engine.md), Abschnitt „Vue-/Inertia-Komponenten und UI-Flows". Vollständige Spezifikation der Seiten, Props-Verträge, Komponenten und Interaktionen. Gestaltungs-Grundlagen (Tokens, Grid, Zustandsmuster) definiert das [UI-Design-System](../../ui/design-system.md); dieses Dokument spezifiziert nur Disc-spezifisches. Architekturregel 2 gilt durchgängig: Komponenten treffen keine fachlichen Entscheidungen — jeder angezeigte Zustand kommt vorgerechnet vom Server, jede Mutation ist ein Action-Roundtrip.

## Seitenkatalog

| Inertia-Seite | Route | Rolle | Zweck |
|---|---|---|---|
| `Discs/Index` | `/libraries/{ulid}/discs` | member | Disc-Grid/-Liste einer Bibliothek |
| `Discs/Show` | `/discs/{ulid}` | member | Disc-Detail: Struktur, Mappings, Playback |
| `Discs/MappingReview` | `/discs/{ulid}/mapping-review` | manager | Review-Arbeitsfläche |
| `DiscSets/Show` | `/disc-sets/{ulid}` | member (Mutationen manager) | Set-Verwaltung und Matrix |
| eingebettet | Dashboard-Karte | member | manuelle Playback-Bestätigung |

## `Discs/Index`

### Props-Vertrag

```ts
interface DiscsIndexProps {
  library: LibraryRef;
  discs: Paginated<DiscCard>;
  filters: DiscFilterState;          // aktive Filter, serverseitig geparst
  reviewCounts: { mappingOpen: number; analysisFailed: number };
}
interface DiscCard {
  id: Ulid;
  kind: 'bluray' | 'uhd_bluray' | 'dvd';
  label: string;                     // Anzeigename: Katalogtitel > meta_title > Volume-Label
  coverUrl: string | null;           // signierte Kurzzeit-URL aus Katalog-Verknüpfung
  set: { id: Ulid; name: string; position: number; size: number } | null;
  analysis: 'pending' | 'running' | 'analyzed' | 'failed';
  mapping: { candidates: number; confirmed: number; reviewOpen: boolean };
  watch: { mapped: number; watched: number; inProgress: number;
           derived: 'unmapped' | 'unwatched' | 'partial' | 'watched' };
}
```

### Verhalten

Grid (Cover-Karten, Default) und Tabellen-Umschaltung (Toggle im Kopf, Präferenz im User-Setting `ui.discs_view`). Karte: Cover mit Kind-Badge (BD/UHD/DVD als Ecklabel), Fortschrittsring unten rechts (`watched/mapped`, Farbe nach `derived`), Warn-Badge bei `reviewOpen` (⚠) und `analysis='failed'` (✕). Klick ⇒ `Discs/Show`; Badge-Klick ⇒ direkt `MappingReview` (kürzester Weg zur Arbeit). Filterleiste: Status-Chips (Zähler serverseitig), Kind-Auswahl, Set-Auswahl, Suchfeld (debounced 300 ms, serverseitige Suche). Leere Zustände nach Design-System-Muster: „keine Discs in dieser Bibliothek" (mit Scan-Hinweis für Manager) vs. „Filter ergebnislos" (mit Zurücksetzen-Aktion). Analyse-Läufe aktualisieren die Karten über den Echo-Kanal der Bibliothek (`DiscImageAnalyzed` ⇒ partielles Reload der betroffenen Karte via Inertia-Partial-Visit) — kein Polling.

## `Discs/Show`

### Props-Vertrag (Auszug; vollständig serverseitig vorgerechnet)

```ts
interface DiscDetailProps {
  disc: {
    id: Ulid; kind: DiscKind; sourceForm: 'iso' | 'bdmv_folder' | 'video_ts_folder';
    label: string; analysis: AnalysisState; analyzedAt: Iso8601 | null;
    analyzerVersion: string | null;
    signatureShort: string | null;               // gekürzt für Anzeige, Copy-Aktion voll
    menus: { hdmv: boolean; bdj: boolean; dvd: boolean };
    encrypted: boolean | null;                   // AACS/CSS-Stichprobe
    anomalies: Array<{ code: string; count: number }>;  // aggregiert, Tooltip-Detail
    catalogLink: CatalogLinkRef | null;
    set: SetRef | null;
    openInPlayer: { available: boolean; players: PlayerOption[] } | null;
  };
  playlists: PlaylistRow[];
  duplicatesCollapsed: number;                   // gefaltete Duplikate (aufklappbar)
  auditContext: AuditTimelineProps;              // Fundament-Komponente
  pendingAcks: ManualAckCard[];                  // offene open_close-Bestätigungen
}
interface PlaylistRow {
  id: Ulid; ref: string; durationMs: number; chapterCount: number;
  classification: Classification; confidence: number | null;
  classifiedBy: 'heuristic' | 'manual' | 'ai' | null;
  evidenceSummary: string;                       // vorformulierte Ein-Satz-Begründung
  segments: SegmentChip[];
  mapping: MappingChip | null;                   // bestätigt ODER bester Vorschlag
  watch: { status: WatchStatus; positionMs: number | null } | null;  // je Ziel-Episode
}
```

`evidenceSummary` ist die serverseitig formulierte Begründung („Gruppe aus 6 ähnlichen Laufzeiten, Play-All-Mitglied") — das UI rendert Text, es interpretiert keine Evidence-JSONs (Regel 2). Das volle Evidence-Objekt liegt im Popover als formatierter Baum (Diagnose, read-only).

### Layout und Verhalten

Drei Bereiche (Modulkapitel): Kopf, Playlist-Tabelle, Audit-Timeline. Ergänzende Festlegungen:

* **Kopf:** Katalog-Verknüpfung als Breadcrumb (Show → Season → Disc-Label); „Disc 2 von 4" navigiert zum Set; Menü-Fähigkeiten als Icon-Reihe mit Tooltips („BD-J-Menü — erfordert Kodi"); „Im externen Player öffnen" als Split-Button (Default-Player + Auswahl), disabled mit Begründungs-Tooltip, wenn kein Player konfiguriert (`openInPlayer.available=false`). Verschlüsselungs-Hinweis als dezentes Badge.
* **Playlist-Tabelle:** sortiert Episoden-Kandidaten zuerst (dann main/play_all/bonus/Rest), Duplikate/Junk hinter einem Aufklapper („27 gefaltete/irrelevante Playlists anzeigen"). Klassifikations-Chip mit Confidence als Untertext; manuelle Klassifikation trägt ein Schloss-Icon (unantastbar für Heuristik, Regelkatalog). Mapping-Spalte: bestätigt (✓ Episodennummer + Titel), Vorschlag (gestrichelt, Confidence-Badge, Klick ⇒ Review), leer. Watch-Spalte je gemappter Episode: Status-Icon + Resume-Zeit; Hover zeigt „zuletzt gesehen …". Kapitelanatomie als Mini-Balken (relative Markenpositionen) — dieselbe Visualisierung wie im Review (Wiedererkennung).
* **Segment-Chips:** Playlists mit Segmenten zeigen die Partition als proportionale Farbleiste (episode_body/intro/credits/bonus nach Design-System-Kategorienfarben); Klick öffnet den Segment-Editor (unten).
* **Aktionen** (manager): Zeilen-Kontextmenü „umklassifizieren" (Untermenü der Klassen ⇒ Action-Roundtrip), „neu analysieren" (Kopf-Menü, mit Struktur/Nur-Regeln-Wahl als Dialog), „Set zuordnen".

## `Discs/MappingReview`

Die Arbeitsfläche des Kern-Flows (Modulkapitel). Präzisierungen:

### Layout

Zweispaltig: links Playlist-Kandidaten (Karten mit Laufzeit, Anatomie-Balken, Confidence), rechts Episoden-Suchraum (kompakte Liste: Nummer, Titel, Provider-Laufzeit, Belegt-Hinweis). Verbindungslinien als SVG-Overlay zwischen den Spalten: durchgezogen = Vorschlag, gestrichelt = Zweitbeste (aus `second_best`), grün = bestätigt. Kopfleiste: Disc-Kontext, Fortschritt („4 von 6 entschieden"), Zonen-Legende, „alle ≥ 0.9 bestätigen"-Sammelaktion (zeigt Anzahl; disabled, wenn keine qualifiziert).

### Props-Vertrag

```ts
interface MappingReviewProps {
  disc: DiscHeaderRef;
  candidates: ReviewCandidate[];     // Playlists mit Vorschlägen + Alternativen
  searchSpace: ReviewEpisode[];      // Suchraum inkl. Belegung durch andere Discs
  context: { source: 'S-01'|'S-02'|'S-03'|'S-04'; label: string;
             breadthWarning: string | null };   // z. B. "Suchraum: ganze Serie"
  reviewTaskId: Ulid | null;         // gesetzt, wenn über Review-Inbox betreten
}
```

### Interaktionen (vollständig)

| Interaktion | Geste | Wirkung (Action) |
|---|---|---|
| Vorschlag bestätigen | Klick auf Linie ⇒ Bestätigen-Knopf; Tastatur `Enter` auf fokussierter Linie | `ConfirmDiscEpisodeMapping` |
| Sammel-Bestätigung | Kopfleisten-Knopf | n × Confirm, sequenziell mit Fortschrittsanzeige; Abbruch stoppt nach laufender Action |
| Mapping umziehen | Drag Kandidat auf Episode (oder Linie greifen) | `RemapDiscPlaylist` |
| Mapping lösen | Linie fokussieren ⇒ Löschen-Knopf / `Entf` | `RejectDiscEpisodeMapping` |
| Umklassifizieren | Kontextmenü Kandidat („ist Bonus", „ist Play-All", …) | Umklassifikations-Action; Kandidat verlässt die Fläche animiert |
| Doppelfolge teilen | Kontextmenü „Doppelfolge teilen" | öffnet Segment-Editor |
| Alternativen ansehen | Hover/Fokus Kandidat | Zweitbest-Linie + Score-Vergleich als Popover („beste 0.92 / zweitbeste 0.61") |

Kein optimistisches Fachverhalten (Modulkapitel): Jede Action zeigt Pending-Zustand auf der betroffenen Linie; Fehler (409-Konflikte, z. B. paralleler Review) erscheinen als Inline-Problem mit Reload-Angebot. Der Abschluss-Zustand („alle entschieden") löst den Review-Task serverseitig; das UI zeigt die Erfolgsfläche mit Weiter-Navigation (nächster offener Review derselben Bibliothek, falls vorhanden — Fließband-Arbeit für 60-Disc-Sammlungen).

### Segment-Editor

Modal über der Review-Fläche (auch aus `Discs/Show` erreichbar). Zeitachse der Playlist mit Kapitelmarken als Snap-Punkte (Magnetradius 2 s Darstellungszeit); Segmente als verschiebbare Bereiche mit Griff an jeder Grenze; Doppelklick teilt am Cursor, `Entf` verschmilzt mit Nachbar. Snapping ist Default an (`Alt` löst); Grenzen ohne Marke im Fenster zeigen den `no_snap_mark`-Hinweis ([Mapping-Algorithmus](mapping-algorithm.md), Stufe 4). Je Segment: Kind-Auswahl + Episoden-Zuordnung (Suchfeld über den Suchraum). Speichern = `DefineDiscSegments` + Mapping-Actions atomar sequenziert; der 409-Fall `segments_in_use` führt in einen geführten Konfliktdialog (betroffene Mappings mit Lösen-Option). Vorschau-Leiste unten: resultierende Episoden-Anrechnung („Position 51:30 → S03E08 bei 08:20") — live gerechnet aus den Editor-Grenzen, als reine Anzeige-Arithmetik (keine Fachlogik: dieselbe Formel wie serverseitig, `position − start`).

## `DiscSets/Show`

Reihenfolge-Verwaltung (Drag-and-Drop-Liste; Persistenz als atomarer `disc_ids`-Ersatz, [API-Referenz](api-reference.md)), Container-Verknüpfung (Katalog-Suchfeld, nur Container wählbar — Client filtert Anzeige, Server validiert), Bestätigen-Aktion mit Erklärtext („aktiviert Set-Kontext für Klassifikation und Mapping"). Kernstück **Mapping-Matrix**: Zeilen = Episoden des Containers (Provider-Reihenfolge), Spalten = Discs (Positionsreihenfolge), Zellen = Mapping-Status (leer/vorgeschlagen/bestätigt, Watch-Status als Zellfüllung). Die Matrix beantwortet auf einen Blick „ist die Box vollständig?": vollständig bestätigte Spalten tragen ✓ im Spaltenkopf, unbelegte Episoden-Zeilen sind das sichtbare Loch. Zell-Klick navigiert zur Review-Fläche der Disc mit vorfokussiertem Kandidaten. Bei > 50 Episoden virtualisiert die Matrix die Zeilen (Design-System-Standardmuster für lange Listen).

## Dashboard-Karte: manuelle Playback-Bestätigung

Erscheint je offener `manual_ack`-Aufgabe (open_close-Sessions, [Playback-Übersetzung](playback-translation.md)). Inhalt: Zeitfenster („Gestern 21:40–23:55"), Disc (Cover + Label), Player-Instanz; darunter die gemappten Episoden als Knopfreihe (Episodennummer + Titel, Watch-Status-Icon bei bereits Gesehenem) und „nichts anrechnen". Mehrfachauswahl möglich (Toggle-Knöpfe, dann „anrechnen"). Keine Vorauswahl (Modulkapitel: das System rät nicht). Nach Aktion verschwindet die Karte animiert; verfallene Karten (14-Tage-Frist) erscheinen nie im Dashboard, bleiben aber in `Discs/Show` im Session-Log sichtbar.

## Komponenten-Inventar (Disc-spezifisch, wiederverwendbar)

| Komponente | Verwendung | Props-Kern |
|---|---|---|
| `DiscKindBadge` | überall | `kind` |
| `ChapterAnatomyBar` | Show, Review | `positions: number[]` (relativ), `compact?` |
| `ConfidenceBadge` | Show, Review, Matrix | `value: number`, Zonen-Färbung (≥0.9 / 0.6–0.9 / <0.6) |
| `ClassificationChip` | Show, Review | `classification`, `confidence`, `locked` (manuell) |
| `SegmentTrack` | Show (Chips), Editor (interaktiv) | `segments`, `durationMs`, `editable?` |
| `MappingLineLayer` | Review | SVG-Overlay, Fokus-/Tastatur-Verwaltung |
| `DiscProgressRing` | Index, Set-Matrix | `watched`, `mapped`, `derived` |
| `ManualAckCard` | Dashboard, Show | `session`, `episodes`, `onAcknowledge` |

Alle Komponenten sind präsentational (Props rein, Events raus); Action-Aufrufe leben in den Seiten (Inertia-`router`-Aufrufe auf die Action-Routen) — Testbarkeit über Props-Snapshots, Interaktionstests der Seiten über die Inertia-Test-Harness ([developer-handbook/testing.md](../../developer-handbook/testing.md)).

## Tastatur und Zugänglichkeit

Review-Fläche vollständig tastaturbedienbar: `Tab` wandert über Kandidaten in Vorschlagsreihenfolge, `Enter` bestätigt fokussierten Vorschlag, `R` öffnet Remap-Suchfeld, `Entf` löst, `S` Segment-Editor, `?` Shortcut-Übersicht (Design-System-Standard). Verbindungslinien sind als `aria-describedby`-Beziehungen semantisch abgebildet („Playlist 00004, 43 Minuten, Vorschlag: Staffel 3 Episode 8, Konfidenz 0.92, zweitbeste Alternative Episode 9"); der SVG-Layer selbst ist `aria-hidden`. Farbcodierungen (Zonen, Segment-Kinds) tragen redundante Form-/Text-Marker (Design-System-Verpflichtung, keine Nur-Farbe-Information). Der Segment-Editor bietet numerische Grenzen-Eingabe (mm:ss) als gleichwertige Alternative zum Draggen.

## Fehler- und Sonderzustände

| Zustand | Anzeige |
|---|---|
| `analysis='failed'` | Show-Seite mit Fehlerpanel (analysis_error, Anomalien), Aktionen: neu analysieren, Datei prüfen (Link auf Datei-Detail) |
| `analysis='running'` | Fortschritts-Panel (Operations-Status via Echo), Tabelle der letzten bekannten Struktur bleibt sichtbar mit „veraltet"-Banner |
| Obfuskations-Verdacht | Warn-Banner auf Show/Review („Struktur-Obfuskation erkannt — Klassifikation konservativ"), Link auf Regelkatalog-Erklärung |
| verwaiste Review-Fläche (Disc gelöscht) | Inertia-Redirect auf Index mit Toast |
| Suchraum leer (S-05) | Review zeigt Zuordnungs-Dialog („Disc zuordnen") statt Kandidatenfläche |
| Player-Öffnung fehlgeschlagen | Toast mit Player-Fehlerdetail vom Connector, Session bleibt unberührt |

## Telemetrie-freie Gestaltung

Bewusste Festlegung: Das Review-UI erhebt keine Interaktions-Telemetrie (keine Klickpfade, keine Zeitmessung pro Review). Die einzige „Metrik" ist das Audit (wer hat wann was bestätigt) — das genügt für die Frage „wie viel Review-Arbeit fällt an" (Auswertung über Audit-Aggregate im Admin-Dashboard) und respektiert, dass MediaForge ein Self-Hosted-System ohne Nutzungsauswertung ist ([architecture/security.md](../../architecture/security.md), Datenminimierung).
