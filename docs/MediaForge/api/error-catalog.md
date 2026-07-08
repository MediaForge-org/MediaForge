# API-Fehlercode-Gesamtkatalog

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Vertiefung zu [api/conventions.md](conventions.md) (Fehlerformat, RFC 9457) und [endpoint-catalog.md](endpoint-catalog.md). Jeder `code` im `code`-Feld der Problem-Details-Antwort ist hier gelistet, gruppiert nach Namensraum (`modul.fehler`, Konventions-Regel). Modulkapitel definieren ihre Codes normativ; dieser Katalog aggregiert sie für zwei Zwecke: **Konsistenzprüfung** (kein Code kollidiert, kein Muster wiederholt sich unbeabsichtigt) und **Konsumenten-Referenz** (ein CLI-Skript, das `disc.mapping_conflict` behandeln will, muss nicht acht Kapitel durchsuchen). Der Contract-Test „jede Fehlerquelle liefert Problem Details mit `code`" (Konventionen) verankert diesen Katalog vollständig gegen den Code — ein hier fehlender, im Code erzeugter Code bricht den Build, und umgekehrt.

## Namensraum-Register

| Namensraum | Modul | Kapitel |
|---|---|---|
| `disc.*` | Disc-Engine | [API-Referenz](../modules/disc-engine/api-reference.md) |
| `assembler.*` | Hörbuch-Assembler | [API/UI/Tests](../modules/audiobook-assembler/api-ui-tests.md) |
| `upscaler.*` | Audio-Upscaler | [audio-upscaler.md](../modules/audio-upscaler.md), [Profile](../modules/audio-upscaler/profiles-metrics.md) |
| `enrichment.*` | Enrichment | [enrichment.md](../modules/enrichment.md) |
| `catalog.*` | Fundament-Katalog | [core-schema.md](../database/core-schema.md) |
| `workflow.*` | Workflow Engine | [workflow-engine.md](../modules/workflow-engine.md) |
| `rule.*` | Rule Engine | [rule-engine.md](../modules/rule-engine.md) |
| `connector.*` | Connector SDK | [connector-sdk.md](../connectors/connector-sdk.md) |
| `quality.*` | Datenqualität | [data-quality.md](../modules/data-quality.md) |
| `dedup.*` | Dublettenerkennung | [dedup-fingerprinting.md](../modules/dedup-fingerprinting.md) |
| `search.*` | Suche | [search.md](../modules/search.md) |
| `ai.*` | AI Engine | [ai-engine.md](../modules/ai-engine.md) |
| `plugin.*` | Plugin SDK | [plugin-sdk.md](../developer-handbook/plugin-sdk.md) |
| `backup.*` | Backup/Restore | [backup-restore.md](../modules/backup-restore.md) |
| `auth.*` | Fundament-Auth | [conventions.md](conventions.md) |

Ein neuer Namensraum entsteht nur mit neuem Modul (1:1); ein Modul, das in den Namensraum eines anderen schreiben will, hat eine Abhängigkeits-Modellierungsfrage zu klären, keine Namensfrage.

## `disc.*` (vollständig, siehe [Disc-API-Referenz](../modules/disc-engine/api-reference.md) für Kontext)

| Code | Status | Bedeutung |
|---|---|---|
| `analysis_running` | 409 | Analyse-Lauf bereits aktiv |
| `analysis_failed_state` | 409 | Struktur nie erfolgreich analysiert |
| `mapping_conflict` | 409 | Unique-Invariante 3 (Ganz-/Segment-Mapping) verletzt |
| `mapping_not_confirmable` | 409 | falscher Ausgangsstatus (rejected/superseded) |
| `mapping_target_not_consumable` | 422 | Invariante 4 (Container als Ziel) |
| `segments_in_use` | 409 | bestätigte Mappings blockieren Neusegmentierung |
| `segments_invalid_partition` | 422 | Überlappung/Grenzen außerhalb `[0, duration]` |
| `set_container_not_container` | 422 | Set-Container-Ziel ist konsumierbar |
| `set_position_conflict` | 422 | doppelte/lückenhafte Disc-Reihenfolge |
| `session_not_active` | 409 | Playback-Session beendet/verworfen |
| `session_ref_invalid` | 422 | `disc_ref` abgelaufen/ungültig |
| `reporting_unsupported` | 422 | deklarierter Reporting-Modus unbekannt |
| `event_batch_too_large` | 422 | > 100 Events im Batch |
| `search_space_too_large` | 422 | Mapper-Schutzgrenze N·M überschritten |

## `assembler.*` (vollständig, siehe [Assembler-API](../modules/audiobook-assembler/api-ui-tests.md))

| Code | Status | Bedeutung |
|---|---|---|
| `sequence_running` | 409 | Sequenzierung bereits aktiv |
| `sequence_incomplete` | 422 | Reorder-Liste unvollständig/überzählig |
| `unknown_file` | 422 | Datei-ID nicht Teil der Edition |
| `not_sequenced` | 409 | Quellensammlung vor Sequenzierung angefordert |
| `not_aligned` | 409 | Aktivierung eines nicht alignierten Sets |
| `ai_requires_user` | 403 | KI-Set-Aktivierung ohne menschlichen Kontext (System-/Workflow-Aufrufer) |
| `hierarchy_conflict` | 409 | offener Aktivierungs-Konflikt (Selector-Fälle a–c) |
| `invalid_partition` | 422 | Kapitel-Edit verletzt Partitions-Invariante |
| `empty_titles` | 422 | Kapitel-Edit ohne Titel |
| `ai_engine_disabled` | 409 | KI-Vorschlag angefordert, Feature deaktiviert |
| `no_active_set` | 409 | Build ohne aktives Chapter Set |
| `unknown_target` | 422 | unbekanntes Build-Target |
| `build_running` | 409 | Build desselben Targets bereits aktiv |
| `stale_assembly` | 409 | Build auf `stale`-Assembly (Re-Sequenzierung nötig) |

## `upscaler.*`

| Code | Status | Bedeutung |
|---|---|---|
| `invalid_params` | 422 | Profil-Parameter außerhalb Schema-Bereich |
| `model_unavailable` | 409 | kein lebender Worker mit passendem (Modell, Version, Hash) |
| `rejected_pointless` | — (kein HTTP-Fehler; Run-Fachzustand) | Preflight-Ablehnung, siehe [Profile-Referenz](../modules/audio-upscaler/profiles-metrics.md) |
| `duplicate_active_run` | 409 | zweiter aktiver Lauf gleicher Quelle+Signatur+Profil |
| `request_role_insufficient` | 403 | Anforderung unterhalb `upscaler.request_role`-Settings |

## `enrichment.*`

| Code | Status | Bedeutung |
|---|---|---|
| `field_locked` | 409 | Schreibversuch auf gelocktes Feld außerhalb der Merge-Engine |
| `provider_not_configured` | 409 | Refresh ohne konfigurierten Provider-Zugang |
| `mapper_schema_mismatch` | 500 (Job-Fachfehler, nicht API-direkt) | Payload-Schema-Bruch, s. Modulkapitel |
| `egress_disabled` | 409 | Refresh bei global deaktiviertem Egress-Schalter |
| `asset_slot_locked` | 409 | automatische Aktiv-Wahl gegen gelockten Slot |

## `catalog.*` (Fundament)

| Code | Status | Bedeutung |
|---|---|---|
| `hierarchy_invalid` | 422 | Parent-Typ-Kompatibilität verletzt (z. B. Episode unter Film) |
| `subject_not_consumable` | 422 | Watch-State-Route auf Container/Disc-Subjekt |
| `edition_primary_conflict` | 409 | zweite primäre Edition ohne Überschreib-Bestätigung |
| `duplicate_path` | 409 | `(library_id, path)` bereits vergeben |
| `not_found` | 404 | generische Existenz-/Sichtbarkeits-Ablehnung (kein Existenz-Orakel) |

## `workflow.*`

| Code | Status | Bedeutung |
|---|---|---|
| `definition_not_found` | 404 | unbekannte Workflow-Definition |
| `definition_disabled` | 409 | Definition deaktiviert |
| `instance_not_cancelable` | 409 | Instanz bereits terminal |
| `subject_conflict` | 409 | konkurrierende Instanz auf demselben Subjekt (sofern die Definition Exklusivität deklariert) |
| `batch_too_large` | 422 | Batch-Start über Subjekt-Obergrenze |

## `rule.*`

| Code | Status | Bedeutung |
|---|---|---|
| `invalid_predicate` | 422 | Regel-Bedingung gegen `rule-schema` ungültig |
| `invalid_action` | 422 | Regel-Aktion außerhalb der erlaubten Capability-Liste |
| `test_run_failed` | 422 | Trockenlauf gegen Testsubjekt gescheitert (Detail im Body) |
| `already_paused` / `already_active` | 409 | Statuswechsel ohne Wirkung angefordert |

## `connector.*`

| Code | Status | Bedeutung |
|---|---|---|
| `instance_test_failed` | 422 | Verbindungstest gescheitert (Detail: Netzwerk/Auth/Version) |
| `sync_running` | 409 | Sync-Lauf bereits aktiv |
| `webhook_signature_invalid` | 401 | Signaturprüfung des Webhook-Pfads gescheitert |
| `webhook_instance_unknown` | 404 | Instanz-ULID im Webhook-Pfad unbekannt |
| `unsupported_capability` | 422 | angeforderte Aktion außerhalb der deklarierten Connector-Capabilities |

## `quality.*` / `dedup.*`

| Code | Status | Bedeutung |
|---|---|---|
| `quality.unknown_dimension` | 422 | Worklist-Filter mit unbekannter Qualitätsdimension |
| `dedup.group_already_resolved` | 409 | Auflösung eines bereits entschiedenen Dubletten-Verdachts |
| `dedup.merge_target_invalid` | 422 | Merge-Ziel ist selbst Teil der Quellmenge |

## `search.*` / `ai.*` / `plugin.*` / `backup.*` / `auth.*`

| Code | Status | Bedeutung |
|---|---|---|
| `search.semantic_unavailable` | 200 mit Warnfeld (kein Hartfehler) | pgvector/Embeddings fehlen; Fallback auf Trigram, siehe [search.md](../modules/search.md) |
| `ai.model_not_registered` | 422 | Modellreferenz ohne Registry-Eintrag |
| `plugin.trust_violation` | 403 | Plugin-Capability außerhalb des deklarierten Trust-Levels ([ADR-0012](../adr/0012-plugin-trust-model.md)) |
| `backup.restore_in_progress` | 409 | zweiter Restore-Versuch während laufendem |
| `auth.token_revoked` | 401 | Bearer-Token widerrufen |
| `auth.scope_insufficient` | 403 | Scope-Matrix-Verletzung (Konventionen) |

## Konsistenzregeln (normativ für neue Codes)

1. **Ein Code, eine Bedeutung, ein Namensraum.** Kein Code wird modulübergreifend wiederverwendet, auch nicht bei identischer Semantik (`disc.session_not_active` und ein hypothetisches `upscaler.session_not_active` beschreiben unterschiedliche Zustandsmaschinen — Wiederverwendung suggeriert eine Kopplung, die nicht besteht).
2. **Statuscode folgt aus der Fehlerklasse, nicht aus Bequemlichkeit**: Zustandskonflikte (parallele Läufe, Unique-Verletzungen der Fachlogik) sind immer 409, nie 422; Validierungsfehler (Payload-Form, Bereichsgrenzen) sind immer 422, nie 409 — ein Code, der beides je nach Kontext liefert, ist ein Designfehler (Konventions-Prinzip: Statuscodes sind kanonisch).
3. **Fachzustände sind keine Fehlercodes**, wenn sie kein HTTP-Fehlerstatus tragen (`upscaler.rejected_pointless` ist ein Run-Zustand, kein API-Fehler — die Anfrage selbst war 202 erfolgreich; der Code lebt im Run-Datensatz, nicht im Problem-Details-Feld). Der Katalog führt solche Fälle explizit mit „—" in der Status-Spalte, damit sie nicht fälschlich als API-Fehler implementiert werden.
4. **Jeder Code hat einen erzeugenden Test** (Modulkapitel-Testkataloge, z. B. `API-01…14` der Disc-Engine, `AB-API-*` des Assemblers) — ein Code ohne Test ist unverifizierte Dokumentation und in Reviews zu beanstanden.
5. **Deprecation** folgt der API-Versionierung: Ein Code verschwindet nie innerhalb v1 (er kann durch einen präziseren ergänzt werden, `Deprecation`-Header-Analogie gilt sinngemäß über die Dokumentation dieses Katalogs — ein als „historisch" markierter Code bleibt hier mit Vermerk, statt zu verschwinden und Konsumenten-Fehlerbehandlung stillschweigend zu brechen).

## Prüfliste für neue Fehlercodes (PR-Checkliste)

Namensraum korrekt (Register oben) · Status nach Klasse (Regel 2) · Kein Duplikat (Volltextsuche über diesen Katalog vor dem Anlegen) · Zeile im Modulkapitel **und** hier · erzeugender Test benannt · bei Fachzustand ohne HTTP-Wirkung explizit als „—" geführt. Die CI-Bindung (Konventionen: „jede Fehlerquelle liefert Problem Details mit `code`") scannt den Code nach `ProblemDetailsException`-Konstruktionen und gleicht jeden literalen `code`-Wert gegen dieses Dokument ab — ein Code im Code ohne Katalogzeile bricht den Build, eine Katalogzeile ohne Code-Vorkommen wird als Drift-Warnung gemeldet (totes Dokumentationsrelikt).
