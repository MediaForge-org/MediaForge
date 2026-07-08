# ai-worker: Protokoll-Spezifikation (`ai-job/v1`)

Vertiefung zu [modules/audio-upscaler.md](../audio-upscaler.md), Abschnitt „Worker-Protokoll". Normativ für die Kommunikation zwischen Laravel (Orchestrierung) und ai-worker (Python, Signalverarbeitung) über die `ai`-Queue. Das Protokoll ist bewusst upscaler-neutral formuliert: Die [AI Engine](../ai-engine.md) übernimmt es bei Ausarbeitung als allgemeines Worker-Protokoll (Modulkapitel-Hinweis); bis dahin ist dieses Dokument die einzige normative Quelle.

## Rollenmodell

Der Worker ist ein **dummer, zustandsloser Rechenknecht** (Modulkapitel): keine Datenbank-Verbindung, kein Katalogwissen, keine Entscheidungen. Er kennt genau vier Dinge: Eingabedateien lesen, Modell anwenden, Ausgabedatei schreiben, Ergebnis melden. Alles Fachliche (Chunk-Planung, Sinnhaftigkeit, Verbuchung) lebt in PHP. Diese Asymmetrie ist die Sicherheits- und Wartbarkeitsgrenze: Der ML-Stack (PyTorch, CUDA, native Codecs) ist die am schwersten härtbare Komponente des Systems — sie bekommt die kleinstmögliche Verantwortung.

## Transport

Redis, drei Schlüsselräume (Präfix `mediaforge:ai:`):

| Schlüssel | Typ | Zweck |
|---|---|---|
| `mediaforge:ai:jobs` | List (BRPOPLPUSH → `mediaforge:ai:working:{worker_id}`) | Job-Warteschlange; atomare Übernahme in eine Worker-Arbeitsliste |
| `mediaforge:ai:results` | List | Ergebnis-/Fehlermeldungen an PHP (Horizon-Job konsumiert) |
| `mediaforge:ai:workers:{worker_id}` | Hash mit TTL 90 s | Registrierung + Heartbeat |
| `mediaforge:ai:cancel:{run_id}` | Key mit TTL | Abbruch-Signal (Worker prüft vor jedem Chunk) |

Bewusst **kein** HTTP-Dienst: Die Queue entkoppelt Verfügbarkeit (Worker darf stundenlang fehlen, Jobs warten), erzwingt Einzelverarbeitung pro Worker (GPU-Serialität, Modulkapitel Performance) und braucht keinen zusätzlichen Netzpfad in den härtesten Container. Das `working`-Muster (per-Worker-Arbeitsliste) macht Absturz-Erkennung trivial: Ein Eintrag in `working:{id}` ohne lebenden Heartbeat ⇒ Job wird von der Reaper-Routine (PHP, Scheduler-Job minütlich) zurück auf `jobs` gelegt (`requeued_reason='worker_lost'`, zählt als Fehlversuch des Chunks).

## Worker-Registrierung und Heartbeat

Beim Start schreibt der Worker seinen Fähigkeits-Hash und erneuert ihn alle 30 s:

```json
{"worker_id": "aiw-7f3a", "protocol": "ai-job/v1",
 "started_at": "2026-07-06T18:00:00Z",
 "device": {"kind": "cuda", "name": "RTX 4070", "vram_mb": 12282,
            "driver": "570.86", "compute": "8.9"}
   | {"kind": "cpu", "threads": 16},
 "models": [{"name": "speech-bwe", "version": "2.1.0",
             "weights_hash": "blake3:9a41…", "loaded": false}],
 "current": {"run_id": "01J…", "chunk": 17, "of": 40} | null}
```

`models[]` ist das Inventar des lokalen Modell-Volumes nach Hash-Verifikation (Modulkapitel Security: abweichender `weights_hash` ⇒ Modell wird als `corrupt` gemeldet und nie geladen; der Health-Check des [Admin-Moduls](../health-monitoring.md) alarmiert). PHP aggregiert die Worker-Hashes zur Verfügbarkeits-Anzeige („Feature nicht verfügbar", Modulkapitel) und zur **Dispatch-Prüfung**: Ein Run wird nur gestartet, wenn mindestens ein lebender Worker das geforderte (Modell, Version, Hash)-Tripel inventarisiert — sonst scheitert `RequestUpscale` sofort mit `upscaler.model_unavailable` statt Jobs ins Leere zu legen.

## Job-Nachricht (PHP → Worker)

Das Modulkapitel zeigt das Grundschema; vollständige Feldliste:

| Feld | Pflicht | Inhalt |
|---|---|---|
| `schema` | ja | konstant `ai-job/v1` |
| `job_id` | ja | ULID des Chunk-Jobs (Ergebnis-Korrelation) |
| `run_id` | ja | Run-ULID (Cancel-Prüfung, Modell-Affinität) |
| `attempt` | ja | 1–3; der Worker behandelt Versuche identisch (Eskalation ist PHP-Sache) |
| `task` | ja | Task-Enum des Profils ([profiles-metrics.md](profiles-metrics.md)) |
| `model` | ja | `{name, version, weights_hash}` — der Worker lädt exakt diese Kombination; Hash-Mismatch beim Laden ⇒ Fehlerklasse `model_integrity` |
| `params` | ja | Modellparameter (Profil-Snapshot-Auszug, task-spezifisch validiert im Worker gegen das Parameter-Schema des Modells) |
| `inputs[]` | ja | Dateien + Chunk-Fenster (`start_ms`/`end_ms` in Werkzeit der Quelle; bei Mehrdatei-Quellen mehrere Einträge, deren Fenster der Worker konkateniert dekodiert — die Zerlegung entlang der Track-Sequenz hat PHP im Chunk-Plan erledigt) |
| `output` | ja | Zielpfad (`.partial`) + Format (`wav_f32`/`wav_pcm16`) |
| `checkpoint_key` | ja | Affinitäts-Hinweis: gleiche Keys nacheinander ⇒ Modell im Speicher halten (Modulkapitel Performance) |
| `limits` | ja | `{max_wall_s, max_vram_mb}` — Selbstbegrenzung; Überschreitung ⇒ kontrollierter Abbruch statt OOM-Kill |

Der Worker validiert die Nachricht gegen das mitinstallierte JSON-Schema; Schema-Verletzungen gehen als `protocol_error` in die Results (nie stilles Verwerfen — ein Protokoll-Drift zwischen PHP- und Worker-Version muss sofort auffallen). Versionierung: Worker akzeptieren genau eine `schema`-Version; Upgrades deployen Worker und PHP-Seite zusammen (Compose-Profil, [deployment.md](../../architecture/deployment.md)) — das Protokoll hat bewusst keine Aushandlung (ein Betreiber, ein Deploy, keine Mischversionen).

## Fortschritts- und Ergebnis-Nachrichten (Worker → PHP)

Drei Nachrichtentypen auf `results`:

```json
{"type": "progress", "job_id": "01J…", "pct": 40,
 "detail": {"phase": "inference", "rtf": 0.31}}

{"type": "result", "job_id": "01J…", "status": "ok",
 "output": {"path": "…chunk-003.wav.partial", "duration_ms": 600512,
            "content_hash": "blake3:…"},
 "stats": {"wall_s": 187.4, "device": "cuda", "peak_vram_mb": 8412,
           "model_load_s": 0.0, "rtf": 0.31},
 "model_confidence": {"mean": 0.87, "min_window": 0.41} }

{"type": "result", "job_id": "01J…", "status": "error",
 "error": {"class": "oom", "detail": "CUDA out of memory …",
           "retryable_hint": true}}
```

`progress` ist best-effort (mind. alle 30 s während Inferenz; PHP speist `job_progress` fürs UI). `result.ok` trägt den Content-Hash des Chunk-Ergebnisses — der Finalize verifiziert ihn vor dem Concat (die `chunk_hash_mismatch`-Mechanik der [Assembler-Builder](../audiobook-assembler/artifact-builders.md) gilt baugleich). `model_confidence` ist optional (modellabhängig) und wandert in `worker_info` des Runs; ein `min_window < 0.3` erzeugt die Ergebnis-Warnung `low_confidence_passages` mit Zeitfenster-Verweis (im A/B-UI als markierte Zone sichtbar — genau dort sollte der Mensch hinhören).

**Fehlerklassen-Taxonomie** (normativ; steuert die PHP-Reaktion):

| `error.class` | Bedeutung | Reaktion |
|---|---|---|
| `oom` | GPU/RAM erschöpft | retry (bis 3); beim 2. Versuch halbiert PHP das Chunk-Fenster (Re-Plan der Restchunks — der dokumentierte adaptive Pfad) |
| `model_integrity` | Gewichte-Hash falsch | Run `failed` sofort; Health-Alarm (kein Retry — der Zustand heilt sich nicht) |
| `model_load` | Laden scheiterte (VRAM, Format) | retry auf anderem Worker, falls vorhanden; sonst `failed` |
| `decode_error` | Eingabedatei unlesbar/korrupt | Run `failed` (Fachfehler; Quelle prüfen) |
| `inference_error` | Modell-Laufzeitfehler | retry; 3× ⇒ `failed` |
| `io_error` | Mount weg, ENOSPC | retry mit Backoff (transient) |
| `cancelled` | Cancel-Key gesehen | kein Retry; Run-Abbruch läuft |
| `protocol_error` | Schema-Verletzung | Run `failed`; Deploy-Inkonsistenz-Alarm |
| `timeout` | `max_wall_s` überschritten | wie oom behandelt (Fenster-Halbierung) |

## Chunk-Plan (PHP-intern, Vertragsteil wegen Wiederaufnahme)

Der Preflight persistiert den Plan als Job-Checkpoint-Payload (`upscale:{run}:plan`):

```json
{"chunk_ms": 600000, "overlap_ms": 500, "chunks": [
  {"index": 1, "start_ms": 0, "end_ms": 600500, "inputs": [{"file_id": "01J…", "from_ms": 0, "to_ms": 600500}]},
  {"index": 2, "start_ms": 600000, "end_ms": 1200500, "inputs": […]}
]}
```

Fenster überlappen um `overlap_ms` beidseitig der Naht (Chunk n endet 500 ms nach der nominellen Grenze, Chunk n+1 beginnt 500 ms davor); der Finalize-Crossfade (equal-power, über die volle Überlappung) arbeitet ausschließlich auf diesen deklarierten Zonen. Re-Planung (OOM-Halbierung) ersetzt nur **unverarbeitete** Chunks — fertige Chunk-Ergebnisse bleiben gültig, weil Nahtzonen im Plan explizit sind und der neue Plan die alten Grenzen respektiert (Halbierung teilt innerhalb bestehender Fenster). Diese Eigenschaft ist testverankert (Wiederaufnahme-Test des Modulkapitels): Kein Re-Plan invalidiert bezahlte GPU-Arbeit.

## Abbruch

`CancelUpscaleRun` setzt den Cancel-Key (TTL 48 h), purgt wartende Chunk-Jobs des Runs aus `jobs` (Lua-Skript, atomar über `run_id`-Match) und lässt den laufenden Chunk austrudeln (Worker prüft den Key zwischen Chunks und an Phasen-Grenzen innerhalb der Inferenz, wo das Modell es erlaubt — spätestens nach dem laufenden Chunk terminiert der Run). Teilergebnisse räumt der Housekeeping-Pfad (Modulkapitel Performance).

## Worker-Implementierungsvertrag

Für alternative Worker-Implementierungen (und die Test-Fakes) die zehnteilige Konformitätsliste — Pendant zur Player-Konformität der Disc-Engine:

1. Atomare Job-Übernahme via BRPOPLPUSH; nie zwei Jobs parallel.
2. Heartbeat ≤ 30 s mit korrektem `current`.
3. Modell-Hash-Verifikation vor jedem Laden; `corrupt`-Inventarisierung.
4. Schema-Validierung eingehender Jobs; `protocol_error` statt Ratenlassen.
5. Ausgabe ausschließlich auf den deklarierten `.partial`-Pfad; Content-Hash im Result.
6. Sample-exakte Fensterung (`start_ms`/`end_ms` ± 1 Sample bei gegebener Rate).
7. `progress` mindestens alle 30 s Inferenzzeit.
8. Fehlerklassen-Treue (kein Alles-ist-`inference_error`).
9. Cancel-Key-Prüfung mindestens an Chunk-Grenzen.
10. Selbstlimits (`max_wall_s`, `max_vram_mb`) mit kontrolliertem Abbruch.

Die Konformitäts-Suite (UP-W-Serie, [profiles-metrics.md](profiles-metrics.md), Test-Abschnitt) läuft gegen jede Worker-Version in deren CI; der Referenz-Worker (Python, ausgeliefertes Image) und der PHP-Test-Fake bestehen sie beide — der Fake ist damit beweisbar protokolltreu, und Integrationstests der PHP-Seite testen echtes Verhalten.

## Sicherheits-Randbedingungen (Zusammenfassung mit Protokollbezug)

Der Worker-Container: kein Netz-Egress (Modelle offline, Modulkapitel), Redis als einzige erlaubte Verbindung (Compose-internes Netz, eigenes Redis-ACL-Profil: nur die `mediaforge:ai:*`-Schlüssel, kein `KEYS`, kein `FLUSHALL`), Medien read-only, Schreibrecht nur `/artifacts/audio-upscaler`. Pfade in Job-Nachrichten werden PHP-seitig gegen eine Allowlist der Bibliotheks- und Artefakt-Wurzeln geprüft **und** worker-seitig erneut (Defense in Depth: ein kompromittiertes PHP soll den Worker nicht `/etc/shadow` lesen lassen können — der Worker weigert sich außerhalb seiner Mounts ohnehin, aber die explizite Prüfung macht den Verstoß beobachtbar als `protocol_error`).
