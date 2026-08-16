# Adult Full-Scene Analysis, Event Timeline und Taxonomie

Status: langfristige Spezifikation
Priorität: **P0 Datenmodell**, P1/P2 Analysemodelle je nach Reife
Referenzscreens: `33_adult_scene_full_analysis_timeline.png`, `34_adult_tag_taxonomy_event_inspector.png`, `40_feature_overview_p0_p2.png`, `41_feature_matrix_detailed.png`

## 1. Ziel

MediaForge soll eine lokale Adult-Scene vollständig analysieren können. „Analysiert“ bedeutet nicht, dass einige zufällige Screenshots geprüft wurden, sondern dass Video und Audio über ihre vollständige Laufzeit verarbeitet wurden und der Analyseumfang messbar gespeichert ist.

Ergebnis:

- grobe Scene-/Setting-Segmente;
- konkrete visuelle Events;
- Audio Events;
- Speech/Language optional;
- genaue Start-/Endzeiten;
- hierarchische Tags + Attribute;
- Evidence;
- Confidence;
- Verifikationsstatus;
- Modell-/Analyseversion.

## 2. Coverage vs. Confidence

Diese Begriffe dürfen nicht vermischt werden.

```text
analysis_coverage = 100%
```

bedeutet: Die komplette Datei wurde nach der definierten Pipeline verarbeitet.

```text
confidence = 0.94
```

bedeutet: Ein konkreter Detector schätzt einen Event mit 94 % Wahrscheinlichkeit ein.

100 % Coverage bedeutet **nicht** 100 % semantische Wahrheit.

## 3. Video-Pipeline

### Pass A – Full Decode

Jeder Frame wird decodiert und mit PTS/Frame-Position verfolgt. Dadurch werden keine Zeitbereiche blind ausgelassen.

### Pass B – Lightweight Full Coverage

Jeder Frame/kleine Temporal Window erhält günstige Features:

- Scene/Shot Change;
- Bewegung;
- Personen/Objekte;
- Embeddings;
- grobe Klassifikation;
- Kandidatenflags.

### Pass C – Temporal Detection

Aktionen werden aus Sequenzen statt isolierten Einzelbildern erkannt. Das ist für kurzzeitige oder bewegungsabhängige Events wichtiger als ein einzelner Screenshot.

### Pass D – Dense Refinement

Kandidatenbereiche werden dichter/höher aufgelöst geprüft, bei kritischen Event-Klassen bis Frame-by-Frame, um Start-/Endgrenzen nachzuschärfen.

### Pass E – Multimodal Fusion

Video-, Audio- und gegebenenfalls Speech-Evidence können kombiniert werden. Ein Audioereignis darf einen visuellen Tag nicht allein „beweisen“, erhöht aber bei passender Korrelation die Evidenz.

## 4. Audio-Pipeline

Die komplette Audiospur wird zu PCM decodiert und mit stark überlappenden Fenstern analysiert.

Beispielhafte Audio-Events:

- `crying`;
- `screaming`;
- `laughing`;
- `whispering`;
- `talking`;
- `music`;
- `silence`;
- `ambient`;
- weitere fachlich definierte Event-Klassen.

Jeder Event speichert Start/End, Confidence, Detector, Model Version und Evidence.

## 5. Einheitliches Event-Modell

```text
scene_events
├── id
├── scene_id
├── base_tag_id
├── modality                # visual/audio/speech/multimodal/manual
├── start_ms
├── end_ms
├── confidence nullable
├── verification_state
├── detector
├── model_version
├── analysis_run_id
├── evidence_bundle_id nullable
└── created_at
```

## 6. Hierarchische Taxonomie

Keine flache Liste mit tausenden zusammengesetzten Strings.

Beispiel:

```text
Event
└── puke
    ├── consistency
    │   ├── watery
    │   ├── thick
    │   └── chunky
    ├── appearance
    │   ├── milky
    │   ├── clear
    │   └── colored
    └── amount
        ├── small
        ├── medium
        └── large
```

Ein Event kann dann sein:

```text
base: puke
start: 24:04.120
end: 24:19.680
attributes:
  consistency = chunky
  appearance = milky
  amount = medium
```

Das UI darf daraus „Milky, chunky puke“ rendern, aber die Datenbank bleibt strukturiert.

## 7. Taxonomie-Metadaten

Jeder Base Tag/Attribute Value besitzt:

- canonical name;
- display name;
- aliases/synonyms;
- parent/group;
- description;
- detector profile;
- sensitivity/privacy class;
- incompatible attributes;
- mapping zu StashDB/TPDB/anderen Taxonomien;
- version introduced/deprecated.

## 8. Negative Tags / checked_absent

Für verlässliche Suche ist es wichtig zu wissen, ob etwas nur „nicht getaggt“ oder wirklich geprüft und nicht gefunden wurde.

```text
scene_tag_checks
├── scene_id
├── tag_id
├── analysis_run_id
├── checked_coverage
├── result = present|absent|uncertain
└── confidence
```

Damit kann MediaForge später Filter wie „voll analysiert und kein crying“ korrekt unterscheiden von „nie geprüft“.

## 9. Evidence Bundles

Ein AI-Event ist erklärbar:

```text
EvidenceBundle
├── representative_frames[]
├── before_after_frames[]
├── audio_excerpt nullable
├── waveform/spectrogram ref nullable
├── detector outputs
└── model versions
```

User Actions:

- Confirm;
- Correct attributes;
- Reject;
- Add Note;
- Create Training Example.

## 10. Verification States

Empfohlene Zustände:

```text
ai_detected
ai_high_confidence
cross_modal_confirmed
manual_verified
manual_rejected
needs_review
```

Ein Prozentwert allein ist keine fachliche Wahrheit.

## 11. Active Learning

Manuelle Korrekturen können optional als lokaler Training-/Evaluation-Datensatz gespeichert werden. Training ist opt-in und darf nicht heimlich Nutzerdaten hochladen.

Ziel:

- persönliche Bibliothek wird über Zeit genauer;
- Fehlerklassen werden messbar;
- neue Modelle können gegen alte verifizierte Beispiele evaluiert werden.

## 12. Inkrementelle Analyse

Zwischenergebnisse werden versioniert gespeichert:

- Scene Boundaries;
- Embeddings;
- Audio Features;
- Speech Transcript;
- Detector-specific output.

Wenn nur ein neuer Tag Detector hinzukommt, soll nicht zwingend jedes 4K-Video komplett von Null verarbeitet werden.

## 13. Analysebericht

Jede Datei erhält einen reproduzierbaren Report:

```text
Video decoded              100%
Audio decoded              100%
Temporal analysis          100%
Critical event refinement  100%
Audio event analysis       100%
Speech analysis            optional/100%
Model set                  2026.08
Analysis run               <id>
```

## 14. Timeline UI

Unter dem Player:

```text
Visual
POV       ━━━━━━━      ━━━━━
Puke                 ●━━━━●

Audio
Crying       ━━━━━
Screaming             ●━●
Music     ━━━━━━━━━━━━━━━━━
```

Jeder Marker ist anklickbar und seeked auf `start_ms`.

## 15. Suche

Beispiele:

```text
visual:puke
visual:puke attribute:consistency=watery
audio:crying
visual:puke AND audio:crying
```

Suchergebnisse dürfen direkt die konkreten Timestamp-Treffer zeigen.

## 16. Smart Collections

Jede komplexe Suche kann als dynamische Collection gespeichert werden. Neue analysierte Scenes werden automatisch ergänzt, wenn sie die Regel erfüllen.

## 17. Privacy

Analyseergebnisse, Evidence-Thumbnails, Tags und Event-Namen unterliegen vollständig dem Adult Lock. Keine generischen Cache-Keys, Notifications oder Analytics dürfen sie im gesperrten Modus leaken.

## 18. Performance

„Maximale Genauigkeit“ bedeutet nicht, dass jeder 4K-Frame immer durch jedes große Modell läuft. Die Architektur darf adaptive/dichte Analyse nutzen, solange Coverage vollständig bleibt und kritische Bereiche nicht ausgelassen werden.

Hardwareprofile:

- Fast;
- Balanced;
- Exact.

`Exact` priorisiert Trefferquote und Boundary Precision, kann aber erheblich länger laufen.

## 19. Performer Tattoo Coverage und 3D

Detaillierte Tattooanalyse wird für weibliche Adult-Performer priorisiert. Primäre Kennzahl ist flächenbasierte Gesamt-Coverage über die **vollständige Körperoberfläche**, nicht Tattoo Count und nicht nur sichtbare Haut. Body Regions verwenden die versionierte Anatomy Ontology. Multi-Scene Evidence kann optional ein individuelles 3D-Mesh erzeugen und Tattoos auf dessen Oberfläche projizieren.

Siehe `adult-3d-reconstruction-and-tattoo-coverage.md`.

## 20. Optionalität

Full Analysis, 3D Reconstruction, Tattoo Projection und große lokale AI-Modelle sind opt-in Capabilities. Ohne sie bleibt Adult Catalog/Playback/Metadata vollständig funktionsfähig.
