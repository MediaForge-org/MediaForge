# Work, MediaItem, Edition, Cut und File – gemeinsames Modell

Priorität: **P0**

## Grundsatz

Physische Datei, technische Edition und inhaltliches Werk sind verschiedene Ebenen.

## Allgemeines Modell

```text
Work
└── MediaItem / Release
    └── ContentVariant (Cut/Edition when semantic)
        └── TechnicalEdition
            └── MediaFile(s)
```

Je nach Medientyp werden Ebenen angepasst.

## Film

```text
Blade Runner (Work)
├── Theatrical Cut
├── Director's Cut
├── Final Cut
│   ├── UHD Blu-ray Remux
│   ├── 4K AV1 Encode
│   └── 1080p Blu-ray
└── Workprint
```

**Cut** = inhaltliche Variante.
**Technical Edition** = andere Quelle/Encode desselben Cuts.

## Serie

```text
Episode
├── Broadcast Version
├── Blu-ray Version
├── Streaming Version
└── Local Encodes
```

## Adult

```text
Canonical Scene
├── Original Cut
├── Alternate Edit
├── Compilation Appearance
└── Local Technical Editions
```

## Audiobook

```text
Audiobook Work
├── German unabridged – Narrator A
│   ├── Single FLAC
│   ├── M4B
│   └── Chapter Files
└── English unabridged – Narrator B
```

## Duplicate Detection

Gleicher Titel ist kein ausreichendes Duplicate-Kriterium. Laufzeit-/Fingerprint-/Source-/Cut-/Edition-Facts werden berücksichtigt.

## Best-Version Selection

Playback kann eine bevorzugte Technical Edition automatisch auswählen nach:

- User Quality Profile;
- source priority;
- HDR capability;
- audio capability;
- device compatibility;
- storage/network constraints.

User kann Auswahl überschreiben.

## Cross-Edition Progress Mapping

Wenn zwei Editionen inhaltlich gleich sind, aber leicht andere Laufzeiten/Intros besitzen, kann MediaForge Content-Fingerprint-/Timeline-Mappings speichern:

```text
source edition position -> canonical content position -> target edition position
```

So kann ein Benutzer von 1080p auf 4K wechseln und möglichst an derselben inhaltlichen Stelle fortsetzen.

Dieses Mapping ist optional und muss bei unklaren Cuts deaktiviert werden.
