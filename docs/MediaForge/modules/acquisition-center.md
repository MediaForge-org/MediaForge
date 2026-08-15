# Acquisition Center, Download Clients und Import Sandbox

Status: langfristige Produktspezifikation
Priorität: P1 nach Usable-Core-Gate; Datenmodell/Contracts P0 vorbereiten

## 1. Ziel

MediaForge soll Medienbeschaffung und Import **innerhalb derselben Oberfläche** orchestrieren, ohne Benutzer zu SABnzbd/qBittorrent-Web-UIs zu zwingen.

Unterstützte Eingänge:

- benutzerbereitgestellte `.nzb`;
- benutzerbereitgestellte `.torrent`;
- Magnet-Link;
- bereits heruntergeladene Datei/Ordner;
- externe, vom Benutzer konfigurierte Download-/Automation-Provider;
- offizielle Kauf-/Download-/Streaming-Links und benutzerdefinierte Source Links.

MediaForge wird **keine integrierte Piraterie-Suchmaschine**. Es kann generische Downloader integrieren und benutzerdefinierte/legitime Quellen verwalten; Zugangskontrollen/DRM werden nicht umgangen.

Referenzen:

- `30_acquisition_center.png`
- `31_manual_download_intake.png`
- `32_import_sandbox_upgrade_review.png`

## 2. Domänenmodell

```text
AcquisitionRequest
├── id
├── requested_for_media_item_id nullable
├── source_type
├── source_reference
├── desired_quality_profile_id nullable
├── status
└── created_by

DownloadJob
├── client_id
├── external_job_id
├── intake_type
├── original_name
├── expected_size
├── status/progress/speed/eta
└── staging_path

DownloadArtifact
├── path
├── size
├── hash
├── detected_type
└── probe_state

ImportCandidate
├── candidate_media_id nullable
├── match_evidence
├── quality_analysis
├── duplicate_analysis
└── review_state

ImportPlan
├── operations[]
├── final_paths[]
├── keep/replace/edition policy
└── approval/audit
```

## 3. Download Client Contract

Clients wie SABnzbd und qBittorrent implementieren ein gemeinsames Interface:

```text
health()
add(input)
pause(job)
resume(job)
cancel(job)
remove(job)
status(job)
listFiles(job)
setPriority(job/file)
setCategory(job)
```

Credentials werden verschlüsselt gespeichert. Client-spezifische IDs bleiben Mappingdaten.

## 4. Intake vor Download

MediaForge analysiert die Intake-Datei, soweit sicher möglich, **bevor** sie an den Downloader geht.

Torrent:

- Name;
- File-Liste;
- Größe;
- mögliche Staffel-/Episodenstruktur;
- mögliche Media-Matches;
- Tracker-Metadaten nur als technische Information.

NZB:

- Name;
- erwartete Dateien/Archive soweit aus der Struktur ableitbar;
- Größe;
- Media-Match-Kandidaten.

Das UI zeigt nicht bloß „Datei hochgeladen“, sondern eine verständliche Zusammenfassung und Zielbibliothek.

## 5. Staging und Import Sandbox

Downloads landen standardmäßig in einer Staging Area und **nicht direkt in der finalen Bibliothek**.

Pipeline:

```text
Downloaded
 -> integrity check
 -> unpack (wenn konfiguriert)
 -> ffprobe / file analysis
 -> media matching
 -> duplicate/edition analysis
 -> quality comparison
 -> rename/move preview
 -> approval/automatic verified rule
 -> import
```

## 6. Season-/Multi-File-Import

Ein Download kann viele MediaItems erzeugen:

```text
Season Pack
├── S01E01 -> Episode 1
├── S01E02 -> Episode 2
└── ...
```

Jeder Kandidat erhält sein eigenes Match und seinen eigenen Importstatus. Ein defektes File darf nicht zwingend den gesamten Pack blockieren, wenn Policies einen Teilimport erlauben.

## 7. Quality Intelligence

MediaForge vergleicht Incoming Candidate und vorhandene Editionen anhand von:

- Quelltyp (UHD Blu-ray/Blu-ray/WEB/TV/...);
- Cut/Edition;
- Auflösung;
- HDR/Dolby Vision;
- Codec;
- Bitrate nur als Signal, nicht als absolute Wahrheit;
- Audioformat/Kanäle;
- Untertitel;
- Laufzeit;
- technische Qualitätsanalyse.

Mögliche Entscheidungen:

```text
Upgrade existing edition
Keep both editions
Reject as downgrade
Import as alternate cut
Needs review
```

## 8. Source-Capped Quality

Das System unterscheidet:

- offizielle Source Quality;
- konkrete Release Quality;
- Encode Quality;
- reconstructed/upscaled Quality.

Ein AI-Upscale wird nicht automatisch als „besser als offizielles Maximum“ klassifiziert.

## 9. Provenienz

Jeder importierte File-Pfad kann später nachvollziehen:

```text
AcquisitionRequest
 -> DownloadJob
 -> DownloadArtifact
 -> ImportCandidate
 -> ImportPlan
 -> MediaFile
 -> Edition
 -> MediaItem
```

Diese Lineage erscheint im Provenance Inspector.

## 10. Automatisierung

Automatisches Importieren ist nur für Regeln erlaubt, deren Bedingungen deterministisch sind. Beispiele:

- eindeutiges Media-Match;
- keine Konflikte;
- verifizierte bessere Quality;
- erlaubter Zielpfad;
- keine manuellen Locks;
- kein unbekannter Cut.

Unklarheit -> Review Center.

## 11. Adult

Adult verwendet dieselbe Acquisition-Schicht, ist aber vollständig an Adult-Authorization gebunden. Adult-Acquisition-Jobs/Notifications/Artwork dürfen im gesperrten Modus nicht in normalen Feeds erscheinen.

## 12. UI

Acquisition Center zeigt:

- aktive Downloads;
- Client Health;
- Queue;
- Completed;
- Import Sandbox;
- Quality Upgrade Recommendations;
- Fehler/Retry;
- Source/Provenance;
- Drag & Drop Intake.

Die UI soll wie MediaForge wirken, nicht wie ein eingebettetes qBittorrent/SABnzbd.
