# API-Endpunkt-Gesamtkatalog

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Vertiefung zu [api/conventions.md](conventions.md). Konsolidierte Sicht auf **alle** `/api/v1`-Routen des Systems: die Modulkapitel definieren ihre Routen normativ (Payloads, Fehler, Semantik — hierhin wird nur verwiesen); dieser Katalog liefert, was kein Einzelkapitel zeigt: die Gesamtfläche mit Scope/Rolle/Muster je Route, die Konsumenten-Landkarte, die OpenAPI-Tag-Struktur und die Governance-Regeln für neue Routen. Er ist zugleich die Prüfliste des Contract-Tests „jede registrierte Route ist dokumentiert" (Konventionen): Eine Route, die hier fehlt, bricht den Build.

## Legende

**Scope** nach Konventions-Katalog (`read`, `write:catalog`, `write:operations`, `playback:report`, `admin`). **Muster**: L = Liste (Cursor, sofern nicht anders vermerkt), D = Detail, A = Action-POST (synchron), O = 202-Operation (asynchron, [Konventionen](conventions.md)), W = Webhook (signierter Pfad, tokenlos). **Rolle** = Mindestrolle des Token-Users zusätzlich zum Scope.

## Katalog- und Suchfläche

| Route | Muster | Scope | Rolle | Kapitel |
|---|---|---|---|---|
| `GET /search?q=&types=&library=&semantic=auto` | L | read | member | [search](../modules/search.md) |
| `GET /media/{ulid}/graph?depth=1` | D | read | member | [knowledge-graph](../modules/knowledge-graph.md) |
| `GET /items/{ulid}/enrichment` · `…/assets?slot=` | D/L | read | member | [enrichment](../modules/enrichment.md) |
| `POST /items/{ulid}/enrichment/refresh` | O | write:operations | manager | enrichment |
| `POST /items/{ulid}/fields/{field}/lock` · `…/unlock` | A | write:catalog | manager | enrichment |
| `POST /assets/{ulid}/select` | A | write:catalog | manager | enrichment |
| `GET /editions/{ulid}/processing-history` | L | read | member | [audio-upscaler](../modules/audio-upscaler.md) |

## Disc-Engine

| Route | Muster | Scope | Rolle |
|---|---|---|---|
| `GET /discs` (Filter s. [API-Referenz](../modules/disc-engine/api-reference.md)) · `GET /discs/{ulid}` · `…/playlists` | L/D/D | read | member |
| `GET /discs/{ulid}/raw-analysis` | D | read | manager |
| `GET /disc-sets` · `GET /disc-sets/{ulid}` | L (Offset erlaubt)/D | read | member |
| `POST /discs/{ulid}/reanalyze` | O | write:operations | manager |
| `POST /disc-mappings/{ulid}/confirm` · `…/reject` · `POST /disc-playlists/{ulid}/remap` · `…/segments` | A | write:catalog | manager |
| `POST /disc-sets` · `PATCH /disc-sets/{ulid}` · `POST …/confirm` | A | write:catalog | manager |
| `POST /discs/{ulid}/play?device=` | A | write:operations | member | 
| `GET /users/me/disc-sessions` · `POST /disc-sessions/{ulid}/acknowledge` | L/A | read / write:catalog | member |
| `POST /playback/disc-sessions` · `…/{ulid}/events` · `…/{ulid}/end` | A | **playback:report** (exklusiv) | member (Token-gebunden) |

## Hörbuch-Assembler

| Route | Muster | Scope | Rolle |
|---|---|---|---|
| `GET /audiobooks/{ulid}/assembly` | D | read | member |
| `POST …/assembly/sequence` · `…/collect-sources` · `…/ai-proposal` · `…/build?targets=` | O | write:operations | manager |
| `PUT …/assembly/sequence` · `PUT /chapter-sets/{ulid}/chapters` | A | write:catalog | manager |
| `POST /chapter-sets/{ulid}/activate` | A | write:catalog | manager |

## Audio-Upscaler und AI

| Route | Muster | Scope | Rolle |
|---|---|---|---|
| `GET /upscale/profiles` · `GET /upscale/runs/{ulid}` · `…/comparison` | L/D/D | read | member |
| `POST /editions/{ulid}/upscale` | O | write:operations | manager |
| `POST /upscale/runs/{ulid}/cancel` | A | write:operations | manager |
| `GET /ai/models` · `/ai/workers` · `/ai/availability?task=` | L | read | admin/admin/member | 
| `POST /ai/models` | A | admin | admin |

## Workflows, Regeln, Qualität, Dubletten

| Route | Muster | Scope | Rolle |
|---|---|---|---|
| `GET /workflow-definitions` · `GET /workflows?definition=&status=&subject=` · `GET /workflows/{ulid}` | L/L/D | read | member |
| `POST /workflows` · `POST /workflow-batches` · `POST /workflows/{ulid}/cancel` | O/O/A | write:operations | manager |
| `GET /rules` · `GET /rule-schema` · `GET /rules/{ulid}/firings` | L/D/L | read | manager |
| `POST /rules/{ulid}/pause` · `POST /rules/{ulid}/test` | A | admin | admin |
| `GET /quality/summary?library=` · `GET /quality/worklist?…` | D/L | read | manager |
| `GET /duplicates?level=&status=` · `GET /duplicates/{ulid}` | L/D | read | manager |

## Connectoren, Betrieb, Audit

| Route | Muster | Scope | Rolle |
|---|---|---|---|
| `GET /connectors` · `POST /connectors/{key}/instances` · `PUT /connector-instances/{ulid}` | L/A/A | read+admin / admin | admin |
| `POST /connector-instances/{ulid}/test` · `…/sync?stream=` · `GET …/activity` | A/O/L | admin / write:operations / read | admin/manager/manager |
| `POST /webhooks/{key}/{instanz-ulid}/{signatur}` | W | — (Signatur) | — |
| `GET /player-devices` · `POST /player-devices` | L/A | read / admin | member/admin |
| `GET /plugins` | L | read | admin |
| `GET /health` | D | — (öffentlich, minimal) bzw. read (Detail) | — |
| `GET /backups` · `POST /backups` | L/O | admin | admin |
| `GET /audit/operations?…` · `…/operations/{ulid}` · `…/subjects/{type}/{ulid}` | L/D/L | read | manager (Subjekt-Ansicht member für Eigenes) |
| `GET /operations/{ulid}` | D | read | member | 
| `GET /files/{ulid}/audio-analysis` | D | read | manager |
| `GET /acquisition/overview` · `GET /indexer*` | D/L | read | manager |

## Konsumenten-Landkarte

Wer nutzt was — die Begründungspflicht der Konventionen („keine Route ohne externen Anwendungsfall"), konsolidiert:

| Konsument | Genutzte Flächen |
|---|---|
| Kodi-Add-on / Player-Integrationen | Player-Protokoll (exklusiv `playback:report`), `discs`-Lesend, `disc-sessions`, `player-devices` |
| CLI-Automatisierung (Betreiber-Skripte) | Operations-Muster überall, Reanalysen, Builds, Workflows, Backups |
| Review-/Mobile-Clients (künftig) | Mapping-/Set-/Assembly-Mutationen, Review-nahe Lese-Routen |
| Monitoring (Prometheus-Scraper, Statuspages) | `GET /health` (minimal öffentlich), Audit-Aggregate |
| Fremdsysteme (ABS-nahe Tools, Skripte) | Assembly-Lesend, `processing-history`, Suche |
| Webhook-Sender (Jellyfin, ABS, Stash, Arr) | `POST /webhooks/…` (einzige tokenlose Fläche) |

## OpenAPI-Struktur

Tags = Tabellen-Abschnitte dieses Katalogs (`catalog`, `disc-engine`, `audiobook-assembler`, `upscaler-ai`, `workflows-rules`, `quality-duplicates`, `connectors-ops`, `audit`, `playback-protocol`); `operationId` = `{tag}.{verb}{Resource}` deterministisch aus den Controller-Attributen. Die generierte Spezifikation gruppiert exakt nach diesem Katalog — Katalog-Tabellen und OpenAPI-Tags driften nicht, weil beide aus denselben Attributen entstehen (der Katalog wird bei Abweichung vom Contract-Test als veraltet markiert: Route in OpenAPI ∖ Katalog ⇒ Buildfehler mit Verweis hierher).

## Querschnitts-Verhalten (gilt für jede Zeile oben)

Vollständig in den [Konventionen](conventions.md) normiert, hier nur die Erinnerungsliste: Problem-Details-Fehler mit `code` ([Fehlerkatalog](error-catalog.md)) · Cursor-Pagination außer dokumentierten Offset-Ausnahmen (disc-sets, rules, instances) · `Idempotency-Key` auf allen O-Routen · Rate-Limits 300/60/600 (Standard/Schreib/Playback) · ISO-8601-UTC + `_ms`-Ganzzahlen · ULIDs im Pfad · keine absoluten Pfade in Responses · 404 statt 403 für unsichtbare Existenz · Audit-Actor aus Token mit `channel='api'`.

## Governance neuer Routen

Aufnahmeprozess (PR-Checkliste): (1) benannter externer Konsument (Landkarten-Zeile), (2) Modulkapitel-Vertrag (Payload/Fehler/Semantik), (3) Katalog-Zeile hier, (4) Fehlercodes im [Fehlerkatalog](error-catalog.md), (5) Scope-Matrix-Test, (6) OpenAPI-Attribute. Ablehnungsgründe aus den Konventionen bleiben in Kraft: UI-Bedarf ist kein Grund (Inertia), Bulk ohne Nachweis nicht, GraphQL nie. Die Fläche ist mit ~90 Routen bewusst klein für ein System dieser Tiefe — der Katalog macht das Wachstum sichtbar und rechtfertigungspflichtig; als Richtwert gilt: Ein neues Modul bringt 3–8 Routen (lesend zuerst), nicht 20.
