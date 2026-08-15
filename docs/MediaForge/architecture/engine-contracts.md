# Engine Contracts

Status: Zielvertrag; Implementierung schrittweise

## Zweck

Engine-Verträge entkoppeln die MediaForge-Oberfläche und den kanonischen Katalog von Jellyfin, Audiobookshelf, Stash-derived Adult und der Disc Engine.

## Kernverträge

```text
Engine
├── health()
├── capabilities()
├── version()
└── lifecycle()

LibraryEngine
├── listLibraries()
├── scan()
├── getItems()
└── resolveExternalIdentity()

PlaybackEngine
├── preparePlayback()
├── getCapabilities()
├── reportProgress()
└── stop()

ArtworkEngine
├── getArtwork()
├── generateThumbnail()
├── generatePreview()
└── generateTrickplay()

SearchEngine
└── search()

ProgressEngine
├── readProgress()
└── writeProgress()
```

Die konkreten Signaturen werden erst mit der Implementierungsphase versioniert. Fachlich sind folgende Regeln jetzt verbindlich.

## Regeln

1. Alle Engine-Antworten werden auf kanonische DTOs normalisiert.
2. Jede Engine-Referenz wird über `media_external_mappings` bzw. eine dedizierte Engine-Mapping-Tabelle auf MediaForge-ULIDs abgebildet.
3. Eine Engine darf MediaForge-Core-Tabellen nicht direkt schreiben.
4. Engine-spezifische Fehler werden auf stabile MediaForge-Fehlercodes normalisiert.
5. Capabilities werden abgefragt, nicht vorausgesetzt.
6. UI-Features werden aus Capabilities aktiviert; keine `if (engine === "jellyfin")`-Logik in Seitenkomponenten.
7. Engine-Wechsel/Fork-Migration muss möglich sein, ohne MediaForge-IDs zu ändern.
8. Adult-Engine-Aufrufe unterliegen zusätzlich der Zero-Leak-Policy.

## Beispiel Capability Set

```json
{
  "streaming": true,
  "transcoding": true,
  "trickplay": true,
  "discMenus": false,
  "sceneMetadata": false,
  "audiobookChapters": false
}
```

## Versionierung

Spätestens vor V34 wird `Engine Contract v1` eingefroren. Bis dahin dürfen interne Verträge evolvieren, müssen aber über Contract-Tests abgesichert werden.
