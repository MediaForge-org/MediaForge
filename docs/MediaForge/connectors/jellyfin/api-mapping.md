# Jellyfin: API-Mapping-Referenz

Vertiefung zu [connectors/jellyfin.md](../jellyfin.md). Normativ für die Wire-Ebene: exakte Endpunkte, Feldpfade, Übersetzungsformeln und Versionsmatrix. Das Modulkapitel definiert Semantik und Entscheidungen (Container-Verwerfung, Disc-Umleitung); dieses Dokument definiert, welches Byte wohin fließt — die Grundlage der Übersetzer-Golden-Tests (`JellyfinItemTranslator`, `JellyfinUserDataTranslator`).

## Versionsmatrix

| Jellyfin-Version | API-Pfad-Präfix | Getestet | Bekannte Abweichungen |
|---|---|---|---|
| 10.8.x | `/emby` und `/` (Alias) | ja (Fixture-Basis) | `UserData.LastPlayedDate` gelegentlich `null` bei Migrationsbeständen |
| 10.9.x | `/` | ja | `ProviderIds`-Schlüssel-Casing stabilisiert (`Tmdb` statt `TMDb`) |
| 10.10.x | `/` | ja | Sessions-API liefert zusätzlich `PlayState.RepeatMode` (ignoriert) |
| < 10.8 | — | nicht unterstützt | `Diagnostics` weist die Instanz mit `unsupported_version` ab |

Der Client sendet `X-Emby-Client`/`X-Emby-Version`-Header (eigene Identifikation, kein Vortäuschen eines bekannten Clients); die Versionsprüfung liest `GET /System/Info` (`Version`-Feld, SemVer-Vergleich) beim Diagnostics-Lauf.

## Authentifizierung

```
Header: X-Emby-Token: {api_key}
```

Kein OAuth-Flow, kein User-Login: Der Connector nutzt ausschließlich einen Admin-API-Key (Jellyfin-Dashboard → API-Keys) mit explizitem `userId`-Query-Parameter für benutzergebundene Endpunkte — nie gespeicherte User-Passwörter (Modulkapitel-Festlegung). Rechteprüfung beim Diagnostics-Lauf: `GET /Users` muss die gemappten `jellyfin_user_id`-Werte enthalten, sonst `insufficient_key_rights`.

## Katalog-Ingest: Endpunkte und Felder

```
GET /Items?ParentId={libraryId}&IncludeItemTypes=Movie,Series,Season,Episode
    &Fields=ProviderIds,Path,Overview,PremiereDate,RunTimeTicks
    &Recursive=true&StartIndex={n}&Limit=500
```

Antwort-Feld → kanonisches Feld:

| Jellyfin-Feld | Typ | → Kanonisch | Anmerkung |
|---|---|---|---|
| `Id` | GUID-String | `remote_ref` (⇒ `provider_ids.external_id`, `provider='jellyfin_item'`) | installationsgebunden, s. Rebuild-Edge-Case |
| `Type` | Enum | Klassifikationshinweis (Movie→movie, Series→show, Season→season, Episode→episode) | steuert nur die Matching-Kaskaden-Filterung, erzeugt nie selbst Katalogtypen |
| `ProviderIds.Tmdb` / `.Tvdb` / `.Imdb` | String | `provider_ids`-Kandidaten (`tmdb_movie`/`tmdb_tv` je `Type`, `tvdb`, `imdb`) | Stufe 1 der Matching-Kaskade |
| `Path` | String (Server-absolut) | Pfad-Normalisierung gegen `files.path` | Stufe 2; Normalisierung: Backslash→Slash, Laufwerksbuchstaben-Strip bei Windows-Jellyfin-Hosts (konfigurierbares Mapping-Präfix wie beim External-Player-Konzept) |
| `IndexNumber` / `ParentIndexNumber` | Integer | Episode-/Staffelnummer (nur Anzeige-Abgleich, nie autoritativ) | MediaForge-`sort_index` bleibt lokale Anzeige-/Sortierentscheidung |
| `RunTimeTicks` | Int64 (100 ns) | `runtime_ms` (nur Plausibilitäts-Vergleich) | `÷ 10_000` |
| `Overview`, `PremiereDate` | String/Date | nicht übernommen (`egressCatalog=false`, Ingest ist reines Mapping-Signal, kein Enrichment-Ersatz) | Feldhoheit liegt bei der Enrichment-Governance |

Pagination: `StartIndex`/`Limit`, harte Obergrenze `Limit=500` (größere Werte werden von manchen Jellyfin-Versionen stillschweigend gekappt — der Client paginiert unabhängig von der Server-Kappung anhand `TotalRecordCount`).

## Playstate-Ingest: Endpunkte und Felder

```
GET /Users/{jellyfinUserId}/Items?ParentId={libraryId}&Fields=UserData,ProviderIds
    &Recursive=true&Filters=IsPlayed,IsResumable&StartIndex={n}&Limit=500
```

`Filters=IsPlayed,IsResumable` ist der Vorfilter (Modulkapitel „Performance"): Er liefert nur Items mit nichttrivialem `UserData`, nicht den vollen Bestand. `UserData`-Unterfelder:

| Jellyfin-Feld | Typ | → Kanonisch | Formel |
|---|---|---|---|
| `PlaybackPositionTicks` | Int64 (100 ns) | `position_ms` | `÷ 10_000` |
| `Played` | Bool | Fakt „gesehen" | `true` ⇒ `MarkWatched`-Kandidat, Fundament-Schwelle entscheidet nicht mit (expliziter Fakt) |
| `PlayCount` | Integer | `play_count`-Untergrenze | MediaForge `play_count = max(lokal, remote)` — monoton, nie gesenkt |
| `LastPlayedDate` | ISO-8601 oder fehlend | `occurred_at` | fehlt das Feld: `recorded_at` der Ingest-Verarbeitung, Kennzeichnung `occurred_at_estimated=true` im `context`-JSONB |
| `IsFavorite` | Bool | nicht übernommen | kein MediaForge-Konzept; künftig ggf. Tag-Brücke (offener Punkt) |

Für Container-Items (`Type=Series`/`Season`) wird `UserData.Played` gelesen, aber **verworfen** (Modulkapitel): Der Ingest-Handler filtert `Type ∈ {Series, Season}` vor der Fakten-Erzeugung heraus — Jellyfin kaskadiert `Played` ohnehin auf die Episode-`UserData`, die separat ankommt.

## Playstate-Egress: Endpunkte

```
POST /Users/{jellyfinUserId}/PlayedItems/{itemId}          -- Played=true setzen
DELETE /Users/{jellyfinUserId}/PlayedItems/{itemId}        -- Played=false setzen
POST /Users/{jellyfinUserId}/PlayingItems/{itemId}/Progress
     Body: {"PositionTicks": <ms*10000>, "IsPaused": true}
```

Read-back (Echo-Hash-Grundlage, SDK-Muster): unmittelbar nach dem Schreiben `GET /Users/{userId}/Items/{itemId}?Fields=UserData`, Hash über `(PlaybackPositionTicks, Played, PlayCount)`. Scheitert der Read-back (Netzwerk), bleibt das Outbox-Item `in_flight` bis zum nächsten Versuch (Fehlerklassifikation: Timeout/5xx → transient).

## Sessions-Endpunkt (optional, Live-Anzeige)

```
GET /Sessions
```

Liefert aktive Wiedergaben (`NowPlayingItem`, `PlayState.PositionTicks`). Aktuell nicht konsumiert (Modulkapitel, offener Punkt „Live-Sessions") — als Wire-Referenz dokumentiert, falls die Funktion spezifiziert wird: `PlayState.PositionTicks` folgt derselben Ticks-Formel, `NowPlayingItem.Id` demselben `remote_ref`-Schema.

## Webhook-Payload (Webhook-Plugin)

```json
{"NotificationType": "PlaybackStop", "ItemId": "…", "UserId": "…",
 "PlaybackPositionTicks": 12345670000, "Played": false}
```

Wird **nur** als Trigger konsumiert (SDK-Regel): Der Webhook-Handler extrahiert `ItemId`+`UserId`, prüft ob beide gemappt sind, und stößt einen sofortigen Ziel-Poll (`GET /Users/{userId}/Items/{itemId}?Fields=UserData`) an — der Payload-Inhalt selbst fließt nie direkt in eine Action (Modulkapitel: Webhook-Semantik ist verkürzt gegenüber der Abfrage-API).

## Fehlerklassifikation (`JellyfinClient`)

| HTTP/Bedingung | Klasse | Health-Wirkung |
|---|---|---|
| 401 | permanent | `auth_failed` |
| 403 (Key ohne Rechte) | permanent | `auth_failed` mit Detail |
| 404 auf gemapptes Item | permanent (für diesen Datensatz) | Mapping wird `orphaned` markiert (Rebuild-Edge-Case) |
| 429 | transient | Retry-After respektiert |
| 5xx / Timeout / Connection Refused | transient | `unreachable` nach 3 Fehlversuchen in Folge |
| Version < 10.8 | permanent | `unsupported_version` |

## Fixture-Index

`tests/fixtures/connectors/jellyfin/{version}/` mit: `items-list.json` (gemischte Typen inkl. Disc-ISO-Item), `userdata-list.json` (inkl. `LastPlayedDate`-fehlend-Fall), `webhook-playbackstop.json`, `system-info.json` je Version. Übersetzer-Golden-Tests laufen gegen alle drei Versionsordner identisch (Bijektivitäts-Nachweis über Versionsgrenzen).
