# Upscale-Profile und Audio-Metriken: Referenz

Vertiefung zu [modules/audio-upscaler.md](../audio-upscaler.md), Abschnitte „Datenmodell" (Profile), „SQL-Schema" (Metrik-JSONBs) und „Finalize". Normativ für: die Task-Parameter-Schemata der Profile, das Metrik-Schema `audio-metrics/v1`, die Preflight-Entscheidungsregeln (Sinnhaftigkeit, Domänen-Warnungen) und die A/B-Vergleichsdaten. Ergänzt das [Worker-Protokoll](worker-protocol.md); die Messungen selbst implementiert das Audioanalyse-Modul ([modules/audio-analysis.md](../audio-analysis.md)) hinter `AudioMetricsInterface`.

## Task-Katalog und Parameter-Schemata

Je `task`-Enum des Profils das normative Parameter-Schema (`params`-JSONB; Worker validieren task-spezifisch, [Protokoll](worker-protocol.md) Punkt 4). Gemeinsame Parameter aller Tasks: `target_sr` (Ziel-Samplerate: 22050/44100/48000; Default 44100), `channels` (`keep`/`mono`/`stereo`; Default `keep` — die Pseudo-Stereo-Erkennung des Preflight ändert nie selbst, sie schlägt vor, Modulkapitel), `chunk_overlap_ms` (Default 500, Bereich 250–2000).

| Task | Zweck | Spezifische Parameter (Default) |
|---|---|---|
| `speech_enhance` | Sprach-Restauration kombiniert (Denoise + Klarheit) | `denoise_strength` (0.6; 0–1), `dereverb` (false), `preserve_music` (true — Musik-Passagen in Hörbüchern nicht „entrauschen") |
| `bandwidth_extend` | Obertonrekonstruktion oberhalb der effektiven Bandbreite | `cutoff_detect` (`auto`), `max_extension_hz` (16000 — nie über die Trainingsdomäne hinaus versprechen) |
| `denoise` | Breitband-/Impulsrauschen | `denoise_strength` (0.5), `noise_profile` (`auto`/`hiss`/`hum`/`crackle`) |
| `declip` | Übersteuerungs-Rekonstruktion | `clip_threshold_db` (−0.1), `max_gain_reduction_db` (6) |
| `codec_artifact_reduce` | MP3/AAC-Artefakte (Pre-Echo, Sweep-Verschmierung) | `codec_hint` (`auto`/`mp3`/`aac`), `aggressiveness` (0.5) |
| `composite` | verkettete Tasks in einem Lauf | `pipeline: [task-refs in Reihenfolge]` (Validierung: max. 3, keine Duplikate, `bandwidth_extend` nur letzte Stufe — Erweiterung erfundener Obertöne wäre Doppel-Erfindung) |

Parameter außerhalb der Bereichsgrenzen scheitern in `RequestUpscale` (422, `upscaler.invalid_params`), nicht erst im Worker. Der Profil-Snapshot friert Task + Parameter + Modelltripel ein (Modulkapitel); die Schemata hier sind mit `params_schema_version` versioniert — ein Modell-Major-Update mit neuen Parametern erhöht die Version, alte Snapshots bleiben interpretierbar.

## Ausgelieferte Standard-Profile

Seed-Daten der Migration (Betreiber editieren/ergänzen; `admin`-Scope):

| Name | Task | Zielformat | Einsatz |
|---|---|---|---|
| `Hörbuch-Standard` | `speech_enhance` | flac | 64–128-kbit/s-Sprach-MP3s |
| `Hörbuch-Bandbreite` | `composite` (denoise → bandwidth_extend) | flac | dumpfe Alt-Rips |
| `Musik-Restauration` | `codec_artifact_reduce` | flac | 128-kbit/s-Musik |
| `Kassetten-Rettung` | `composite` (denoise → declip) | flac | Digitalisierungen |
| `Archiv-WAV` | `denoise` | wav | verlustfreie Weiterverarbeitung außerhalb |

Die Profile referenzieren Modell-Kennungen der Registry (offener Punkt des Modulkapitels: konkrete Modelle bestimmt die [AI Engine](../ai-engine.md)); Seeds ohne installiertes Modell erscheinen im UI als „Profil verfügbar, Modell nicht installiert" mit Volume-Hinweis.

## `audio-metrics/v1` (normativ)

Struktur der `metrics_before`/`metrics_after`-JSONBs; Messimplementierung im Audioanalyse-Modul, Konsumenten sind Preflight-Regeln, Metrik-Diff-UI und Health-Aggregate. Zeitreihen liegen als `analysis_report`-Artefakt daneben (Modulkapitel, Regel-8-Grenze).

```json
{
  "schema": "audio-metrics/v1",
  "analyzer_version": "1.2.0",
  "duration_ms": 21618004,
  "sample_rate": 22050, "channels": 2, "channel_correlation": 0.991,
  "codec": {"name": "mp3", "bitrate_kbps": 64, "vbr": false},
  "bandwidth_hz": {"effective": 10120, "method": "rolloff_-30db_p95"},
  "noise_floor_db": -52.4,
  "clipping_ratio": 0.00031,
  "loudness": {"lufs_integrated": -19.2, "lra": 9.4, "true_peak_db": -0.4},
  "speech": {"detected_ratio": 0.97, "music_ratio": 0.02},
  "windows_report_artifact": "01J…"
}
```

Feld-Normativa: `bandwidth_hz.effective` ist das 95. Perzentil der fensterweisen −30-dB-Rolloff-Frequenz (2-s-Fenster, Hann; das Perzentil macht die Messung robust gegen einzelne helle Effekte); `noise_floor_db` das 10. Perzentil der Fenster-RMS in erkannten Pausen (ohne Pausen: leisestes Perzentil gesamt, mit `method`-Vermerk); `clipping_ratio` der Sample-Anteil ≥ −0.1 dBFS in Plateaus ≥ 3 Samples; `speech.detected_ratio` aus dem Sprach/Musik-Klassifikator der Audioanalyse. Alle Kennwerte sind auf der **dekodierten** Wellenform gemessen (nie Header-Angaben); `codec` dokumentiert die Container-Sicht getrennt.

## Preflight-Entscheidungsregeln

Kaskade nach der Messung (Kennungen PF-nn; Settings `upscaler.preflight.*`):

* **PF-01 Vollbandigkeit** (`rejected_pointless`, Modulkapitel): Task enthält `bandwidth_extend` und `bandwidth_hz.effective ≥ 0.88 · (target_sr/2)` (`pointless_bandwidth_ratio` 0.88) ⇒ Ablehnung mit Metrik-Begründung.
* **PF-02 Rauschfreiheit**: Task ist `denoise`(-haltig) und `noise_floor_db ≤ −72` ⇒ Ablehnung („nichts zu entrauschen").
* **PF-03 Clip-Freiheit**: `declip` und `clipping_ratio < 10⁻⁵` ⇒ Ablehnung.
* **PF-04 Domänen-Warnung unten** (Modulkapitel): Quell-Bitrate < 48 kbit/s oder `bandwidth_hz.effective < 4000` ⇒ `domain_warning='below_trained_bitrate_domain'` (Lauf läuft, Warnung bis ins Artefakt).
* **PF-05 Musik-Schutz**: `speech_enhance` mit `preserve_music=true` und `music_ratio > 0.3` ⇒ Warnung `high_music_share` + UI-Hinweis, Profil `Musik-Restauration` zu erwägen (keine Ablehnung — Hörspiele sind legitime Grenzgänger).
* **PF-06 Pseudo-Stereo** (Modulkapitel): `channel_correlation > 0.98` und `channels='keep'` bei Stereo-Quelle ⇒ Vorschlags-Flag `mono_recommended` (halbierte Rechenzeit); die Entscheidung trifft der Anfordernde im UI-Dialog, nie der Preflight.
* **PF-07 Speicher** (Modulkapitel Edge Case): Schätzprüfung vor Chunk-Dispatch.
* **PF-08 Längen-Sanity**: Quelldauer < 10 s (`min_duration_ms`) ⇒ Ablehnung `source_too_short` (Chunk-/Crossfade-Mechanik degeneriert; Kurzclips sind kein Upscaler-Anwendungsfall).

Ablehnungen sind Fachzustände des Runs (`rejected_pointless` mit `error_detail` = PF-Kennung + Messwert), keine Fehler — sie kosten genau den Preflight (Modulkapitel) und erscheinen in der Processing History als vollwertige, erklärte Einträge.

## Erfolgskriterien und Nachmessung

Der Finalize misst identisch nach und berechnet das **Delta-Urteil** (persistiert in `worker_info.assessment`):

| Task | Erwartung (Default-Schwellen) | Bei Verfehlung |
|---|---|---|
| `bandwidth_extend` | `bandwidth_hz.effective` +≥ 2000 Hz | Warnung `no_measurable_gain` |
| `denoise`/`speech_enhance` | `noise_floor_db` −≥ 6 dB | Warnung |
| `declip` | `clipping_ratio` −≥ 50 % | Warnung |
| `codec_artifact_reduce` | (kein robustes Einzelmaß) | immer neutral; A/B entscheidet |
| alle | `lufs_integrated` |Δ| ≤ 1.5 LU; `true_peak_db ≤ −0.3` | **Validierungsfehler** `loudness_shift`/`peak_violation` — der Lauf darf klanglich verbessern, nie die Lautheit verschieben oder Übersteuerung neu einführen |

`no_measurable_gain`-Warnungen lassen Artefakt und Edition entstehen (das menschliche A/B-Urteil kann Messschwellen überstimmen), erscheinen aber prominent in der Vergleichsansicht — die dokumentierte Anti-Placebo-Mechanik zusammen mit dem Blind-Modus des UIs (Modulkapitel).

## A/B-Vergleichsdaten (Vertrag der `comparison`-Route)

```json
{"run_id": "01J…",
 "spectrograms": {"before": "signed-url", "after": "signed-url",
                  "px_per_s": 4, "freq_scale": "mel"},
 "excerpts": [
   {"kind": "quietest", "start_ms": 7411000, "len_ms": 20000,
    "a_url": "signed", "b_url": "signed", "blind_order_seed": 174},
   {"kind": "loudest", …}, {"kind": "max_spectral_delta", …}
 ],
 "low_confidence_zones": [{"start_ms": 12040000, "end_ms": 12100000}],
 "metrics_diff": {"bandwidth_hz": [10120, 15890], "noise_floor_db": [-52.4, -61.0], …},
 "assessment": {"verdict": "improved", "warnings": []}}
```

Auswahlregeln der drei Ausschnitte (Modulkapitel, präzisiert): `quietest` = 20-s-Fenster mit minimalem RMS **und** `speech_detected` (leise Sprache zeigt Denoise-Schäden zuerst); `loudest` = maximales RMS (Declip/Verdichtung); `max_spectral_delta` = maximale spektrale Distanz vorher/nachher oberhalb 4 kHz (dort arbeitet die Bandbreiten-Erweiterung). `blind_order_seed` bestimmt serverseitig die A/B-Zuordnung je Ausschnitt; die Auflösung liefert erst der Aufdeck-Endpunkt nach abgegebenem Höreindruck (das UI kann nicht schummeln — die Zuordnung ist client-seitig schlicht unbekannt). `low_confidence_zones` stammen aus `model_confidence.min_window`-Meldungen des Workers ([Protokoll](worker-protocol.md)).

## Settings-Referenz

| Schlüssel (`upscaler.*`) | Default | Verwendung |
|---|---|---|
| `preflight.pointless_bandwidth_ratio` | 0.88 | PF-01 |
| `preflight.quiet_noise_floor_db` | −72 | PF-02 |
| `preflight.min_duration_ms` | 10000 | PF-08 |
| `chunk_minutes` | 10 | Chunk-Plan |
| `assess.bandwidth_gain_hz` | 2000 | Delta-Urteil |
| `assess.noise_gain_db` | 6 | Delta-Urteil |
| `assess.loudness_shift_max_lu` | 1.5 | Validierungsgrenze |
| `worker_lost_timeout_minutes` | 30 | Modulkapitel Edge Case |
| `request_role` | manager | Modulkapitel Security (konfigurierbar) |

## Tests (UP-Serie; Rückverfolgung wie üblich)

* **UP-PF-01…08**: je Preflight-Regel beidseitig der Schwelle (synthetische Quellen: bandbegrenztes Rauschen, Clipping-Konstrukte, Pseudo-Stereo).
* **UP-MET-01…05**: Metrik-Berechnungen gegen synthetische Referenzen (bekannte Bandbreite/Noise-Floor/Clipping-Anteile deterministisch konstruiert); Schema-Validierung; Zeitreihen-Auslagerung.
* **UP-ASSESS-01…04**: Delta-Urteil je Task; `loudness_shift`-Validierungsfehler; `no_measurable_gain`-Warnpfad.
* **UP-AB-01…03**: Ausschnitt-Auswahlregeln (konstruiertes Audio mit bekannten Extremstellen); Blind-Seed-Mechanik (Zuordnung erst nach Aufdeckung); Zonen aus low_confidence.
* **UP-W-01…10**: Worker-Konformitäts-Suite ([Protokoll](worker-protocol.md)) gegen Referenz-Worker und PHP-Fake.
* **UP-E2E-01**: Kern-Flow des Modulkapitels als Integrationstest mit Fake-Worker (Anforderung → Preflight → Chunks → Finalize → Edition mit Badge → Processing History vollständig; Original byte-identisch).
* **Unverhandelbar** (Ehrlichkeits-Invarianten, Modulkapitel Tests): UP-GUARD-01 Badge/Tags/`is_primary`-Sperre; UP-GUARD-02 Idempotenz (Duplikat-Request ⇒ bestehender Run).
