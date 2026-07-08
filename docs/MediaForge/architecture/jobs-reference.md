# Job-Gesamtreferenz

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Vertiefung zu [architecture/overview.md](overview.md), Abschnitt „Job-Konventionen". Konsolidierte Sicht auf **jeden** Job des Systems: Queue-Zuordnung, Typ (Job/ResumableJob/Batch), Trigger, Idempotenz-Technik und Timeout/Retry-Profil. Modulkapitel definieren ihre Jobs normativ (Schritte, Fachsemantik); dieser Katalog beantwortet die Querschnittsfragen, die nur über alle Jobs hinweg sichtbar werden: Ist die Queue-Verteilung ausbalanciert? Welche Jobs konkurrieren um dieselbe Ressource (GPU, NAS-I/O, Rate-Limits)? Wo fehlt eine Idempotenz-Technik? Der Katalog ist außerdem die Prüfliste des Architektur-Tests „jeder Job ist hier gelistet" (Analogon zum Endpunkt-Contract-Test).

## Queue-Übersicht und Ressourcenprofil

Aus [architecture/overview.md](overview.md) übernommen, hier mit Belegungs-Charakteristik:

| Queue | Worker-Container | Engpass-Ressource | Parallelität |
|---|---|---|---|
| `default` | worker-default | CPU niedrig | hoch (mehrere Worker-Prozesse) |
| `connector` | worker-default | externe Rate-Limits | pro Ziel-Limiter gedrosselt |
| `scan` | worker-scan | NAS-I/O | bibliotheksweise seriell (Lock) |
| `assemble` | worker-scan | NAS-I/O + CPU (Encoding) | Setting-begrenzt (Default 2) |
| `analyze` | worker-analyze | CPU mittel, gelegentlich NAS-I/O | mittel |
| `ai` | ai-worker | GPU/CPU exklusiv | **streng seriell pro Worker** |

Die `ai`-Queue ist die einzige mit harter Serialitätsgarantie (Modellspeicher-Bindung, [Audio-Upscaler](../modules/audio-upscaler/worker-protocol.md)); alle anderen skalieren horizontal über zusätzliche Worker-Replicas ([deployment.md](deployment.md)).

## Vollständiges Job-Inventar

Gruppiert nach Eigentümer-Modul. **Typ**: J = einfacher Job, RJ = ResumableJob (Checkpoint-Vertrag), B = Batch-Orchestrierung. **Idempotenz-Technik** referenziert die Kategorien aus [overview.md](overview.md) (Natürlicher-Schlüssel-Upsert, Signatur-Prüfung, Unique-Job, Outbox).

### Fundament / Scan-Pipeline

| Job | Typ | Queue | Trigger | Idempotenz |
|---|---|---|---|---|
| `ScanLibraryJob` | RJ | `scan` | Scheduler (`scan_interval_min`) / manuell | Diff gegen `files` über (size, mtime, inode); Unique-Job je Bibliothek |
| `ScanPathJob` | J | `scan` | von `ScanLibraryJob` je Batch (1000 Pfade) dispatcht | Natürlicher Schlüssel `(library_id, path)`-Upsert |
| `ComputeFingerprintsJob` | RJ | `analyze` | Event `FileFingerprinted`-Vorstufe, gestaffelt quick→content→stream | Signatur-Prüfung je Stufe (Hash bereits vorhanden ⇒ Stufe übersprungen) |
| `DetectDuplicatesJob` | J | `default` | Event `FileFingerprinted` | Gruppen-Upsert über Fingerprint-Cluster-Schlüssel |

### Disc-Engine ([Modulkapitel](../modules/disc-engine.md), [Test-Katalog](../modules/disc-engine/test-catalog.md))

| Job | Typ | Queue | Trigger | Idempotenz |
|---|---|---|---|---|
| `AnalyzeDiscImageJob` | RJ | `analyze` | Classifier-Kandidatentyp `disc_image` | Checkpoint-Schritte `fetch-analysis/persist-structure/signature/classify/map`; Upsert über `(disc_image_id, playlist_ref)` |
| `ReclassifyDiscJob` | J | `analyze` | Classifier-Versionswechsel / Set-Bestätigung / manuell | läuft auf gespeicherter Struktur, keine Neuanalyse; Heuristik-Ergebnisse ersetzbar |
| `TranslatePlaybackEventsJob` | J | `default` | Session-Update-Trigger + periodischer Sweep | `processed_at`-Markierung; absolute Standmeldung an `RecordPlaybackProgress` macht Doppelverarbeitung folgenlos |
| `ReprocessPlaylistPlaybackJob` | J | `default` | `ConfirmDiscEpisodeMapping` | dieselbe Idempotenz wie oben (identischer Pfad, `processed_at` zurückgesetzt) |
| `SweepDiscSessionsJob` | J | Scheduler (alle 15 min) | Zeitplan | Zustandsübergänge nur bei erfüllten Zeitbedingungen (`stale`/`discarded`), wiederholbar ohne Doppelwirkung |

### Hörbuch-Assembler ([Modulkapitel](../modules/audiobook-assembler.md), [API/UI/Tests](../modules/audiobook-assembler/api-ui-tests.md))

| Job | Typ | Queue | Trigger | Idempotenz |
|---|---|---|---|---|
| `SequenceAudiobookJob` | RJ | `analyze` | Classifier-Kandidatentyp `audiobook_folder` / manuell | Checkpoint-Schritte `collect-evidence/build-candidates/score/timeline` |
| `CollectChapterSourcesJob` | J | `analyze` | nach `sequenced` / manuell | Upsert über `origin_detail`-Hash (keine Duplikate bei Re-Lauf) |
| `AlignChapterSetJob` | J | `analyze` | neues/geändertes Chapter Set | pure Funktion, deterministisch — Wiederholung liefert identisches Ergebnis |
| `BuildM4bJob`, `BuildCueJob`, `BuildAbsExportJob` | RJ (M4B)/J | `assemble` | manuell / Rule-Engine-Regel | `input_signature`-Gate (Fundament-Artefaktmodell); M4B zusätzlich Chunk-Checkpoints |
| `DownloadAssetJob` | J | `connector` | Enrichment-Asset-Kandidat neu | Content-Hash-Dedup vor Schreibzugriff |

### Audio-Upscaler ([Modulkapitel](../modules/audio-upscaler.md), [Worker-Protokoll](../modules/audio-upscaler/worker-protocol.md))

| Job | Typ | Queue | Trigger | Idempotenz |
|---|---|---|---|---|
| `PreflightUpscaleJob` | RJ | `analyze` | `RequestUpscale`-Action | Duplikat-Unique-Index (`source_signature, profile_id`) vor Dispatch geprüft |
| `DispatchUpscaleChunksJob` | J | `default` | nach Preflight | Chunk-Plan ist deklarativ persistiert; Re-Dispatch ersetzt nur unverarbeitete Chunks |
| (Chunk-Verarbeitung) | — | `ai` (Worker-Protokoll, kein Laravel-Job) | von Dispatch | Content-Hash je Chunk-Ergebnis |
| `FinalizeUpscaleJob` | RJ | `assemble` | alle Chunks vollständig | `input_signature`-Gate wie Assembler-Builder |

### Enrichment ([Modulkapitel](../modules/enrichment.md), [Provider-Referenz](../modules/enrichment/provider-reference.md))

| Job | Typ | Queue | Trigger | Idempotenz |
|---|---|---|---|---|
| `EnrichEntityJob` | RJ | `connector` | Matcher-Bestätigung (`post_match`) / Provider-Ergänzung | Checkpoint-Schritte `fetch/merge/apply/assets/relations`; Payload-Hash-Vergleich vor Merge-Lauf |
| `ScheduledRefreshJob` | J | Scheduler | Alters-/Prioritäts-Policy | wählt Kandidaten, dispatcht `EnrichEntityJob` je Entität (dessen Idempotenz greift) |

### Workflow / Rule / Search / Knowledge Graph / Data Quality / Dedup

| Job | Typ | Queue | Trigger | Idempotenz |
|---|---|---|---|---|
| `EvaluateRuleForSubjectJob` | J | `default` | Event-Pfad der Rule Engine | Cooldown-Prüfung vor Aktionsausführung ([rule-engine](../modules/rule-engine.md)) |
| `RunScheduledRuleJob` | RJ | `default` | Cron-Zeitplan einer Regel | Chunk-Checkpoints über kompilierte Treffermenge |
| `EmbedSubjectJob` | J | `ai-light` (gebatcht) | `MediaItemCreated/Updated`, debounced | Upsert über `media_item_id`-Embedding-Zeile |
| `ReembedAllJob` | B | `ai-light` | Modell-/Textversionswechsel | Batch mit Fortschritts-Tracking; einzelne Subjekt-Jobs idempotent wie oben |
| `ImportProviderRelationsJob` | J | `connector` | Enrichment-Abschluss | Upsert über den Relations-Unique-Key ([knowledge-graph](../modules/knowledge-graph.md)) |
| `SuggestCrossMediaRelationsJob` | J | `default` | wöchentlich (Scheduler) | Vorschlags-Upsert, keine Duplikate über Kandidatenpaar |
| `EvaluateSubjectQualityJob` | J | `default` (debounced 60 s) | Fundament-Events (`MediaItemUpdated`, `DiscMappingConfirmed`, `ChapterSetActivated`, …) | Score-Upsert über `subject_type, subject_id` |
| `RunQualitySweepJob` | RJ | `default` | wöchentlich (Scheduler) | Chunk-Checkpoints; Waisen-Checks sind reine Lesungen mit Review-Erzeugung über den Duplikat-Schutz von `review_tasks` |

### Connectoren, Betrieb, Health, Backup

| Job | Typ | Queue | Trigger | Idempotenz |
|---|---|---|---|---|
| `RunConnectorIngestJob` / `RunConnectorEgressJob` | RJ | `connector` | Sync-Intervall der Instanz / Webhook | `ShouldBeUnique` über Sync-Ziel; Outbox-Protokoll für Egress ([connector-sdk](../connectors/connector-sdk.md)) |
| `SyncJellyfinWatchStatesJob` | RJ | `connector` | konkrete Instanz-Ausprägung von Ingest/Egress | wie oben, Connector-spezifisch |
| `ConnectorHealthCheckJob` | J | Scheduler | periodisch je Instanz | reine Lesung, keine Schreibwirkung |
| `LaunchOnKodiJob` | J | `connector` | „Im Player öffnen"-Aktion | signierte Kurzzeit-Referenz, einmalig gültig |
| `RunHealthChecksJob` | J | Scheduler (minütlich) | Zeitplan | jeder Check ist idempotente Lesung; Zustandsmaschine über Vergleich zum Vorzustand |
| `DeadManPingJob` | J | Scheduler | Zeitplan | externer Anker, keine lokale Zustandsänderung |
| `RunBackupJob` | RJ | Scheduler → `default` | Backup-Zeitplan / manuell | Checkpoint-Schritte `quiesce/dump/config/inventory/manifest/unquiesce/rotate` |
| `RestoreProbeJob` | J | Scheduler (monatlich) | Zeitplan | Wegwerf-Restore in isoliertem Ziel, keine Produktionswirkung |
| `PruneBackupsJob` | J | Scheduler | nach `RunBackupJob` | Rotationsschema deterministisch; hartes Verbot, das letzte erfolgreiche Backup zu löschen |
| `PruneStaleWorkersJob` | J | Scheduler | periodisch | Heartbeat-TTL-Vergleich (ai-worker-Reaper, [Worker-Protokoll](../modules/audio-upscaler/worker-protocol.md)) |
| `MaintainPartitionsJob` | J | Scheduler (monatlich) | Zeitplan | prüft Existenz vor Anlage; `DETACH`/`DROP` nur auf abgelaufene Partitionen ([schema-reference](../database/schema-reference.md)) |

## Job × Ressourcen-Konfliktmatrix

Welche Jobs teilen sich einen Engpass — Betriebsrelevanz für Kapazitätsplanung ([deployment.md](deployment.md)):

| Ressource | Konkurrierende Jobs | Entschärfung |
|---|---|---|
| NAS-I/O | `ScanPathJob`, `AnalyzeDiscImageJob` (Struktur-Lesen), `ComputeFingerprintsJob` (content-Stufe), `BuildM4bJob` (Concat/Transcode-Quelllesen) | getrennte Queues (`scan`/`analyze`/`assemble`) mit unabhängiger Worker-Skalierung; Bibliotheks-Lock verhindert Doppel-Scan derselben Wurzel |
| GPU (ai-worker) | Upscale-Chunks, künftige AI-Engine-Inferenzen (Embeddings großer Modelle, falls GPU-gestützt) | strikte Serialität pro Worker; `ai-light` (CPU-Embeddings) ist bewusst eine **eigene** Queue, um leichte Embedding-Jobs nicht hinter Stunden-Upscales zu stauen |
| Externe Rate-Limits | alle `connector`-Jobs, Enrichment-Refresh, `ImportProviderRelationsJob` | pro-Ziel-Limiter (Redis Token-Bucket), nicht global — ein langsamer Provider drosselt nicht die anderen |
| Datenbank-Schreiblast | `RunQualitySweepJob`, `ReembedAllJob`, `MaintainPartitionsJob` (Wartung) | ausschließlich Nachtfenster-Zeitplan (Betriebsprofil, [deployment.md](deployment.md)); Chunk-Größen halten Einzeltransaktionen klein |

## Timeout- und Retry-Profile (Ergänzung zur Fundament-Tabelle)

Über die drei Klassen aus [overview.md](overview.md) (Connector 5 Tries/5 min, Analyse 3 Tries/30 min, AI 2 Tries/konfigurierbar) hinaus die Werte der übrigen Klassen:

| Klasse | Timeout | Tries | Backoff |
|---|---|---|---|
| Scan (`ScanLibraryJob`, `ScanPathJob`) | 60 min (Gesamtjob) / 2 min je Pfad-Batch | 3 | `[30,120,600]` |
| Assemble (Build-Jobs) | 240 min (M4B), 10 min (CUE/Metadaten) | 2 | `[60,300]` |
| Default (Fachlogik-Jobs ohne externen I/O) | 5 min | 3 | `[15,60,300]` |
| Health/Scheduler-Jobs | 30 s (je Einzel-Check) | kein Retry (nächster Zyklus ersetzt) | — |

## Prüfliste für neue Jobs (PR-Checkliste)

Queue aus der Enum, nie String-Literal · Idempotenz-Technik benannt und getestet (`assertJobIsIdempotent`-Harness, [overview.md](overview.md)) · Timeout/Tries/Backoff explizit gesetzt · bei Langlauf: `ResumableJob` mit benanntem `checkpointKey()` · Zeile in diesem Katalog (Queue, Trigger, Idempotenz) · Ressourcen-Konfliktmatrix erweitert, falls neue Engpass-Teilnahme · Fortschritts-Reporting über `job_progress`, falls Laufzeit > 10 s typisch.
