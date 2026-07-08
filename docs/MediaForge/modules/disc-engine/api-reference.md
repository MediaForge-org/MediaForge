# Disc-Engine: API-Referenz

Vertiefung zu [modules/disc-engine.md](../disc-engine.md), Abschnitt „API-Endpunkte". Vollständige Verträge aller `/api/v1`-Routen der Engine nach den [API-Konventionen](../../api/conventions.md) (Problem Details, Cursor-Pagination, 202-Muster, Scopes — hier nicht wiederholt). Externe Anwendungsfälle je Routen-Gruppe sind benannt (Konventions-Grundsatz: keine Route ohne externen Konsumenten). OpenAPI bleibt die maschinenlesbare Quelle; dieses Dokument erklärt Semantik und Randfälle, die ein Schema nicht trägt.

## Konsumenten-Übersicht

| Gruppe | Externe Konsumenten |
|---|---|
| Disc-Lesend (`/discs`, `/disc-sets`) | CLI-Automatisierung (Sammlungs-Inventar), Kodi-Add-on (Disc-Browser) |
| Mapping-Mutationen (`/disc-mappings`, Segmente) | CLI-Batch-Bestätigung, künftige Mobile-Review-Clients |
| Player-Protokoll (`/playback/disc-sessions`) | jede External-Player-Integration (normativer Vertrag) |
| Reanalyse | Betriebs-Skripte nach Analyzer-Updates |

## Lesende Routen

### `GET /api/v1/discs`

Scope `read`, Rolle member+. Filter: `library_id` (ULID), `set_id` (ULID), `status` (`unmapped|partial|watched|unwatched` — gegen `derived_status` des anfragenden Users), `kind` (`bluray|uhd_bluray|dvd`), `analysis_status`, `q` (Label-/Katalogtitel-Suche). Sort-Whitelist: `created_at` (Default `-created_at`), `label`. Cursor-Pagination.

```json
{"data": [{
   "id": "01J8ZK3V…", "disc_kind": "uhd_bluray", "source_form": "iso",
   "label": "STAGE_3_DISC_2", "analysis_status": "analyzed",
   "structure_signature": "b3:9f42…",
   "set": {"id": "01J8ZJQ0…", "name": "Staffel 3 (Blu-ray Box)", "position": 2, "size": 4},
   "catalog_link": {"kind": "season", "media_item_id": "01J8Y…", "title": "Staffel 3"},
   "menus": {"hdmv": true, "bdj": false, "dvd": false},
   "mapping_summary": {"episode_candidates": 6, "confirmed": 6, "suggested": 0, "review_open": false},
   "watch_summary": {"mapped": 6, "watched": 3, "in_progress": 1, "derived_status": "partial"},
   "created_at": "2026-05-11T09:14:02Z"
 }],
 "meta": {"next_cursor": "eyJpZCI6…", "per_page": 50}, "links": {…}}
```

`watch_summary` ist benutzerbezogen (Token-User) und stammt aus dem Progress-Cache, nie aus der Live-View (Performance-Abschnitt des Modulkapitels). Kein Feld enthält Dateipfade (Konventions-Security); die Datei ist über `file_id` in der Detail-Route referenzierbar, deren Auflösung Admin-Routen des Fundaments vorbehalten bleibt.

### `GET /api/v1/discs/{ulid}`

Scope `read`. Vollständiges Strukturbild: Disc-Kopf (wie Listeneintrag) plus `playlists[]` (mit Klassifikation, Confidence, Mapping-Status), `anomalies[]`, `analyzer_version`, `analyzed_at`. Playlists sind eingebettet (eine Disc hat < 200 nach Faltung — keine eigene Pagination nötig); `raw_analysis` ist **nicht** enthalten (bis 5 MB; eigene Route unten). 404 bei fremder Bibliothek ohne Sichtbarkeit (kein Existenz-Orakel).

### `GET /api/v1/discs/{ulid}/playlists`

Scope `read`. Playlists mit voller Tiefe: Items (Clips mit Codec/HDR-Attributen), Marken, Segmente, Mappings inkl. `evidence` und `second_best`. Anwendungsfall: Review-Automatisierung und Diagnose. Query `?classification=` filtert. Beispiel-Auszug einer Playlist:

```json
{"ref": "00004", "duration_ms": 2592000, "chapter_count": 5,
 "classification": "episode_candidate", "classification_confidence": 0.90,
 "classified_by": "heuristic",
 "items": [{"seq": 1, "clip_ref": "00012", "in_ms": 0, "out_ms": 2581040,
            "video": {"codec": "hevc", "width": 3840, "height": 2160, "hdr": "dolby_vision"}}],
 "chapters_ms": [0, 122000, 601000, 1489000, 2405000],
 "segments": [],
 "mappings": [{"id": "01J8ZM…", "media_item_id": "01J8Y…",
               "episode": {"season": 3, "episode": 8, "title": "…"},
               "status": "confirmed", "confidence": 0.95, "mapping_source": "heuristic",
               "segment_id": null, "confirmed_at": "2026-05-11T09:20:44Z",
               "evidence": {…}}]}
```

### `GET /api/v1/discs/{ulid}/raw-analysis`

Scope `read`, Rolle manager+ (Diagnose-Route; das Roh-JSON kann interne Pfadfragmente des Analyzer-Kontexts enthalten). Liefert das `disc-analysis/v1`-Dokument unverändert. `ETag` über `analyzer_version + analyzed_at` (Debug-Clients cachen).

### `GET /api/v1/disc-sets` und `GET /api/v1/disc-sets/{ulid}`

Scope `read`. Liste (Filter `container_item_id`, `confirmed`) und Detail (Discs in Positionsreihenfolge, Set-weite Mapping-Matrix wie im UI: `matrix: [{disc_position, episode_id, mapping_status}]`). Offset-Pagination erlaubt (kleine Tabelle, Konventions-Ausnahme).

### `GET /api/v1/users/me/disc-sessions`

Scope `read`. Eigene Playback-Sessions (Filter `status`, `disc_image_id`; Default letzte 30 Tage), inkl. Diagnose-Aggregat und `pending_ack` (offene manuelle Bestätigungen). Anwendungsfall: Player-Add-ons zeigen „zuletzt gespielt" und offene Bestätigungskarten.

## Mutations-Routen

### `POST /api/v1/discs/{ulid}/reanalyze`

Scope `write:operations`, Rolle manager+. Body: `{"structure": true|false}` — `false` (Default) re-klassifiziert/re-mappt auf gespeicherter Struktur ([Regelkatalog](classification-rules.md), „Re-Klassifikation"), `true` erzwingt media-tools-Neuanalyse des Images (I/O-teuer). Antwort 202 mit Operations-Referenz. 409 `disc.analysis_running`, wenn bereits ein Lauf aktiv ist. Idempotency-Key unterstützt.

### `POST /api/v1/disc-mappings/{ulid}/confirm`

Scope `write:catalog`, Rolle manager+. Body leer. Wirkung = Action `ConfirmDiscEpisodeMapping` (Invarianten-Validierung, Review-Auflösung, Reprocessing-Dispatch, Audit — Modulkapitel). Antwort 200 mit dem bestätigten Mapping. Fehler: 409 `disc.mapping_conflict` (konkurrierendes bestätigtes Mapping), 409 `disc.mapping_not_confirmable` (Status ist `rejected`/`superseded`), 422 `disc.mapping_target_not_consumable` (Invariante 4 — kann bei API-Konsumenten auftreten, die veraltete Mapping-IDs bestätigen, deren Ziel-Item zwischenzeitlich zum Container wurde).

### `POST /api/v1/disc-mappings/{ulid}/reject`

Wie confirm; Body optional `{"note": "…"}` (wandert in den Audit-Kontext). Ablehnung eines bestätigten Mappings ist erlaubt (Rückzug) und erzeugt den `metadata_conflict`-Pfad der [Playback-Übersetzung](playback-translation.md), wenn bereits Fortschritt angerechnet war.

### `POST /api/v1/disc-playlists/{ulid}/remap`

Scope `write:catalog`, Rolle manager+. Body: `{"media_item_id": "01J…", "segment_id": null}`. Wirkung = `RemapDiscPlaylist` (altes Mapping `superseded`, neues `confirmed` mit `mapping_source='manual'`, Confidence 1.0). 422 bei Container-Ziel; 409 bei Segment/Ganz-Playlist-Mischung (Invariante 3).

### `POST /api/v1/disc-playlists/{ulid}/segments`

Scope `write:catalog`, Rolle manager+. Ersetzt die Segmentierung atomar (`DefineDiscSegments`):

```json
{"segments": [
  {"start_ms": 0, "end_ms": 2592000, "segment_kind": "episode_body"},
  {"start_ms": 2592000, "end_ms": 5185000, "segment_kind": "episode_body"}
]}
```

Validierung: Nicht-Überlappung, Grenzen in `[0, duration_ms]`, keine bestätigten Segment-Mappings außerhalb der neuen Partition (sonst 409 `disc.segments_in_use` mit den betroffenen Mapping-IDs im `errors`-Detail — der Client muss erst remappen/rejecten). Bestehende Mappings auf unveränderten Segmenten (identische Grenzen) bleiben erhalten (Abgleich über Grenzen, nicht IDs).

### `POST /api/v1/disc-sets`, `PATCH /api/v1/disc-sets/{ulid}`, `POST /api/v1/disc-sets/{ulid}/confirm`

Scope `write:catalog`, Rolle manager+. Anlage (`{"name", "container_item_id"?, "disc_ids": [ordered]}`), Umbau (Name, Container, Reihenfolge als vollständige `disc_ids`-Liste — kein Einzel-Move-API; atomarer Ersatz wie bei Segmenten), Bestätigung (`ConfirmDiscSet`; löst Re-Klassifikation mit Set-Kontext aus, Regelkatalog N-04). 422 `disc.set_container_not_container`, wenn `container_item_id` konsumierbar ist.

### `POST /api/v1/disc-sessions/{ulid}/acknowledge`

Scope `write:catalog` (bewusst nicht `playback:report` — die Bestätigung ist eine Katalog-Entscheidung des Menschen, kein Player-Automatismus). Body: `{"media_item_ids": ["01J…"], "dismiss": false}`. Wirkung = `AcknowledgeManualPlayback` ([Playback-Übersetzung](playback-translation.md), `open_close_only`). `dismiss: true` mit leerer Liste verwirft die Karte auditiert.

## Player-Protokoll

Die drei Routen sind der normative Vertrag jeder Player-Integration (Konformitäts-Suite: [test-catalog.md](test-catalog.md), PB-Serie). Scope ausschließlich `playback:report`; alle anderen Scopes werden auf diesen Routen abgelehnt (403 auch für `admin` — Trennungs-Prinzip: Verwaltungs-Tokens sollen nie in Player-Configs landen).

### `POST /api/v1/playback/disc-sessions`

```json
Request:
{"session_key": "01J8ZP2Q…",              // Client-ULID, macht open idempotent
 "disc_ref": "sig:v1:9f42…"  |  "path_hint": "/mnt/media/…iso",
 "player_kind": "kodi", "player_instance": "wohnzimmer",
 "position_reporting": "playlist_position",
 "started_at": "2026-07-06T20:31:04Z"}

Response 201 (oder 200 bei session_key-Wiederholung):
{"id": "01J8ZP3A…", "disc_image_id": "01J8ZK3V…", "status": "active",
 "accepted_reporting": "playlist_position",
 "title_playlist_hints": {"1": "00003", "2": "00004"}}
```

`disc_ref` ist die signierte Öffnungs-Referenz (Format `sig:v1:<payload>.<mac>`, 15 min gültig, vom „Im Player öffnen"-Flow erzeugt); `path_hint` der Fallback für Fremdstarts (Auflösungssemantik in der [Playback-Übersetzung](playback-translation.md)). `accepted_reporting` kann den deklarierten Modus **herabstufen** (Integration ohne bestandene Konformität, External-Player-Fähigkeitsmatrix); der Player muss den akzeptierten Modus bedienen. `title_playlist_hints` wird mitgeliefert, damit Titel-melde-Player lokal auflösen können — reine Optimierung, der Server löst ohnehin auf.

### `POST /api/v1/playback/disc-sessions/{ulid}/events`

```json
Request:
{"events": [
  {"id": "01J8ZP4B…", "event_type": "playlist_change", "playlist_ref": "00004",
   "position_ms": 0, "occurred_at": "2026-07-06T20:32:10Z"},
  {"id": "01J8ZP4C…", "event_type": "position", "playlist_ref": "00004",
   "position_ms": 10250, "occurred_at": "2026-07-06T20:32:20Z"}
]}

Response 202:
{"accepted": 2, "duplicates": 0, "rejected": []}
```

Batch-Regeln: max. 100 Events; Duplikate (bekannte Event-ULIDs) zählen als `duplicates` und sind kein Fehler (Retry-Sicherheit); strukturell invalide Einzel-Events landen in `rejected: [{id, code}]`, der Rest wird angenommen (Teilannahme statt Alles-oder-Nichts — ein Player soll bei einem kaputten Event nicht den Batch verlieren). 409 `disc.session_not_active` bei `ended/discarded`-Sessions; `stale` wird durch einen Batch reaktiviert (`active`, Sweeper-Semantik). Rate-Limit 600 Events/min/Token.

### `POST /api/v1/playback/disc-sessions/{ulid}/end`

Body: `{"ended_at": "…"}`. Antwort 200 mit Session-Endzustand inkl. Anrechnung-Zusammenfassung (`credited: [{media_item_id, covered_pct}]`, `withheld: [{playlist_ref, note}]`) — der Player kann dem Benutzer direkt „E02 als gesehen markiert" anzeigen. Doppeltes `end` ist idempotent (200, identische Antwort).

## Fehlercode-Katalog (Namensraum `disc.*`)

| Code | Status | Route(n) | Bedeutung |
|---|---|---|---|
| `disc.analysis_running` | 409 | reanalyze | Analyse-Lauf aktiv |
| `disc.analysis_failed_state` | 409 | playlists, mappings | Struktur nie erfolgreich analysiert |
| `disc.mapping_conflict` | 409 | confirm, remap | Unique-Invariante 3 verletzt |
| `disc.mapping_not_confirmable` | 409 | confirm | falscher Ausgangsstatus |
| `disc.mapping_target_not_consumable` | 422 | confirm, remap | Invariante 4 |
| `disc.segments_in_use` | 409 | segments | bestätigte Mappings blockieren |
| `disc.segments_invalid_partition` | 422 | segments | Überlappung/Grenzen |
| `disc.set_container_not_container` | 422 | disc-sets | Container-Ziel konsumierbar |
| `disc.set_position_conflict` | 422 | disc-sets | doppelte/lückenhafte Reihenfolge |
| `disc.session_not_active` | 409 | events, end | Session beendet/verworfen |
| `disc.session_ref_invalid` | 422 | open | `disc_ref` abgelaufen/ungültig |
| `disc.reporting_unsupported` | 422 | open | deklarierter Modus unbekannt |
| `disc.event_batch_too_large` | 422 | events | > 100 Events |
| `disc.search_space_too_large` | 422 | (intern via reanalyze-Operation) | Mapper-Schutzgrenze |

Alle Codes erscheinen im Problem-Details-Feld `code` (Konventionen); die OpenAPI-Spezifikation bindet jeden Code an seine Routen (CI-geprüft).

## Ereignisse für externe Konsumenten

Die Engine-Events (`DiscImageAnalyzed`, `DiscMappingConfirmed`, `DiscPlaybackSessionEnded`) sind interne Events; extern sichtbar werden sie über die Rule Engine (`notify`-Kanal) — es gibt bewusst keinen Disc-spezifischen Webhook-Ausgang (offener Punkt der Konventionen gilt hier mit). Automatisierungen, die auf „neue Disc analysiert" reagieren wollen, pollen `GET /discs?analysis_status=analyzed&sort=-created_at` mit Cursor oder abonnieren eine Rule-Engine-Benachrichtigung.

## Kompatibilitäts-Zusagen

Innerhalb v1 (Konventionen): `mapping_summary`/`watch_summary`-Felder sind stabil; neue Klassifikations-Enumwerte können auftreten (Konsument muss unbekannte Werte tolerieren — dokumentierte Ausnahme von der Semantik-Stabilität, im OpenAPI-Schema als `x-extensible-enum` markiert); `evidence`-Inhalte sind **nicht** vertraglich (Diagnose-Daten, Schema-Version intern versioniert), wohl aber ihre Existenz. Das Player-Protokoll ist die härteste Zusage der Engine: Änderungen daran sind faktisch v2-Material, weil Player-Installationen in Wohnzimmern nicht mitwandern.
