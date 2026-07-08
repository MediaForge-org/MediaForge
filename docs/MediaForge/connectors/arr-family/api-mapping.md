# *arr-Familie: API-Mapping-Referenz

Vertiefung zu [connectors/arr-family.md](../arr-family.md). Wire-Ebene für die vier `ArrEntityTranslatorBase`-Ableitungen: exakte v3-Endpunkte, Feldpfade je System, Provider-Schlüssel-Extraktion und die `history?since`-Cursor-Mechanik — das einzige echte Cursor-Interface unter den Connectoren.

## Versionsmatrix

| System | API-Version | Getestet | Bekannte Abweichungen |
|---|---|---|---|
| Sonarr | v3 | ja | `series.statistics.episodeFileCount` erst ab 3.0.6 |
| Radarr | v3 | ja | `movie.movieFile` kann bei importierten Altbeständen fehlen |
| Readarr | v1 (kein v3-Sprung vollzogen) | ja | Provider-Feld-Lage volatil (Modulkapitel „Metadaten-Instabilität") |
| Lidarr | v1 | ja | `artist.foreignArtistId` = MusicBrainz-UUID direkt (kein Wrapper-Objekt) |

Diagnostics liest `GET /api/v3/system/status` (Sonarr/Radarr) bzw. `GET /api/v1/system/status` (Readarr/Lidarr) — `ArrClientBase` wählt den Pfadpräfix aus dem Manifest, nicht aus der Laufzeit-Erkennung (jedes System deklariert seine API-Version statisch).

## Authentifizierung

```
Header: X-Api-Key: {api_key}
```

Identisch über alle vier Systeme (Familien-Konstante); der API-Key ist im jeweiligen System unter Settings → General sichtbar.

## Bestands-Endpunkte (`catalog`-Ingest)

| System | Endpunkt | Provider-Feld(er) | → `provider_ids` |
|---|---|---|---|
| Sonarr | `GET /api/v3/series` | `tvdbId` (Integer) | `tvdb` |
| Sonarr | `GET /api/v3/episode?seriesId={id}` | `tvdbId`, `seasonNumber`, `episodeNumber` | `tvdb` (episodengenau) |
| Radarr | `GET /api/v3/movie` | `tmdbId` (Integer), `imdbId` (String `tt…`) | `tmdb_movie`, `imdb` |
| Readarr | `GET /api/v1/author` → `GET /api/v1/book?authorId={id}` | `foreignBookId` (Goodreads-Edition), `editions[].isbn13` | `goodreads`, `isbn13` |
| Lidarr | `GET /api/v1/artist` → `GET /api/v1/album?artistId={id}` | `foreignArtistId`/`foreignAlbumId` (MusicBrainz-UUID direkt) | `musicbrainz_artist`, `musicbrainz_release_group` |

Gemeinsame Zusatzfelder je Entität: `monitored` (Bool → `presence`-Signal, Modulkapitel), `path` (Pfad-Normalisierung, sekundäre Matching-Stufe), `rootFolderPath` (nur Diagnose).

### Monitoring-Granularität (Sonarr-Sonderfall)

Sonarr kennt Monitoring auf drei Ebenen: Serie (`series.monitored`), Staffel (`series.seasons[].monitored`), Episode (`episode.monitored`). Der Ingest-Handler übernimmt die **feinste verfügbare** Ebene als `presence`-Signal: eine Episode mit `monitored=true` in einer Serie mit `monitored=false` (Sonarr erlaubt diese Inkonsistenz) erzeugt trotzdem den `wanted`-Vorschlag für genau diese Episode. Radarr/Readarr/Lidarr kennen nur Werk-Ebene.

## Queue-Endpunkt

```
GET /api/v3/queue?includeUnknownSeriesItems=true&pageSize=200
```

(Readarr/Lidarr: `/api/v1/queue`, analoge Struktur.) Felder → `arr_queue_snapshot`:

| Feld | → Spalte |
|---|---|
| `id` | `remote_ref` |
| `title` | `title` |
| `status` (`downloading`/`queued`/`completed`/…) | `status` (Enum-Mapping 1:1, unbekannte Werte durchgereicht) |
| `size` / `sizeleft` | `size_bytes` (`size`) |
| `estimatedCompletionTime` | `eta` |
| `seriesId`/`movieId`/`authorId`/`artistId` | Auflösung gegen `provider_ids` → `media_item_id` |

Jeder Lauf ersetzt den Instanz-Bestand vollständig (Modulkapitel: Delete+Insert-Transaktion) — die Queue-API selbst liefert keinen Cursor, nur den aktuellen Stand.

## History-Endpunkt (echter Cursor)

```
GET /api/v3/history/since?date={ISO8601}&eventType=downloadFolderImported,grabbed,downloadFailed
```

Dies ist der einzige Connector-Endpunkt im gesamten System mit echtem serverseitigem Zeitfilter (kein Zustandsvergleich nötig, anders als Jellyfin/ABS). Antwort-Felder je Event:

| Feld | → Kanonisch | Wirkung |
|---|---|---|
| `date` | `occurred_at` | Cursor-Fortschritt (`connector_sync_states.cursor = {"since": date}` des letzten Events) |
| `eventType` | Ereignistyp | `downloadFolderImported` ⇒ `ScanPathJob`-Dispatch (s. u.) |
| `data.droppedPath` / `data.importedPath` | Zielpfad | Scope des gezielten Scans |
| `seriesId`/`movieId`/… + `episodeId` (Sonarr) | Bezug | Aktivitäts-Sicht-Verknüpfung |

`downloadFolderImported` → `ScanPathJob`: Der Handler extrahiert `data.importedPath` (Readarr/Lidarr: analoges Feld unter leicht anderem Namen, `ArrEntityTranslatorBase` normalisiert), mappt ihn über die Instanz-Pfad-Konfiguration auf den MediaForge-Bibliothekspfad und dispatcht `ScanPathJob` mit diesem Scope — die Antwort auf die Import-Latenz (Modulkapitel).

## Health-Endpunkt

```
GET /api/v3/health
```

Array von `{source, type: "error"|"warning", message}`. Jede `error`-Zeile setzt die Connector-Instanz auf `degraded` mit `message` als `health_detail`; `warning` wird nur in der Aktivitäts-Sicht angezeigt, ohne Health-Statusänderung.

## Egress-Endpunkte (hart begrenzt, Modulkapitel)

```
PUT /api/v3/series/{id}          Body: {..., "monitored": true}   (ganze Ressource, gelesen-verändert-zurückgeschrieben)
PUT /api/v3/episode/{id}         Body: {..., "monitored": true}   (Sonarr episodengenau)
POST /api/v3/command             Body: {"name": "SeriesSearch", "seriesId": {id}}
                                  (Radarr: "MoviesSearch"/"MovieSearch"; Readarr: "BookSearch"; Lidarr: "AlbumSearch")
```

`PUT` erfordert Read-Modify-Write (die *arr-APIs verlangen die vollständige Ressource, kein PATCH) — der Client liest vor jedem Egress-Schreiben die aktuelle Ressource, ändert nur `monitored`, schreibt zurück; das schließt aus, dass der Connector versehentlich andere Felder (Qualitätsprofil, Root-Pfad) verändert, weil er sie unverändert zurücksendet. Read-back-Verifikation: erneutes `GET` nach dem `PUT`, Hash über `monitored`.

## Fehlerklassifikation (`ArrClientBase`)

| HTTP/Bedingung | Klasse | Health-Wirkung |
|---|---|---|
| 401 | permanent | `auth_failed` |
| 404 auf `history/since` (API-Version-Mismatch) | permanent | `degraded`, Hinweis auf v4/v5-Inkompatibilität (Modulkapitel Edge Case) |
| 429 | transient | selten (kein dokumentiertes Rate-Limiting der *arr-Familie), dennoch respektiert |
| 5xx/Timeout | transient | `unreachable` nach 3 Fehlversuchen |
| Command-Queue voll (`400` mit spezifischer Meldung) | transient | Egress-Retry mit Backoff, kein Health-Einfluss |

## Fixture-Index

`tests/fixtures/connectors/arr/{system}/`: `series-list.json`/`movie-list.json`/`book-list.json`/`album-list.json`, `history-since.json` (inkl. aller drei `eventType`-Werte), `queue.json`, `health.json` je System. Familien-Contract-Test: derselbe abstrakte Testfall (`assertCatalogIngestMapsProviderIds`) läuft parametrisiert gegen alle vier Fixture-Sätze — eine Testformulierung, vier Ausführungen, Abweichungen fallen als Parametrisierungs-Diff auf statt als vier gepflegte Kopien.
