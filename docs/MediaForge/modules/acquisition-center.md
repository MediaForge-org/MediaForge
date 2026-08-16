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

## 13. Expanded acquisition architecture — 17 August 2026

Detailed automation/naming/post-processing requirements live in `acquisition-automation-and-postprocessing.md` and ADR-0027.

Binding additions:

- MediaForge owns the normal Acquisition UI; SABnzbd/qBittorrent/Prowlarr/Sonarr/Radarr/Whisparr are backend capabilities, not separate day-to-day product surfaces.
- Support broad provider ecosystems through Newznab, Torznab, Prowlarr-managed definitions, Jackett-compatible Torznab endpoints, native provider plugins, RSS and a Browser Companion/manual fallback instead of a small hard-coded tracker whitelist.
- Drag/drop intake classifies the target media/library before submission where evidence is sufficient.
- `AcquisitionBlueprint` records expected identity, source, downloader, naming, seeding, disc/remux/transcode and final-library policy before automation begins.
- MediaForge is final naming authority after SAB repair/unpack. Default single-media Usenet output can use the sanitised NZB/job base name for both folder and main media file; secrets/password markers never survive into final names.
- Torrent imports preserve seeding by default through hardlinks/reflinks/copies; any active-payload rename is performed through qBittorrent APIs rather than behind the client's back.
- Per-tracker seeding policies, Wanted/Upgrade monitoring, explainable release scoring, failure fallback, provenance, quarantine, storage forecasts and resource scheduling are first-class.
- Disc/ISO inputs can branch into verified episode/extra extraction, Matroska remux and optional H.264/H.265/AV1 derived outputs.

Additional UI references:

- `68_backend_capabilities_acquisition_overview.png`
- `69_localization_translation_acquisition_overview.png`
