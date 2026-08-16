# Optional AI Capabilities, Model Registry and Evaluation

Status: **verbindliche Architekturregel**

## AI ist optional

MediaForge muss ohne AI/3D vollständig für Filme, Serien, Hörbücher, Adult-Katalog, Playback, Metadata, Acquisition und Plugins funktionieren.

Große Modelle werden erst nach expliziter Aktivierung heruntergeladen. UI und API behandeln fehlende Capabilities als normalen Zustand, nicht als Fehler.

Beispiele:

```text
capability.analysis.video_events
capability.analysis.audio_events
capability.analysis.body_reconstruction
capability.analysis.tattoo_projection
capability.analysis.embeddings
capability.audio.restoration
```

## Model Registry

Jedes lokale Modell besitzt mindestens:

- `model_id`;
- Provider/Adapter;
- Version;
- Content Hash;
- Modell-/Runtime-Anforderungen;
- Lizenz und erlaubte Nutzung;
- Hardwareanforderungen;
- unterstützte Capabilities;
- Evaluation-Version und Kennzahlen;
- enabled/disabled/rollback state.

Ein Modell darf nicht für einen Domain-Use aktiviert werden, wenn seine Lizenz diesen Use nicht erlaubt.

## Golden Verification Dataset

Manuell verifizierte lokale Beispiele können opt-in als Evaluationsset dienen. Neue Modellversionen werden gegen dieselben Fälle verglichen. Eine Regression darf nicht still als Upgrade aktiviert werden.

## Resource Scheduler

Priorität:

1. Playback;
2. interaktive Benutzeraktionen;
3. notwendiges Transcoding;
4. interaktive Analyse;
5. Background AI;
6. Re-Analyse/Backfill.

Der Scheduler berücksichtigt CPU, RAM, GPU/VRAM, Temperatur/Load sofern verfügbar und aktive Playback-Sessions.
