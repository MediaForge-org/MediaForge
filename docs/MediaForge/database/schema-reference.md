# Datenbank-Gesamtreferenz

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Vertiefung zu [database/core-schema.md](core-schema.md); Querschnitts-Referenz über **alle** Tabellen des Systems (Fundament + Module). Dieses Dokument wiederholt keine DDL — es aggregiert, was kein Einzelkapitel zeigen kann: das Gesamtinventar mit Eigentümerschaft und Wachstumsklassen, den Kaskaden-Graphen, die Kataloge der Constraint-Muster, das JSONB-Register und die Morph-Alias-Registry. Es ist das Dokument, das ein DBA liest, bevor er die erste Wartung fährt, und das ein Reviewer konsultiert, wenn eine neue Tabelle die Konventionen einhalten soll. Zusätzlich normiert es zwei bisher offene DDL-Bausteine (`user_container_progress`, Partitions-Automatik).

## Tabellen-Inventar

Gruppiert nach Eigentümer-Modul. **Wachstumsklasse**: S (statisch/klein, < 10³), M (mittel, 10³–10⁵), L (groß, 10⁵–10⁷), XL (append-heavy, > 10⁷, Retention-pflichtig). **Retention**: ∞ (Bestand), R (konfigurierbare Ausdünnung), K (Karenz + GC).

| Tabelle | Eigentümer | Klasse | Retention | Bemerkung |
|---|---|---|---|---|
| `libraries` | Fundament | S | ∞ | |
| `files` | Fundament | L | ∞ (Status-Lebenszyklus) | nie Soft-Delete |
| `media_items` | Fundament | L | ∞ (Soft-Delete) | Selbst-Hierarchie |
| `episode_details`, `audiobook_details` | Fundament | L/M | ∞ | 1:1-Satelliten |
| `media_editions`, `edition_files` | Fundament | L | ∞ (Soft-Delete/∞) | |
| `people`, `credits` | Fundament | M/L | ∞ | |
| `tags`, `taggables` | Fundament | S/L | ∞ | |
| `provider_ids` | Fundament | L | ∞ | polymorph, dokumentierte FK-Ausnahme |
| `users` | Fundament | S | ∞ (Soft-Delete) | |
| `user_watch_states` | Fundament | L | ∞ | „ungesehen" = keine Zeile |
| `watch_state_events` | Fundament | **XL** | R (24 Mon. Default) | **partitioniert** (monatlich, `occurred_at`) |
| `user_container_progress` | Fundament | L | Cache (rebuildbar) | DDL unten |
| `review_tasks` | Fundament | M | ∞ | |
| `artifacts` | Fundament | M | K (orphaned-GC) | |
| `settings` | Fundament | S | ∞ | nur Abweichungen vom Default |
| `job_checkpoints`, `job_progress` | Fundament | M | K (nach Abschluss 30 Tage) | |
| `audit_log` (+ Satelliten, [modules/audit.md](../modules/audit.md)) | Audit | **XL** | R (gesetzlich/Betreiber) | partitioniert wie Events |
| `disc_sets`, `disc_images`, `disc_clips`, `disc_playlists`, `disc_playlist_items`, `disc_playlist_marks`, `disc_segments`, `disc_menus` | Disc-Engine | M | ∞ | Strukturebene |
| `disc_episode_mappings` | Disc-Engine | M | ∞ (`superseded`-Kette) | Interpretationsebene |
| `disc_playback_sessions`, `disc_playback_events` | Disc-Engine | L/XL | R (mit Audit-Retention) | Nutzungsebene, append-only |
| `audiobook_assemblies`, `audiobook_tracks` | Assembler | M/L | ∞ | |
| `chapter_sets`, `chapters` | Assembler | M/L | ∞ | Kandidaten bleiben (Vergleich) |
| `upscale_profiles`, `upscale_runs` | Upscaler | S/M | ∞ (Processing History) | |
| `provider_payloads` | Enrichment | L | Upsert (kein Verlauf) | Spiegel, TOAST-lastig |
| `enrichment_runs` | Enrichment | L | R (12 Mon.) | Diagnose |
| `asset_candidates` | Enrichment | L | K (mit Artefakt) | |
| `workflow_definitions`, `workflow_instances`, `workflow_steps` ([workflow-engine](../modules/workflow-engine.md)) | Workflow | S/M/L | ∞/R | |
| `rules`, `rule_executions` ([rule-engine](../modules/rule-engine.md)) | Rule Engine | S/L | ∞/R (6 Mon.) | |
| `embeddings` ([search](../modules/search.md)) | Suche | L | Cache (rebuildbar) | pgvector |
| `kg_relations` ([knowledge-graph](../modules/knowledge-graph.md)) | Knowledge Graph | L | ∞ | |
| `fingerprints`, `duplicate_groups` ([dedup](../modules/dedup-fingerprinting.md)) | Dedup | L/M | ∞ | |
| `quality_scores`, `quality_findings` ([data-quality](../modules/data-quality.md)) | Datenqualität | L | Cache (rebuildbar) | |
| `connector_instances`, `connector_sync_states`, `connector_entity_links` ([connector-sdk](../connectors/connector-sdk.md)) | Connectoren | S/S/L | ∞ | |
| `plugin_registrations` ([plugin-sdk](../developer-handbook/plugin-sdk.md)) | Plugins | S | ∞ | |
| `health_checks`, `health_incidents` ([health-monitoring](../modules/health-monitoring.md)) | Health | S/M | R (6 Mon.) | |
| `backup_manifests` ([backup-restore](../modules/backup-restore.md)) | Backup | M | R (Aufbewahrungsplan) | |

Aufnahme-Regel für neue Tabellen: Zeile in dieses Inventar (Klasse + Retention begründet) ist Teil der Definition-of-Done einer Schema-Migration ([developer-handbook/getting-started.md](../developer-handbook/getting-started.md), PR-Checkliste) — das Inventar driftet sonst in einem Quartal.

## `user_container_progress` (normative DDL — löst den offenen Punkt des Kernschemas)

```sql
CREATE TABLE user_container_progress (
    user_id        CHAR(26) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    media_item_id  CHAR(26) NOT NULL REFERENCES media_items(id) ON DELETE CASCADE,  -- Container
    consumable_total   INTEGER NOT NULL,
    watched_count      INTEGER NOT NULL DEFAULT 0,
    in_progress_count  INTEGER NOT NULL DEFAULT 0,
    last_activity_at   TIMESTAMPTZ,
    next_up_item_id    CHAR(26) REFERENCES media_items(id) ON DELETE SET NULL,
    refreshed_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, media_item_id),
    CHECK (watched_count + in_progress_count <= consumable_total)
);
CREATE INDEX ucp_continue_idx ON user_container_progress (user_id, last_activity_at DESC)
    WHERE in_progress_count > 0;
```

Cache-Vertrag: nachgeführt von Listenern auf `EpisodeWatched`/Watch-State-Änderungen/Katalog-Strukturänderungen (neue Episode erhöht `consumable_total`); `next_up_item_id` ist die vorberechnete „nächste Folge" (kleinster `sort_index` ohne watched-State — die teuerste UI-Frage als Spalte); vollständig rekonstruierbar per `mediaforge:rebuild-progress` (chunked, bibliotheksweise). Der Cache hat **keine** Autorität: Jede Diskrepanz zur Ableitung aus `user_watch_states` ist ein Cache-Bug, den der nächtliche Konsistenz-Check des Datenqualitätsmoduls als Metrik meldet (`ucp_drift_count`; > 0 ⇒ Health-Warnung, Auto-Rebuild der betroffenen Zeilen).

## Partitions-Automatik (normativ — löst den zweiten offenen Punkt)

`watch_state_events`, `audit_log` und `disc_playback_events` sind `PARTITION BY RANGE`-Tabellen mit Monatspartitionen. Der Scheduler-Job `MaintainPartitionsJob` (monatlich, 1. des Monats 02:00 Instanzzeit) legt die Partition des übernächsten Monats an (immer zwei im Voraus — ein ausgefallener Lauf reißt kein Loch) und wendet Retention an: Partitionen, deren Obergrenze älter als die konfigurierte Frist ist, werden `DETACH CONCURRENTLY` + `DROP` (nie `DELETE`; Kernschema-Begründung). Vor dem Drop schreibt der Job die Partition optional als Parquet-Export ins Backup-Volume (`retention.archive_before_drop`, Default true für `audit_log`, false für Events — Audit ist rechenschafts-, Events sind diagnose-motiviert). Fehlende künftige Partition ist ein Health-Check-Kriterium (Insert in nicht existierende Partition wäre ein Produktionsausfall — der Check schlägt **vor** dem Monatswechsel an, nicht danach).

## Kaskaden-Graph (Lösch-Semantik)

Was passiert bei Löschung der wichtigsten Wurzeln — die Kette, die man vor jedem `DELETE` verstanden haben muss:

```
libraries ─CASCADE→ files ─CASCADE→ edition_files
                    files ─CASCADE→ disc_images ─CASCADE→ [gesamte Disc-Struktur- und Interpretationsebene]
                    files ─SET NULL→ disc_playback_sessions (Nutzungsebene bleibt!)
libraries ─SET NULL→ media_items.library_id (Katalog überlebt Bibliotheks-Löschung)
media_items ─CASCADE→ media_editions ─CASCADE→ edition_files, audiobook_assemblies ─CASCADE→ tracks, chapter_sets ─CASCADE→ chapters
media_items ─CASCADE→ user_watch_states, watch_state_events, credits, episode_details, disc_episode_mappings
media_items ─CASCADE (parent_id)→ Unterbaum (Show-Löschung reißt Seasons/Episoden)
people ─RESTRICT← credits (Personen mit Credits sind unlöschbar; erst Merge/Reassign)
users ─CASCADE→ watch_states/events/sessions; ─SET NULL→ resolved_by, confirmed_by, updated_by (Entscheidungen bleiben, Bezug anonymisiert)
upscale_profiles ─RESTRICT← upscale_runs (Historie schützt Profile vor Löschung; Deaktivierung statt Löschung)
artifacts ─CASCADE→ asset_candidates.artifact_id
```

Zwei bewusste Asymmetrien verdienen Wiederholung: (1) Disc-**Nutzung** überlebt die Datei (Statistik/Audit; Modulkapitel-Invariante 1); (2) der **Katalog** überlebt die Bibliothek (Watch-States sind wertvoller als der Mount — eine versehentlich gelöschte Bibliothek plus Re-Scan verliert keine Historie; das Fingerprinting näht Dateien über `content_hash` wieder an). Soft-Delete-Wechselwirkung: `deleted_at`-Zeilen kaskadieren nichts — erst der Hard-Delete durch den Housekeeping-GC (Karenz 30 Tage) löst die Ketten aus; die Lösch-Dämpfung der Scan-Pipeline und `mass_deletion`-Reviews liegen davor.

## Katalog der partiellen Unique-Indizes („höchstens eins"-Muster)

Das Fundament-Muster (Kernschema) mit allen Instanzen — die Liste ist der Vertrag, dass Eindeutigkeits-Fachregeln in der Datenbank leben, nicht im Code:

| Index | Tabelle | Regel |
|---|---|---|
| `media_editions_one_primary` | media_editions | eine primäre Edition je Item (lebend) |
| `disc_mappings_one_confirmed_full` | disc_episode_mappings | ein bestätigtes Ganz-Playlist-Mapping |
| `disc_mappings_one_confirmed_per_segment` | disc_episode_mappings | ein bestätigtes Mapping je Segment |
| `chapter_sets_one_active` | chapter_sets | ein aktives Set je Assembly |
| `asset_one_active_per_slot` | asset_candidates | ein aktives Asset je Slot |
| `upscale_runs_no_duplicate_active` | upscale_runs | kein doppelter aktiver Lauf (Quelle+Signatur+Profil) |
| `review_tasks_no_duplicate_open` | review_tasks | kein doppeltes offenes Review je Subjekt+Typ |
| `artifacts_idempotency` | artifacts | ein aktives Artefakt je (generator, input_signature) |
| `provider_ids_one_per_provider` | provider_ids | ein Mapping je Entität+Provider |

## Katalog der Exclusion Constraints

| Constraint | Tabelle | Garantie |
|---|---|---|
| Segment-Nichtüberlappung | `disc_segments` | eindeutige Position→Episode-Übersetzung ([Disc-Engine](../modules/disc-engine.md), Invariante 2) |
| Kapitel-Nichtüberlappung | `chapters` | Partitionseigenschaft je Chapter Set ([Assembler](../modules/audiobook-assembler.md)) |

Beide über `int8range`-GiST; beide benötigen die `btree_gist`-Extension (Pflicht-Extension-Liste unten). Neue Bereichs-Fachregeln haben diese beiden als Vorlage — ein Bereichs-Overlap-Bug, den ein Constraint hätte verhindern können, ist ein Architektur-Review-Befund.

## Pflicht-Extensions

`pg_trgm` (Titel-/Namenssuche), `btree_gist` (Exclusion Constraints), `pgvector` (Suche; optional installierbar — das Such-Modul degradiert dokumentiert, wenn absent). Migrationen prüfen Extensions mit klarer Fehlermeldung (`CREATE EXTENSION IF NOT EXISTS` + Rechte-Hinweis im Fehlerfall); das Compose-Deployment liefert ein Postgres-Image mit allen dreien ([deployment.md](../architecture/deployment.md)).

## JSONB-Register

Jede JSONB-Spalte des Systems mit Kategorie (Kernschema-Kriterien) und Schema-Eigentümer — das Register ist die Antwort auf „ist dieses JSONB legitim?":

| Spalte | Kategorie | Schema/Eigentümer |
|---|---|---|
| `libraries.settings` | Konfiguration | Scan-Pipeline |
| `files` → (keine) | — | Dateien sind vollständig relational |
| `disc_images.raw_analysis` | Werkzeug-Rohoutput | `disc-analysis/v1` (media-tools) |
| `disc_playlists.classification_evidence` | Evidence | [Regelkatalog](../modules/disc-engine/classification-rules.md) |
| `disc_episode_mappings.evidence` | Evidence | [Mapping-Spezifikation](../modules/disc-engine/mapping-algorithm.md) |
| `disc_clips.audio_streams` | Anzeige | Disc-Engine (nie gefiltert) |
| `audiobook_assemblies.sequencer_evidence` | Evidence | [Sequenzierungs-Katalog](../modules/audiobook-assembler/sequencing-rules.md) |
| `audiobook_tracks.tag_snapshot` | Werkzeug-Rohoutput | media-tools Tag-Reader |
| `chapter_sets.raw_source` / `alignment_report` | Rohoutput / Evidence | [Formatreferenz](../modules/audiobook-assembler/chapter-source-formats.md) / [Aligner](../modules/audiobook-assembler/alignment-algorithm.md) |
| `upscale_profiles.params`, `upscale_runs.profile_snapshot` | Parameter | [Profile-Referenz](../modules/audio-upscaler/profiles-metrics.md) |
| `upscale_runs.metrics_before/after` | Werkzeug-Rohoutput | `audio-metrics/v1` |
| `upscale_runs.worker_info` | Diagnose | [Worker-Protokoll](../modules/audio-upscaler/worker-protocol.md) |
| `provider_payloads.payload` | Werkzeug-Rohoutput | Provider-APIs (Spiegel) |
| `enrichment_runs.decisions` | Evidence | Merge-Engine |
| `media_items.field_provenance` | Anzeige/Diagnose | [Enrichment](../modules/enrichment.md) |
| `watch_state_events.context` | Evidence | Kernschema |
| `review_tasks.evidence/resolution` | Evidence | Kernschema |
| `artifacts.params` | Parameter | Erzeuger-Module |
| `settings.value` | Konfiguration | typisierte Settings-Klassen |

Aufnahme neuer JSONB-Spalten: Kriterien-Nachweis im Modulkapitel **und** Zeile hier (PR-Checkliste). Kategorien außerhalb der fünf (Rohoutput, Evidence, Parameter, Konfiguration, Anzeige) existieren nicht — wer eine sechste braucht, hat vermutlich ein Relationenproblem.

## Morph-Alias-Registry

Polymorphe Spalten (`entity_type`, `subject_type`, `source_type`, `taggable_type`) verwenden Eloquent-Morph-Aliase aus einer zentralen Registry (`App\Core\MorphMap`): `media_item`, `media_edition`, `file`, `person`, `library`, `disc_image`, `disc_playlist`, `audiobook_assembly`, `chapter_set`, `upscale_run`, `artifact`, `user`, `connector_instance`, `workflow_instance`, `rule`. Klassennamen in der Datenbank sind ein Review-Defekt (Refactoring-Bruchstelle); die Registry ist per Architektur-Test erzwungen (unbekannter Alias in einer Morph-Spalte ⇒ Testfehler). Neue Aliase: Registry + dieses Register + Waisen-Check-Erweiterung des Datenqualitätsmoduls (der nächtliche Check kennt je Alias die Existenz-Query).

## Zugriffs-Matrix (Rollen × Tabellenbereiche)

Verdichtete Policy-Sicht (normativ sind die Policies, [architecture/security.md](../architecture/security.md)):

| Bereich | member | manager | admin |
|---|---|---|---|
| Katalog lesen, eigene Watch-States/Sessions schreiben | ✓ | ✓ | ✓ |
| Reviews, Mappings, Assemblies, Enrichment-Aktionen, Upscale-Anforderung | — | ✓ | ✓ |
| Bibliotheken, Settings, Profile, Connector-Instanzen, Benutzer | — | — | ✓ |
| Fremde Watch-States | — (nie lesend/schreibend) | — | Aggregat-Statistik ohne Einzelansicht |

Die letzte Zeile ist eine bewusste Datenschutz-Festlegung: Auch `admin` sieht keine fremden Einzel-Watch-States (nur Systemstatistik); Konnektoren synchronisieren strikt benutzergebunden über Account-Verknüpfungen der Connector-Instanzen.

## Namens- und Typ-Prüfliste für Schema-Reviews

Kondensat der Konventionen als abhakbare Liste (der Schema-Teil des PR-Templates): ULID-PK · Enum als TEXT+CHECK · FK mit explizitem ON DELETE · `created_at/updated_at` · fachliche Zeitpunkte benannt · keine absoluten Pfade · JSONB nur mit Register-Eintrag · Wachstumsklasse deklariert · Indizes für jede dokumentierte Query ([query-catalog.md](query-catalog.md)) · partielle Uniques für „höchstens eins" · Soft-Delete nur mit Wiederherstellungs-Anwendungsfall · Morph nur mit Registry-Alias · Partitionierung ab XL-Klasse · Constraint-Tests im Modul-Testkatalog.
