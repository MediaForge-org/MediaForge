# Derived Asset and Storage Manager

## Ziel

Generated Assets dürfen den Medienserver nicht unkontrolliert füllen. MediaForge macht Storage-Verbrauch sichtbar und steuerbar.

UI-Kategorien:

- AI Models;
- 3D Models/Reconstruction;
- Evidence;
- Tattoo Masks;
- Thumbnails/Trickplay;
- Embeddings;
- Transcripts;
- temporary cache.

Der Nutzer kann je Kategorie oder global Limits setzen und gezielt bereinigen.

## Sicherheitsregel

Keine Cleanup-Aktion löscht Originalmedien. `Remove downloaded AI models` deaktiviert nur die Capability; kanonische Library-/Metadata-Daten bleiben erhalten.

## AI opt-in

Bei Aktivierung zeigt MediaForge vorab Model-Download, empfohlenen Arbeits-Speicher und Hardwareanforderungen. Ohne Aktivierung entstehen keine großen AI-/3D-Artefakte.
