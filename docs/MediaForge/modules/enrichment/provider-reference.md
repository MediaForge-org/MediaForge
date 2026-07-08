# Enrichment: Provider-Referenz

Vertiefung zu [modules/enrichment.md](../enrichment.md). Je unterstütztem Provider: Endpunkte und Auth, Feld-Mapping-Tabelle (Payload-Pfad → Katalogfeld → Feldklasse), Datenabfluss-Tabelle (was verlässt das Haus — der Vertrag des Egress-Contract-Tests), Rate-/Cache-Verhalten, Eigenheiten. Die Mapping-Tabellen sind die deklarative Quelle der `FieldMapperInterface`-Implementierungen; Golden-Fixtures je Provider liegen unter `tests/fixtures/provider-payloads/`.

## Übersicht und Default-Reihenfolgen

| Medientyp | Default `provider_order` |
|---|---|
| Film | `tmdb_movie` → `imdb` (Datensatz) → Connector-Ingest |
| Serie/Episode | `tvdb` → `tmdb_tv` → Connector-Ingest |
| Hörbuch | `audible_asin` (Audnexus-kompatibel) → `musicbrainz_release` → `openlibrary` → Connector-Ingest |
| Musik | `musicbrainz_release` → Connector-Ingest |
| Buch/eBook | `openlibrary` → `isbn13`-Registrierungen → Connector-Ingest |

Reihenfolgen sind je Bibliothek überschreibbar (Setting `enrichment.provider_order.<library>`); Connector-Ingest steht immer letztrangig (Zirkular-Schutz, Modulkapitel Edge Case). Personen (`people`) werden über die Item-Provider mitgeführt (TMDB/TVDB-Credits, Audible-Narrator) und über `provider_ids` auf Personenebene dedupliziert.

## TMDB (`tmdb_movie`, `tmdb_tv`)

**Transport/Auth:** REST v3, API-Key (Setting, verschlüsselt). Sprachparameter aus `lang_priority` (`language=de-DE`, Fallback-Kette per `include_image_language=de,null,en`).

**Endpunkte:** `movie/{id}` (+`credits,release_dates,images` via `append_to_response` — ein Request statt vier), `tv/{id}` (+`credits,images,content_ratings`), `tv/{id}/season/{n}` (Episodenlisten), `movie|tv/changes` (Delta-Verfahren des Scheduled Refresh: geänderte IDs seit Datum; der Refresh fragt Changes global und schneidet mit lokal gemappten IDs, statt jedes Item einzeln zu prüfen).

**Feld-Mapping (Auszug, normativ vollständig in der Mapper-Implementierung gespiegelt — CI prüft Tabelle ↔ Code):**

| Payload-Pfad | Katalogfeld | Klasse | Anmerkung |
|---|---|---|---|
| `title` / `name` | `title` | descriptive | sprachabhängig |
| `original_title`/`original_name` | `original_title` | descriptive | |
| `overview` | `summary` | descriptive | sprachabhängig |
| `genres[].name` | `genres` | descriptive | Vereinigungsfeld, Normalisierung s. u. |
| `runtime` (min) | `runtime_ms` | factual | ×60000; Plausibilitätsfenster 10 % |
| `release_date` | `released_on` | factual | |
| `release_dates` → DE-Zertifizierung | `certification` | descriptive | Fallback US mit Kennzeichnung |
| `credits.cast[]/crew[]` | Personen + Rollen | structural (Zuordnung), descriptive (Namen) | Personen-Upsert über `tmdb_person`-IDs |
| `seasons[]`/Episodenlisten | Serienstruktur | **structural** | Drift ⇒ Review; Episoden-Anker `tmdb_episode`-ID |
| `images.posters/backdrops` | Asset-Kandidaten | — | `vote_average` → `vote_metric`, `iso_639_1` → `lang` |
| `external_ids` (imdb, tvdb) | `provider_ids`-Ergänzung | — | Quelle `matcher`, unverifiziert — Querverweis-Gewinnung |

**Datenabfluss:** ausschließlich `{tmdb-ID, Endpunkt, language}`; kein Titel-Suchverkehr aus dem Enrichment (Suche ist Matcher-Territorium mit eigener Dokumentation). **Rate:** Legacy-Limit ~40 req/10 s als konservativer Default im Limiter (`enrichment.limits.tmdb`, anpassbar). **Eigenheiten:** Episoden-`runtime` fehlt häufig (Kandidat bleibt leer statt 0 — leere Werte nehmen an `first_wins` nicht teil); Changes-API liefert 14 Tage Fenster (Refresh-Frequenzen müssen darunter bleiben, sonst Vollabgleich).

## TVDB (`tvdb`)

**Transport/Auth:** v4-API, JWT über API-Key (Login-Endpoint, Token-Cache 24 h). **Endpunkte:** `series/{id}/extended`, `seasons/{id}/extended`, `episodes/{id}` (Episoden-Anker), `updates?since=` (Delta). **Mapping-Kernpunkte:** Serien-/Episodentitel und Übersichten sprachvariant über `translations`-Endpunkte (eigener Endpunkt je Sprache — der Spiegel hält je Sprache eine Payload-Zeile); `airsBeforeSeason`/`airsAfterSeason`-Specials-Platzierung wird als Anzeige-Hinweis übernommen, ordnet aber nie `sort_index` um (Specials-Ordnung ist lokal souverän — TVDB-Meinungen dazu ändern sich zu oft; die Disc-Engine hängt an stabilen Indizes). **Reorder-Realität:** TVDB ist der dokumentierte Hauptfall des `structural`-Drifts (Modulkapitel); der Mapper liefert Episoden mit stabilen TVDB-Episode-IDs, die Migrations-Option der Review nutzt exakt diese Anker. **Datenabfluss:** `{tvdb-ID, Endpunkt, Sprache}`. **Eigenheiten:** Genre-Vokabular weicht von TMDB ab (Normalisierung unten); `extended`-Antworten sind groß (Spiegel-TOAST); absolute Nummerierungen (Anime) kommen als Zusatzordnung und werden als `absolute_number`-Editionsattribut geführt, nie als `sort_index`.

## IMDb (`imdb` — Datensatz, kein API)

Kein Live-Lookup: IMDb bietet keine freie API; die wöchentlichen TSV-Datensätze (title.basics, title.ratings) können als **optionaler lokaler Import** geladen werden (`mediaforge:import-imdb-datasets`, Admin-CLI; Speicherbedarf dokumentiert ~2 GB). Der „Provider" ist dann eine lokale Tabelle; Mapping beschränkt sich auf `averageRating`/`numVotes` → `community_rating` (descriptive) und Titel-Validierung als Matcher-Signal. **Datenabfluss: keiner** (der Download des Datensatzes ist ein bewusster Admin-Akt). Ohne Import existiert der Provider schlicht nicht — `imdb`-IDs in `provider_ids` bleiben als Querverweise wertvoll (Jellyfin-Sync nutzt sie).

## Audnexus-kompatibel (`audible_asin`)

**Transport:** REST, Basis-URL konfigurierbar (Modulkapitel [Assembler](../audiobook-assembler.md): selbst hostbare Aggregator-Instanz möglich — der Betreiber entscheidet, wessen Server seine ASINs sieht). **Endpunkte:** `books/{asin}` (Metadaten), `books/{asin}/chapters` (Kapitel — konsumiert der Assembler-Collector, derselbe Spiegel), `authors/{asin}`. **Mapping:** `title`, `subtitle`, `summary` (descriptive); `authors[]`, `narrators[]` → Personen mit Rollen (Narrator ist die Hörbuch-Sonderrolle des Kernschemas); `seriesPrimary` → Serien-/Reihenzuordnung (**structural** — Reihenposition bewegt Anzeigeordnungen); `runtimeLengthMin` → factual mit Fenster; `releaseDate`, `publisherName`, `isbn` (Querverweis-Gewinnung). **Datenabfluss:** `{ASIN}` — die Assembler-Security-Regel (nie Dateiinhalte) gilt wortgleich. **Eigenheiten:** Regionsvarianten (`.de`-ASINs) liefern deutsche Metadaten direkt; `isAccurate`-Semantik der Kapitel behandelt die [Kapitelquellen-Referenz](../audiobook-assembler/chapter-source-formats.md).

## MusicBrainz (`musicbrainz_release`, `musicbrainz_artist`)

**Transport:** REST, **Pflicht-User-Agent** mit Kontakt (MusicBrainz-Etikette; Setting `enrichment.musicbrainz.contact`, ohne den bleibt der Provider deaktiviert — die Höflichkeitsregel ist als Konfigurationszwang implementiert), Rate-Limit hart 1 req/s im Limiter. **Endpunkte:** `release/{mbid}?inc=recordings+artist-credits+labels`, `release-group/{mbid}`. **Mapping:** Release-Titel/Datum/Label (descriptive/factual); Track-Struktur → Editions-Trackliste (structural; für Musik-Editionen und als Assembler-Validierungssignal); Artist-Credits → Personen. **Datenabfluss:** `{MBID}`. **Eigenheiten:** Cover-Art über das Cover Art Archive (`coverartarchive.org/release/{mbid}`) als Asset-Quelle; Hörbuch-Abdeckung lückenhaft (Assembler-Analyse) — der Provider steht bei Hörbüchern bewusst hinter Audible.

## OpenLibrary (`openlibrary`, `isbn13`)

**Transport:** REST ohne Auth. **Endpunkte:** `isbn/{isbn}.json`, `works/{olid}.json`, `authors/{olid}.json`. **Mapping:** Titel/Untertitel/Beschreibung (descriptive), Seitenzahl/Erscheinungsjahr (factual), Autoren (Personen), Cover über `covers.openlibrary.org` (Asset-Kandidaten), ISBN-Familien (Werk ↔ Ausgaben — Editions-Querverweise). **Datenabfluss:** `{ISBN/OLID}`. **Eigenheiten:** Datenqualität streut stark; OpenLibrary steht deshalb nie erstrangig, und seine Kandidaten tragen `qualität=0.7` (die Merge-Engine nutzt Qualität als Tie-Breaker innerhalb der Reihenfolge, nicht als Umordnung).

## Connector-Ingest als Pseudo-Provider

Jellyfin/ABS/Stash-Ingest-Felder erscheinen der Merge-Engine als letztrangiger Provider (`connector:<name>`), Quelle der Kandidaten sind die Sync-Snapshots der Connectoren (deren Kapitel). Zwei Sonderregeln: Ingest gewinnt nur gegen **leere** Felder (strengere Form von `first_wins` — Ingest ist Bootstrap-Hilfe, keine Metadaten-Autorität), und Ingest-Kandidaten erzeugen nie Drift-Reviews (ihre Änderungen sind Sync-Alltag). Die Governance-Frage „schreibt MediaForge zurück?" bleibt per Connector-SDK-Capability deaktiviert (Modulkapitel, offener Punkt).

## Genre-/Vokabular-Normalisierung

Zentrale Tabelle (`enrichment.genre_map`, Seed + admin-erweiterbar): Provider-Vokabel → kanonisches Genre (`Science-Fiction`, `Sci-Fi & Fantasy` (TMDB-TV) → `Science-Fiction`; `Kids` → `Kinder`; …). Unbekannte Vokabeln passieren unverändert und erscheinen im Datenqualitätsmodul als Normalisierungs-Kandidaten (Modulkapitel Edge Case). Die Tabelle ist bewusst instanzlokal editierbar — Genre-Geschmack ist Betreiber-Sache; der Seed liefert die überschneidungsfreie Basis der Kern-Provider.

## Datenabfluss-Gesamttabelle (Egress-Contract)

| Provider | Gesendet wird | Nie gesendet |
|---|---|---|
| TMDB | ID, Endpunkt, Sprache | Suchbegriffe*, Pfade, Bestandsdaten |
| TVDB | ID, Endpunkt, Sprache | dito |
| Audnexus-Endpunkt | ASIN | Dateiinhalte, Laufzeiten, Pfade |
| MusicBrainz | MBID, User-Agent-Kontakt | dito |
| OpenLibrary | ISBN/OLID | dito |
| Cover-CDNs | Asset-URL des Providers | Referer/Cookies (Downloads laufen headerarm) |
| IMDb | — (lokaler Datensatz) | alles |

\* Matcher-Suchverkehr (Titel-Queries beim Erstmatch) ist ein dokumentierter, getrennter Abfluss des Matchers mit eigenem Schalter — Enrichment-Läufe selbst senden nie Freitext. Der Egress-Contract-Test (Modulkapitel Tests) verifiziert diese Tabelle wörtlich: Jede nicht gelistete URL-Form im HTTP-Fake lässt die Suite scheitern.

## Cache- und Spiegel-Politik (Zusammenfassung je Provider)

| Provider | Delta-Verfahren | ETag | Default-Refresh (laufend/aktiv/Bestand) |
|---|---|---|---|
| TMDB | Changes-API | ja | 7/30/90 Tage |
| TVDB | `updates?since=` | nein (Token-Header) | 7/30/90 |
| Audnexus | — | ja | —/30/180 (Hörbuch-Metadaten driften kaum) |
| MusicBrainz | — | ja | —/90/365 |
| OpenLibrary | — | ja | —/90/365 |

„laufend" betrifft nur Serien mit `status='continuing'` (TMDB/TVDB). Alle Frequenzen Settings (`enrichment.refresh.*`); der Scheduler respektiert zusätzlich die globale Nachtfenster-Einstellung des Betriebsprofils ([deployment.md](../../architecture/deployment.md)).

## Neue Provider (Checkliste)

Ein Provider-Zugang entsteht als Plugin oder Kernbeitrag mit: (1) `ProviderClientInterface`-Implementierung mit Limiter/ETag, (2) deklarativer Mapping-Tabelle in diesem Dokumentformat, (3) Feldklassen-Zuordnung jedes gemappten Felds, (4) Datenabfluss-Zeile + Egress-Test-Erweiterung, (5) Payload-Fixtures + Golden-Mapper-Tests, (6) Lizenz-/ToS-Vermerk (kommerzielle Nutzungsgrenzen, Attribution — TMDB verlangt Attribution im UI: der Footer der Item-Detailseite rendert die Attributions aller an diesem Item beteiligten Provider automatisch aus der Provider-Registry). Ohne alle sechs Punkte kein Merge-Zugang — die Checkliste ist die Aufnahmebedingung, geprüft im PR-Template ([developer-handbook](../../developer-handbook/getting-started.md)).
