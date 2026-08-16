# Feature Screen Specifications – verbindliche UI-Funktionsbeschreibung

Diese Datei sagt Claude **nicht nur wie die Screens aussehen**, sondern was jedes Element fachlich bedeutet. Die PNGs sind visuelle Referenz; diese Spezifikation ist die fachliche Referenz.

## Allgemeine Regel

Keine Referenz ist pixelgenau zu kopieren. Beibehalten werden müssen:

- Informationshierarchie;
- Komponententypen;
- sichtbare Kernaktionen;
- Status-/Progress-Semantik;
- Premium Dark Design;
- MediaForge Navigation/Design Tokens;
- keine generische Admin-Optik.

---

## `30_acquisition_center.png` – Acquisition Center

### Zweck
Zentrale Oberfläche für Download-/Acquisition-Jobs, Client Health und Importstatus.

### Muss enthalten

- Tabs: Active, Queue, Completed, Import Sandbox, Client Health;
- SABnzbd/qBittorrent als MediaForge Cards mit Health/Speed/Queue;
- Active Jobs mit Fortschritt, Speed, ETA, Pause/Details;
- Drag & Drop Intake;
- Import Pipeline Status;
- Quality Upgrade Recommendations;
- Recent Imports/Queue Summary;
- Source/Quality Intelligence.

### Nicht tun

- fremde Downloader-Web-UIs iframe'en;
- Indexer-Suche als Piraterieportal bauen;
- Download und finalen Library Import ohne Staging vermischen.

---

## `31_manual_download_intake.png` – Manual Intake

### Zweck
Eine `.nzb`, `.torrent` oder Magnet-Eingabe vor dem Start verstehen.

### Muss enthalten

- Upload/Dropzone + Magnet Input;
- detected media match;
- technische File Analysis;
- Match Evidence/Status;
- Target Library;
- Download Client;
- Destination Preview;
- Quality/Source Summary;
- Post-Download Action;
- klare Start-Aktion.

Confidence ist Hinweis, nicht Beweis. Bei unklarem Match wird Review verlangt.

---

## `32_import_sandbox_upgrade_review.png` – Import Sandbox

### Zweck
Nach Download vor finalem Dateisystem-Write prüfen.

### Muss enthalten

- Download Candidate;
- ffprobe/technical analysis;
- existing vs incoming edition comparison;
- Quality Score als erklärbarer Hilfswert;
- Provenance;
- Import Impact;
- Rename & Move Preview;
- Keep Both / Replace / Reject / Review Actions;
- Watch/Metadata Preservation Hinweis.

---

## `33_adult_scene_full_analysis_timeline.png` – Adult Full Analysis

### Zweck
100%-Coverage Analyse und Event-Timeline einer lokalen Scene.

### Muss enthalten

- Visual/Audio/Speech/Manual Lanes;
- klickbare Event Bars/Markers;
- Start-/Endzeiten;
- Event Filter;
- Analysis Coverage;
- Detector/Verification State;
- Evidence Thumbnails;
- Jump-to-time;
- AI Summary nicht als unfehlbare Wahrheit formulieren.

---

## `34_adult_tag_taxonomy_event_inspector.png` – Taxonomy/Event Inspector

### Zweck
Hierarchische Tag-Definitionen und konkrete Event-Instanzen verwalten.

### Muss enthalten

- Taxonomy Tree;
- Base Tag Definition;
- Attribute Groups;
- Aliases/Relationships;
- konkretes Event mit Timestamp;
- visual/audio Evidence;
- Confidence + Verification;
- Review/Correct/Create Training Example;
- Occurrence List.

---

## `35_adult_metadata_provenance_date_conflict.png` – Date/Provenance Resolver

### Zweck
Mehrere Source-Facts vergleichen, ohne blind den Aggregatorwert zu übernehmen.

### Muss enthalten

- mehrere Date Types;
- Quellen als Spalten/Fact Cards;
- Authority/Trust Erklärung;
- Canonical Choice;
- Konfliktstatus;
- Override/Unresolved/Review;
- Audit Timeline;
- Field-level Summary.

---

## `36_performer_catalog_completeness.png` – Performer Coverage

### Zweck
Kompletten bekannten Katalog einer Performerin und lokale Abdeckung verstehen.

### Muss enthalten

- Known / Local / Missing / Historical / Unresolved;
- Coverage %;
- By Year;
- Top Studios;
- Recent Releases;
- Missing locally cards;
- Relationship/Network Preview;
- Actions: Sync, Review Missing, Acquisition, Export Coverage.

---

## `37_audiobook_single_file_chapter_verification.png` – Single-File Chapter Verification

### Zweck
Eine einzelne große Hörbuchdatei exakt einer Edition zuordnen und offizielle Kapitel laden/aligne'n.

### Muss enthalten

- File + waveform;
- Edition Identification;
- Narrator/Publisher/Language/Edition Type;
- Source Match/Provenance;
- Chapter Table;
- Start/End/Duration;
- official/aligned/detected/manual states;
- Preview Player;
- Generate CUE/JSON actions.

---

## `38_audiobook_storage_strategy.png` – Storage Strategy

### Zweck
Benutzer entscheidet bewusst, ob Kapitel nur logisch oder physisch gespeichert werden.

### Muss enthalten

1. Only in MediaForge;
2. MediaForge + CUE/Sidecar;
3. Split into Chapter Files;
4. Naming Preview;
5. Output Folder;
6. Original Handling: Keep/Archive/Delete;
7. Expected Output Summary;
8. Warnung vor Delete/Reencode.

Original Keep ist Default.

---

## `39_series_episode_order_and_editions.png` – Episode Orders & Editions

### Zweck
Eine Episode mit mehreren Ordnungen, Timeline-Segmenten und Editionen darstellen.

### Muss enthalten

- schöne sprechende URL in Breadcrumb/Address Preview;
- Episode Hero;
- Aired/DVD/Streaming/Production Orders;
- Intro/Recap/Credits/Preview Timeline;
- Skip Options;
- mehrere Technical Editions;
- Edition Comparison;
- Next Up;
- Cast/Screenshots/History.

---

## `40_feature_overview_p0_p2.png` und `41_feature_matrix_detailed.png`

Diese Poster sind **Querschnittsreferenzen**, keine einzelnen Seiten. Claude soll daraus die Feature-Zusammenhänge und Prioritäten lesen:

### P0 – jetzt im Datenmodell/Architektur vorbereiten

- Event/Attribute/Provenance;
- Scene Lineage;
- Multiple Episode Orders;
- Cut vs Edition;
- Audiobook Work/Edition/Chapter;
- Work Graph;
- vollständige Analyse-Run-Metadaten.

### P1 – nach brauchbarem Core

- Evidence Viewer;
- Review Center;
- Smart Collections;
- Transcript Search;
- Cross-Edition Progress;
- Timeline Detection;
- Acquisition Center.

### P2 – fortgeschritten

- Active Learning/Fine-Tuning;
- Speaker/Character Recognition;
- reichhaltige Work-Graph-Visualisierung;
- Historical Source Archive UX;
- weitere Spezialanalysen.

---

# Interaction Design Regeln

## Status

Nutze konsistente Statusfamilien:

- green = verified/healthy/completed;
- amber = warning/review/ambiguous;
- red = failed/destructive;
- purple/blue = active/selected/information;
- muted = unavailable/not configured.

## Destructive Actions

Delete Original, Replace File, Remove Metadata Source usw. müssen klar getrennt und bestätigungspflichtig sein.

## Long-running Jobs

Keine blockierenden Vollseiten-Spinner. Jobs laufen asynchron, haben Progress, ETA soweit belastbar, Pause/Cancel nur wenn technisch unterstützt.

## Confidence

Confidence darf sichtbar sein, aber immer neben Evidence/Verification State. Kein Prozentwert darf als alleiniger „Verified“-Beweis verwendet werden.

## Referenzen 42–67 — Erweiterung 2026-08-16

> Alle 3D-Körper in diesen PNGs sind **neutrale UI-Platzhalter**. Die Screens definieren Layout/Interaktion, nicht die exakte Rekonstruktionsqualität. Technische Textlabels in generierten Mockups sind nicht autoritativ; Code/Architektur-Dokumente sind Source of Truth.

### 42 — Unified Dashboard Next Generation
Home-/Systemoberfläche mit klaren Modulen, Status und konsistentem MediaForge-Look.

### 43 — Female Performer Tattoo Profile
Performer-Tattoo-Coverage mit Gesamtwert, Regionsergebnissen, Confidence und Evidence.

### 44 — Advanced Tattoo Region Filter
Feine Anatomie-Filter statt nur Arms/Torso/Legs. Slider/Regionenfilter müssen auf stabile Anatomy IDs mappen.

### 45 — Scene Analysis Timeline v2
Visuelle/Audio-Events, anklickbare Timestamps, Confidence und Analysefortschritt.

### 46 — Plugins & Themes Overview
Getrennte Extension Types, aktiv/deaktiviert, Compatibility und Permissions.

### 47 — Architecture/Roadmap Overview
Nur visuelle Orientierung; Technologie-Namen in Mockups sind nicht bindend.

### 48 — Tattoo Coverage Analysis
Gesamt-Coverage, Coverage Class, observed/estimated/unknown surface und Region Breakdown.

### 49 — Tattoo Region Coverage Inspector
Eine konkrete Anatomy Region mit Surface %, Evidence und manueller Review-Möglichkeit.

### 50 — Tattoo Evidence Fusion
Mehrere Scenes/Views werden zu einem Performer-Profil zusammengeführt; Evidence-Qualität ist sichtbar.

### 51 — Tattoo Coverage Profile Dashboard
Coverage-Historie/Confidence und regionale Zusammenfassung. Count ist sekundär zur Fläche.

### 52 — Tag Ontology Editor
Parent/Child-Tags, Attributes, Synonyme, Mappings und Detector Profile.

### 53 — Scene Event Inspector
Ein Event mit Start/End, Visual/Audio-Evidence, Attributes und Verify/Reject/Edit.

### 54 — Full Analysis Report
Messbare 100%-Decode-Coverage, Detector-/Model-Versionen, Dense Refinement und offene Unsicherheiten.

### 55 — Plugin Marketplace
Plugin-Typ, Version, Quelle, Compatibility, Permissions und Install/Update-Zustand.

### 56 — Theme Editor / Custom CSS
Design Tokens, CSS Variables, scoped Preview, Import/Export und Advanced Global CSS.

### 57 — Runtime Architecture
Zeigt die sichtbare Ein-Produkt-Architektur. Verbindliche Technologien stehen in `target-monorepo.md`.

### 58 — Docker Deployment
Core/Engines/optional AI, Volumes, Health und ein sichtbarer Gateway-Einstiegspunkt.

### 59 — Green Commit Workflow
Letzter grüner Commit -> Arbeitspaket -> lokale Gates -> Commit -> Push -> GitHub Actions -> Fix/Rollback oder nächstes Paket.

### 60 — 36-Track Roadmap
720 Prompts bleiben 36 × 20; neue Features werden in vorhandene Tracks integriert.

### 61 — 3D Reconstruction Workspace
Evidence Selection, Multi-view Fusion, Progress, Quality und Erzeugen einer neuen immutable ReconstructionRevision.

### 62 — 3D Performer Viewer
Rotate/Zoom/Region Select sowie Tattoo/Region/Confidence Overlays und Revision Compare.

### 63 — 3D Quality / Missing Surface
Observed/Estimated/Unknown Surface nach Region. Unknown ist niemals automatisch tattoo-free.

### 64 — 3D Tattoo Projection
Tattoo Masks aus mehreren Evidence Frames werden auf das kanonische Mesh projiziert und nach Confidence fusioniert.

### 65 — Body Surface Calibration
Mesh-basierte Körperform/Oberfläche, Source-backed Measurements und regionale Surface-Gewichte.

### 66 — 3D Analysis Settings Overview
Quality/Analysis-Modi, Evidence Retention, optional AI, Model/Storage Limits und Privacy.

### 67 — 3D Analysis Reference Board
Zusammenfassende visuelle Referenz für Reconstruction, Viewer, Projection, Calibration und Quality; Detail-Screens 61–66 haben Vorrang.
