# AI Engine

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).

Die AI Engine ist lokal gedacht und optional erweiterbar. MediaForge muss ohne AI Worker vollständig funktionieren; AI verbessert Workflows, wird aber nie Pflicht.

## Funktionen

- Kapitelvorschläge
- Intro- und Outro-Erkennung
- Cover-Auswahl
- Duplicate Detection
- Audio Cleanup
- Subtitle Alignment
- Tag-Vorschläge
- Szenen- und Kapitelanalyse
- Qualitätsbewertung
- Metadatenvorschläge
- Sprecher-/Performer-Hilfen, soweit lokal und zulässig
- Bild-/Cover-Qualitätsbewertung

Externe AI-Dienste dürfen höchstens optionale, klar gekennzeichnete Anbieter sein.

## Querverweise

Die technische Engine steht in [modules/ai-engine.md](../modules/ai-engine.md), Audio-Upscaling in [modules/audio-upscaler.md](../modules/audio-upscaler.md), Embeddings in [modules/search/embedding-spec.md](../modules/search/embedding-spec.md).

## Akzeptanzkriterien

- MediaForge startet und funktioniert ohne AI Worker.
- Modellidentität, Version und Hash sind Pflichtbestandteile jedes AI-Ergebnisses.
- AI-Ergebnisse schreiben nie direkt in offizielle Providerfelder.
- Externe AI-Anbieter sind opt-in und müssen Datenabfluss vor Aktivierung sichtbar machen.
