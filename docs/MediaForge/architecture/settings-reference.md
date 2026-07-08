# Settings-Gesamtreferenz

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Vertiefung zu [architecture/overview.md](overview.md), Abschnitt „Konfiguration". Konsolidierte Sicht auf **jeden** Datenbank-gestützten Settings-Schlüssel des Systems (die dritte Konfigurationsebene: „Werte, die Admins im laufenden Betrieb ändern können sollen", [architecture/overview.md](overview.md)). Modulkapitel definieren ihre Settings normativ mit Begründung; dieser Katalog beantwortet die Querschnittsfrage: Welche Stellschrauben hat die Instanz insgesamt, wie heißen sie einheitlich, und welche Werte hängen zusammen (ein Betreiber, der `disc_engine.auto_confirm_mappings=false` setzt, sollte im selben Atemzug sehen, dass es kein Äquivalent für den Assembler gibt und warum).

## Settings-Mechanik (Wiederholung mit Vertrag)

Jedes Modul definiert eine typisierte Settings-Klasse mit Defaults (`App\Modules\<Modul>\<Modul>Settings`); die `settings`-Tabelle ([core-schema.md](../database/core-schema.md)) hält **nur Abweichungen** vom Klassen-Default. Schlüssel folgen durchgängig `{modul_namespace}.{bereich}.{name}` (zwei- oder dreistufig, siehe Katalog); ein Schlüssel ohne Namespace-Präfix ist ein Review-Defekt (Ausnahme: Workflow-Definitionen lesen zusätzlich freie `Ctx::setting()`-Parameter aus dem Start-Kontext, die keine globalen Settings sind, sondern Instanzparameter — im Katalog gesondert markiert). Jede Änderung läuft über `UpdateSetting` (auditiert, [core-schema.md](../database/core-schema.md)).

## Vollständiger Settings-Katalog

### `disc_engine.*`

| Schlüssel | Default | Bedeutung | Kapitel |
|---|---|---|---|
| `disc_engine.classification_confidence_threshold` | 0.80 | Review-Auslösung unterhalb dieser Gesamtkonfidenz (K-02) | [Regelkatalog](../modules/disc-engine/classification-rules.md) |
| `disc_engine.auto_confirm_mappings` | true | globaler Schalter für Auto-Confirm hochkonfidenter Mappings | [Mapping-Algorithmus](../modules/disc-engine/mapping-algorithm.md) |
| `disc_engine.inherit_confirmed_mappings` | true | Signatur-Treffer erben Mappings direkt als `confirmed` statt `suggested` | Mapping-Algorithmus |
| `disc_engine.retro_credit_days` | 90 | Zeithorizont der Nachverrechnung nach später Mapping-Bestätigung | [Playback-Übersetzung](../modules/disc-engine/playback-translation.md) |
| `disc_engine.session_stale_minutes` | 30 | Sweeper: `active`→`stale` ohne Event | Playback-Übersetzung |
| `disc_engine.session_discard_hours` | 24 | Sweeper: `stale`→`discarded` ohne verwertbare Events | Playback-Übersetzung |
| `disc_engine.manual_ack_expiry_days` | 14 | Verfallsfrist der `open_close_only`-Bestätigungskarte | Playback-Übersetzung |
| `disc_engine.min_span_ms` | 5000 | Mindestlänge einer Wiedergabespanne (Zapping-Filter) | Playback-Übersetzung |
| `disc_engine.mapper.*` (12 Schlüssel: `runtime_tolerance`, `weight_runtime/order/setpos/anatomy`, `gap_playlist/episode`, `mono_bonus`, `inversion_penalty`, `ambiguity_delta/malus`, `complete_monotone_boost`, `segment_snap_window_ms`) | s. Kapitel | DP-Alignment-Gewichte und -Toleranzen | Mapping-Algorithmus |
| `disc_engine.rules.*` (12 Schlüssel: `anatomy_tolerance`, `near_dup_item_ms/total_ms`, `menu_loop_max_ms`, `episode_window_min/max_ms`, `min_group_size`, `group_cv_max`, `main_feature_min_ms`, `playall_duration_tol`, `obfuscation_fold_ratio/unclassified_max`) | s. Kapitel | Klassifikationskaskaden-Parameter | Regelkatalog |

### `assembler.*`

| Schlüssel | Default | Bedeutung | Kapitel |
|---|---|---|---|
| `assembler.sequencer.*` (10 Schlüssel: `pattern_coverage_min`, `auto_threshold`, `review_threshold`, `duration_outlier_low/high`, `decode_divergence`, `weight_tags/filename/folders/natural`) | s. Kapitel | Sequenzierungs-Konsens und -Schwellen | [Sequenzierungs-Katalog](../modules/audiobook-assembler/sequencing-rules.md) |
| `assembler.aligner.*` (11 Schlüssel: `track_snap_ms`, `silence_snap_window_ms`, `min_silence_ms`, `min_chapter_ms`, `scale_trigger/max`, `shift_search_window_ms`, `start_pull_ms`, `end_stretch_ms`, `conflict_boundary_delta_ms/ratio`) | s. Kapitel | Kapitel-Alignment-Toleranzen | [Alignment-Algorithmus](../modules/audiobook-assembler/alignment-algorithm.md) |
| `assembler.sources.official_endpoint` | — | Basis-URL des Audnexus-kompatiblen Kapitel-Providers | [Kapitelquellen-Formatreferenz](../modules/audiobook-assembler/chapter-source-formats.md) |
| `assembler.builders.cue_bom` | false | UTF-8-BOM in CUE-Artefakten (Legacy-Windows-Player) | [Artefakt-Builder](../modules/audiobook-assembler/artifact-builders.md) |
| `assembler.builders.cue_ascii_fallback` | false | Transliteration nicht-ASCII-Kapiteltitel für Alt-Hardware | Artefakt-Builder |
| `assembler.builders.m4b_chunk_hours` | 2 | Ziel-Chunkgröße der M4B-Transcodierung | Artefakt-Builder |
| `assembler.auto_rebuild` | false | Opt-in: automatischer M4B-Rebuild nach Kapitel-Aktivierung (Rule-Engine-Beispiel) | Artefakt-Builder |

### `upscaler.*`

| Schlüssel | Default | Bedeutung | Kapitel |
|---|---|---|---|
| `upscaler.preflight.pointless_bandwidth_ratio` | 0.88 | PF-01-Ablehnungsschwelle | [Profile-Referenz](../modules/audio-upscaler/profiles-metrics.md) |
| `upscaler.preflight.quiet_noise_floor_db` | −72 | PF-02-Ablehnungsschwelle | Profile-Referenz |
| `upscaler.preflight.min_duration_ms` | 10000 | PF-08-Mindestlänge | Profile-Referenz |
| `upscaler.chunk_minutes` | 10 | Chunk-Plan-Fenstergröße | Profile-Referenz |
| `upscaler.assess.bandwidth_gain_hz` | 2000 | Erfolgsschwelle Bandbreiten-Erweiterung | Profile-Referenz |
| `upscaler.assess.noise_gain_db` | 6 | Erfolgsschwelle Denoise | Profile-Referenz |
| `upscaler.assess.loudness_shift_max_lu` | 1.5 | Validierungsgrenze Lautheitsverschiebung | Profile-Referenz |
| `upscaler.worker_lost_timeout_minutes` | 30 | Worker-Verlust-Erkennung | [Worker-Protokoll](../modules/audio-upscaler/worker-protocol.md) |
| `upscaler.request_role` | manager | Mindestrolle für Lauf-Anforderung | Profile-Referenz |

### `search.*`

| Schlüssel | Default | Bedeutung | Kapitel |
|---|---|---|---|
| `search.active_embedding_model` | — | aktives Modell (`"{name}:{version}"`) | [Embedding-Spezifikation](../modules/search/embedding-spec.md) |
| `search.rrf_k` | 60 | RRF-Fusionskonstante | Embedding-Spezifikation |
| `search.min_semantic_query_len` | 3 | Mindestlänge für semantische Stufe | Embedding-Spezifikation |

### `enrichment.*`

| Schlüssel | Default | Bedeutung | Kapitel |
|---|---|---|---|
| `enrichment.provider_order.<medientyp>` | s. Provider-Referenz | Provider-Präzedenz je Medientyp | [Enrichment](../modules/enrichment.md) |
| `enrichment.provider_order.<library>` | — | Bibliotheks-Override der Reihenfolge | [Provider-Referenz](../modules/enrichment/provider-reference.md) |
| `enrichment.egress_enabled` | true | globaler Egress-Schalter (Datenschutz) | Enrichment |
| `enrichment.limits.tmdb` (+ je Provider) | ~40 req/10 s | Rate-Limiter-Budget je Provider | Provider-Referenz |
| `enrichment.musicbrainz.contact` | — (Provider deaktiviert ohne Wert) | Pflicht-User-Agent-Kontakt (Etikette-Erzwingung) | Provider-Referenz |
| `enrichment.refresh.*` | 7/30/90 Tage | Refresh-Frequenzen je Staleness-Klasse | Provider-Referenz |

### `watch_state.*`

| Schlüssel | Default | Bedeutung | Kapitel |
|---|---|---|---|
| `watch_state.thresholds.<media_type>` | movie/episode 90 %, audiobook 99 %, track/comic_volume/ebook 95 % | Watched-Schwelle je Medientyp | [Watch-State](../modules/watch-state.md) |

### `monitoring.*`

| Schlüssel | Default | Bedeutung | Kapitel |
|---|---|---|---|
| `monitoring.quiet_windows` | — | Ruhefenster (Notifications unterdrückt, Zustände laufen weiter) | [Health Monitoring](../modules/health-monitoring.md) |

### `rule_engine.*` / `review.*` / `backup.*` / `dedup.*` / `ai_engine.*` (bislang nur beschreibend dokumentiert, hier erstmals mit Schlüssel normiert)

| Schlüssel | Default | Bedeutung | Kapitel |
|---|---|---|---|
| `rule_engine.default_cooldown_hours` | 24 (Minimum 1) | Default-Cooldown neuer Regeln | [Rule Engine](../modules/rule-engine.md) |
| `review.snooze_fallback_max_days` | 90 | Fallback-Reaktivierung bei nie eintretendem Snooze-Ereignis | [Review-System](../modules/review-system.md) |
| `backup.rotation_daily/weekly/monthly` | 7 / 4 / 6 | Rotationsschema | [Backup und Restore](../modules/backup-restore.md) |
| `backup.dump_encrypted` | true | DB-Dump-Verschlüsselung | Backup und Restore |
| `backup.restore_probe_interval_days` | 30 | Intervall der Restore-Probe | Backup und Restore |
| `dedup.chromaprint_similarity_threshold` | 0.15 | Ähnlichkeitsschwelle Audio-Fingerprinting | [Dedup/Fingerprinting](../modules/dedup-fingerprinting.md) |
| `dedup.hash_throughput_limit_mbps` | 100 | Token-Bucket-Drosselung Content-Hashing | Dedup/Fingerprinting |
| `ai_engine.embedding_batch_size` | 64 | Batch-Größe des Embedding-Dispatchers | [AI Engine](../modules/ai-engine.md) |

### Connector-Instanz-Settings (pro `connector_instances.settings`, nicht global)

| Schlüssel | Default | Bedeutung | Connector |
|---|---|---|---|
| `user_mappings` | — | Benutzer-Zuordnungstabelle | [Jellyfin](../connectors/jellyfin.md), [ABS](../connectors/audiobookshelf.md), [Stash](../connectors/stash.md) |
| `import_missing` | false | Ungematchte Items als Katalog-Einträge importieren | Jellyfin, ABS |
| `verify_tls` | true | TLS-Verifikation; `false` nur mit Fingerprint-Pinning | [Connector SDK](../connectors/connector-sdk.md) |
| `auto_search` | false | `presence→wanted` löst automatisch *arr-Suche aus | [*arr-Familie](../connectors/arr-family.md) |
| `trigger_abs_scan` | true | Export-Abschluss löst ABS-Bibliotheks-Scan aus | ABS |
| `conflict_strategy` | `latest_wins` | Konfliktauflösungs-Strategie (4 Werte) | Connector SDK |
| `restricted_log_retention_hours` | 48 (statt 14 Tage Default) | verkürzte Ingest-Log-Retention für sensible Inhalte | Stash |

### Fundament-weite Settings (nicht modul-namespaced, Kernschema/Architektur)

| Schlüssel | Default | Bedeutung | Kapitel |
|---|---|---|---|
| `scan.deletion_dampening_threshold` | 25 % | Lösch-Dämpfungs-Schwelle | [architecture/overview.md](overview.md) |
| `files.missing_grace_days` | 30 | Karenzfrist `missing`→`removed` | [core-schema.md](../database/core-schema.md) |
| `queue.assemble_parallelism` | 2 | Parallelität der `assemble`-Queue | [Job-Referenz](jobs-reference.md) |
| `queue.ai_job_timeout_minutes` | 120 | Timeout langlaufender AI-Jobs | Job-Referenz |
| `api.token_expiry_days` | 365 | Default-Ablauf neuer API-Tokens | [api/conventions.md](../api/conventions.md) |
| `api.rate_limit_standard/write/playback` | 300/60/600 pro Minute | Rate-Limits je Routenklasse | api/conventions.md |
| `security.cors_allowed_origins` | leer (CORS aus) | explizite Origin-Whitelist | [architecture/security.md](security.md) |

## Namespace-Governance

Ein neues Modul registriert **genau einen** Top-Level-Namespace (seinen Modulnamen oder eine anerkannte Kurzform, z. B. `assembler` statt `audiobookaudiobook_assembler`); Unterbereiche (`.mapper.`, `.rules.`, `.sequencer.`, `.aligner.`) gliedern Schlüsselgruppen, die zu einem Algorithmus/einer Phase gehören (durchgängiges Muster: Disc-Engine trennt `.mapper.` von `.rules.`, weil Klassifikator und Mapper unabhängig kalibriert werden). Ein Schlüssel ohne erkennbare Gruppenzugehörigkeit bleibt zweistufig (`upscaler.chunk_minutes`). Die Aufnahme in diesen Katalog ist Teil der Modul-Anlage-Prüfliste ([module-cookbook.md](../developer-handbook/module-cookbook.md)).

## Kalibrierungs-Verpflichtung (Zusammenfassung)

Ein wiederkehrendes Muster über nahezu alle algorithmischen Settings (Disc-Mapper, -Klassifikator, Assembler-Sequencer/-Aligner): Default-Änderungen laufen gegen die jeweilige Golden-File-/Kalibrierungs-Suite des Moduls, nie als isolierte Zahlenänderung. Dieser Katalog ist die Fundstelle, **welche** Schlüssel zusammengehörig kalibriert werden müssen (z. B. `disc_engine.mapper.weight_*` summiert konzeptionell zu einem Gewichtsbudget — eine Einzeländerung ohne Betrachtung der übrigen drei ist ein Kalibrierungsfehler).

## Tests

Ein Settings-Inventar-Contract-Test (analog Endpunkt-/Fehlercode-Katalog): Jede tatsächlich registrierte Settings-Klasse wird zur Laufzeit introspiziert (Reflection über die typisierten Default-Properties) und gegen dieses Dokument abgeglichen — ein Schlüssel im Code ohne Katalog-Zeile bricht den Build, eine Katalog-Zeile ohne Code-Entsprechung wird als Dokumentations-Drift gemeldet. Zusätzlich: Typ-Konsistenz-Test (ein Schlüssel, der in der DB als String, in der Settings-Klasse aber als `int` typisiert ist, muss beim Lesen einen klaren Fehler werfen, nie stillschweigend casten).
