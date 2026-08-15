# Engine Contracts

Status: verbindliche Zielarchitektur; konkrete v1-Schemas werden in `packages/contracts` versioniert.

## 1. Zweck

Engine Contracts entkoppeln MediaForge UI/Katalog von Jellyfin-, Stash- und Audiobookshelf-derived Implementierungen. Ein Engine-Wechsel darf keine MediaForge-IDs oder sichtbaren URLs brechen.

## 2. Basiskontrakt

```text
Engine
├── health()
├── version()
├── capabilities()
├── diagnostics()
└── lifecycleStatus()
```

## 3. Library Engine

```text
listLibraries()
scan(request)
scanStatus(jobId)
listItems(cursor/filter)
resolveExternalIdentity()
```

Scan-Ergebnisse werden normalisiert und vom Server kanonisiert; Engine schreibt nicht direkt in Core-Tabellen.

## 4. Playback Engine

```text
preparePlayback(mediaRef, deviceProfile, userPreferences)
startSession()
stopSession()
reportEngineProgress()
listTracks()
selectTrack()
```

Antwort beschreibt Direct Play/Remux/Transcode, Stream Endpoint, Tracks und Capabilities.

## 5. Artwork/Preview Engine

```text
getArtwork()
generateThumbnail()
generatePreview()
generateTrickplay()
```

Jobs sind asynchron; Ergebnisse erhalten Content Hash/Version.

## 6. Progress Engine

MediaForge ist Eigentümer des systemübergreifenden Progress. Engines können technischen Playback-State liefern, aber Core normalisiert und speichert canonical progress.

## 7. Analysis Engine

Optional:

```text
analyzeMedia()
analysisProgress()
getAnalysisArtifacts()
```

AI-/Adult-Analyseergebnisse folgen dem Event/Evidence-Schema.

## 8. Download Client Contract

```text
health()
add()
pause()
resume()
cancel()
remove()
status()
listFiles()
```

SABnzbd/qBittorrent werden Adapter auf diesen Vertrag.

## 9. Disc/MediaTools Contract

```text
probeFile()
analyzeDiscStructure()
extractPlaylistDurations()
generateSidecar()
splitAudioChapters()
```

## 10. Capability-first

UI fragt MediaForge, was verfügbar ist. Keine Seite soll `if engine == jellyfin` enthalten.

## 11. IDs

Engine IDs sind External Mappings:

```text
media_external_mappings
engine_external_mappings
```

Core-FKs referenzieren nur MediaForge IDs.

## 12. Fehler

Engine-spezifische Fehler werden auf stabile Error Codes normalisiert:

```text
ENGINE_UNAVAILABLE
CAPABILITY_NOT_SUPPORTED
PLAYBACK_PREPARATION_FAILED
SCAN_FAILED
ANALYSIS_FAILED
RATE_LIMITED
AUTH_FAILED
```

Details dürfen diagnostics enthalten, aber UI muss mit Codes arbeiten können.

## 13. Contract Versioning

Breaking Change -> neue Contract-Version. Während Migration können zwei Versionen parallel unterstützt werden.

## 14. Tests

- JSON Schema/OpenAPI validation;
- generated client compilation;
- provider fixtures;
- contract conformance in jeder Engine;
- end-to-end playback/scan smoke tests.
