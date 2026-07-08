# Audiobookshelf: API-Mapping-Referenz

Vertiefung zu [connectors/audiobookshelf.md](../audiobookshelf.md). Wire-Ebene für `AbsItemTranslator`/`AbsProgressTranslator`: exakte Endpunkte, Feldpfade, Zeitachsen-Formel und die Sidecar-Erkennung, die das Export-Pfad-Matching trägt.

## Versionsmatrix

| ABS-Version | Getestet | Bekannte Abweichungen |
|---|---|---|
| 2.3.x–2.7.x | ja (Fixture-Basis) | `media.metadata.asin` erst ab 2.4 zuverlässig befüllt |
| 2.8.x+ | ja | `listening-sessions`-Response um `deviceInfo` erweitert (ignoriert) |
| < 2.3 | nicht unterstützt | `Diagnostics` weist ab (`unsupported_version`, `GET /status` liefert die Versionskennung) |

## Authentifizierung

```
Header: Authorization: Bearer {user_token}
```

Ein Token pro `user_mappings[]`-Eintrag (Modulkapitel: ABS ist zwingend pro-User-Token, kein Admin-Durchgriff auf fremde Fortschritte). Token-Erzeugung erfolgt außerhalb des Connectors (ABS-eigener Login-Flow, `POST /login` mit Benutzer-Credentials); MediaForge speichert nur das Ergebnis-Token, nie das ABS-Passwort.

## Katalog-Ingest

```
GET /api/libraries/{libraryId}/items?limit=200&page={n}
    &include=media,libraryFiles
```

Antwort-Felder je Item (`libraryItem`-Objekt):

| ABS-Feld | Pfad | → Kanonisch | Anmerkung |
|---|---|---|---|
| `id` | root | `remote_ref` (`provider='abs_item'`) | installationsgebunden |
| `media.metadata.asin` | verschachtelt | `provider_ids`-Kandidat `audible_asin` | Stufe 3 der Matching-Kaskade |
| `media.metadata.isbn` | verschachtelt | `provider_ids`-Kandidat `isbn13` | dito |
| `libraryFiles[].metadata.path` | Array | Pfad-Normalisierung gegen `files.path` | Stufe 2 |
| `libraryFiles[].metadata.filename` | Array | Sidecar-Erkennung: Suche nach `mediaforge.json` im selben Verzeichnis | Stufe 1 (Export-Pfad-Treffer, s. u.) |
| `media.duration` | Sekunden-Float | `declared_total_s` (nur Plausibilisierung) | ×1000 → ms für den Vergleich mit `total_duration_ms` |

### Sidecar-Erkennung (Stufe 1, deterministisch)

Der Assembler-Export schreibt `mediaforge.json` neben die Export-Dateien ([Artefakt-Builder](../../modules/audiobook-assembler/artifact-builders.md)):

```json
{"schema": "mediaforge-export-sidecar/v1", "edition_id": "01J9AB…", "assembly_id": "01J9AC…"}
```

Der Ingest-Handler prüft, ob `libraryFiles[]` eine `mediaforge.json` im selben Ordner referenziert (ABS listet alle Dateien des Items, auch Nicht-Audio); Treffer liefert `edition_id` direkt — kein Heuristik-Risiko, echte Fremdschlüssel-Auflösung. Dies ist der einzige Connector-Mapping-Pfad im gesamten System ohne Konfidenzwert (Confidence konstant 1.0, `mapping_source='sidecar'`).

## Fortschritts-Ingest

```
GET /api/me/progress
```

Antwort: Array von `mediaProgress`-Objekten (eines je begonnenem Item, nicht paginiert — daher „billig", Modulkapitel Performance):

| ABS-Feld | Typ | → Kanonisch | Formel |
|---|---|---|---|
| `libraryItemId` | String | Bezug auf `remote_ref` | — |
| `currentTime` | Sekunden-Float | `position_ms` (Werkzeit) | `round(currentTime * 1000)` |
| `duration` | Sekunden-Float | Plausibilisierungs-Referenz | `duration*1000` vs. `total_duration_ms`, Toleranz 1 % (Modulkapitel) |
| `isFinished` | Bool | Fakt „gehört" | `occurred_at = finishedAt` |
| `finishedAt` | Epoch-ms oder `null` | `occurred_at`-Quelle | fehlt: Ingest-Zeit + `occurred_at_estimated=true` |
| `lastUpdate` | Epoch-ms | Fallback-`occurred_at` für Progress ohne `isFinished` | — |

Ergänzend, nur als `occurred_at`-Quelle (Modulkapitel: „Positionswahrheit ist `progress`"):

```
GET /api/me/listening-sessions?itemsPerPage=25
```

`session.updatedAt` verfeinert `occurred_at` bei mehreren Progress-Änderungen zwischen zwei Polls (die Session-Liste liefert Zeitstempel-Granularität, die `/me/progress` als reiner Endzustand nicht hat); Session-Inhalte selbst (`timeListening`, `duration`) fließen nicht in Watch-State.

## Fortschritts-Egress

```
PATCH /api/me/progress/{itemId}
Body: {"currentTime": <position_ms/1000>, "isFinished": <bool>}
```

Read-back: `GET /api/me/progress/{itemId}` unmittelbar danach, Hash über `(currentTime, isFinished)`. Beim Doppel-Bibliotheks-Paar (Modulkapitel) wird `itemId` des **Export**-Items adressiert, sofern das Paar konsolidiert ist; sonst das einzig gemappte.

## Export-Scan-Trigger

```
POST /api/libraries/{libraryId}/scan
```

Fire-and-forget (keine Response-Auswertung über den 200-Status hinaus); debounced 5 Minuten (Modulkapitel), ausgelöst vom `TriggerAbsScanListener` auf `AudiobookArtifactBuilt` (Export-Typ).

## Fehlerklassifikation (`AbsClient`)

| HTTP/Bedingung | Klasse | Health-Wirkung (pro User-Paar) |
|---|---|---|
| 401 | permanent | `auth_failed` (nur dieses Paar, Modulkapitel Fehlerisolation) |
| 404 auf `libraryId` | permanent | Instanz-Setting-Fehler, `degraded` |
| 429 | transient | Retry-After respektiert |
| 5xx/Timeout | transient | `unreachable` nach 3 Fehlversuchen |
| Duration-Mismatch > 1 % | kein Transport-Fehler | Review `connector_conflict` (Modulkapitel), kein Health-Einfluss |

## Fixture-Index

`tests/fixtures/connectors/abs/{version}/`: `items-with-sidecar.json`, `items-without-sidecar.json`, `progress-list.json` (inkl. `finishedAt=null`-Fall), `listening-sessions.json`, `status.json` je Version. Zeitachsen-Roundtrip-Golden-Test: MediaForge-Position → `currentTime`-Serialisierung → Ingest-Rückübersetzung ⇒ Abweichung ≤ 1 ms (Float-Rundung bei Sekundenauflösung ist die einzige Fehlerquelle, explizit toleriert und getestet).
