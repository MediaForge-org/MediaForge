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
