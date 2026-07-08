# Sonarr/Radarr/Readarr/Lidarr-Connectoren

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [connectors/connector-sdk.md](connector-sdk.md). Die vier Connectoren werden gemeinsam spezifiziert, weil die *arr-Familie eine gemeinsame API-Architektur teilt; Abweichungen sind pro System ausgewiesen.

**Vertiefung**: [API-Mapping-Referenz](arr-family/api-mapping.md) (Wire-Ebene je System, `history?since`-Cursor-Mechanik)

## Motivation

Die *arr-Familie bleibt das Beschaffungs-Backend (Masterdatei, Nicht-Ziele: MediaForge lädt nichts, indiziert nichts). Was fehlt und was diese Connectoren liefern: die **familienübergreifende Gesamtsicht** in MediaForge — was ist gewünscht (`presence='wanted'`), was ist unterwegs (Queue), was kam an (History → Scan-Anstoß), wo hakt es (Health) — plus die Rückrichtung: MediaForge-Wünsche („diese Lücke schließen") als Monitoring-Aufträge an das zuständige *arr. Die vier Silos behalten ihre Arbeit; MediaForge wird die Klammer, die dem Betreiber vier Kalender, vier Queues und vier Wanted-Listen erspart.

## Problemstellung

**Vier fast gleiche APIs.** Sonarr (Serien, TVDB-zentriert), Radarr (Filme, TMDB), Readarr (Bücher/Hörbücher, Goodreads/ISBN — mit notorisch instabilerer Metadatenlage), Lidarr (Musik, MusicBrainz) teilen API-Stil (`/api/v3`, API-Key-Header, gleiche Ressourcen-Muster: series/movie/author/artist, queue, history, wantedmissing, rootfolder, qualityprofile), unterscheiden sich aber in Entitätsmodellen und Provider-Schlüsseln. Der Connector-Code muss die Gemeinsamkeit teilen, ohne die Unterschiede zu verschmieren.

**Zustands-Hoheit.** `presence` im MediaForge-Katalog ist Katalog-Referenzstatus; *arr-Monitoring ist Beschaffungsstatus. Die beiden dürfen sich informieren, aber nicht blind überschreiben: Ein in Sonarr entferntes Monitoring macht eine Episode nicht „unerwünscht", wenn der MediaForge-Benutzer sie explizit will — Konfliktfälle sind Reviews, keine stillen Sieger (SDK-Strategie greift).

**Import-Latenz.** Wenn Radarr importiert, soll MediaForge die Datei schnell sehen (Scan-Anstoß auf den Zielpfad), statt auf den nächsten Bibliotheks-Vollscan zu warten.

## Analyse der Gegenstellen

Gemeinsame API-Flächen (v3, alle vier): `GET /api/v3/{entity}` (Bestand mit Provider-IDs und Pfaden), `GET /queue` (aktive Downloads mit Status/ETA), `GET /history?since=` (das einzige echte Cursor-Interface der Familie — `date`-basiert), `GET /wanted/missing` (Lücken), `GET /health` (Systemwarnungen), `POST /command` (RescanSeries etc.), Monitoring-Mutationen (`PUT /{entity}` mit `monitored`-Flag; Sonarr zusätzlich pro Episode/Season). Webhooks: alle vier bieten „Connect"-Webhooks (On Import, On Grab, On Health) — nach SDK-Regel als Trigger genutzt. Provider-Schlüssel je System: Sonarr `tvdb`, Radarr `tmdb_movie`+`imdb`, Readarr `goodreads`/`isbn13`/`asin`, Lidarr `musicbrainz_artist`/`musicbrainz_release_group` — alle bereits im Provider-Namensraum des Kernschemas vorgesehen.

## Manifest (gemeinsame Basis, vier Ausprägungen)

```php
abstract class ArrManifestBase implements ConnectorManifest
{
    public function capabilities(): CapabilitySet
    {
        return new CapabilitySet(
            ingestPlayState: false, egressPlayState: false,   // *arr kennt kein Playback
            ingestCatalog: true,                              // Bestand + Monitoring + Queue/History
            egressCatalog: true,                              // NUR Monitoring-Flags (s. Egress)
            supportsWebhooks: true,
            supportsCursorSync: true,                         // history?since
            rateLimit: new RateLimit(10, perSeconds: 1),
        );
    }
}
// SonarrManifest: key 'sonarr', providerKeys ['sonarr_series','sonarr_episode','tvdb']
// RadarrManifest: key 'radarr', providerKeys ['radarr_movie','tmdb_movie','imdb'] … usw.
```

`egressCatalog=true` ist hier bewusst enger als die SDK-offene Governance-Frage: Der Egress dieser Connectoren schreibt **ausschließlich Monitoring-Flags und Suchkommandos**, nie Metadaten — das ist im Handler hart kodiert (nicht konfigurierbar) und umgeht die offene Metadaten-Governance des SDK nicht, weil Monitoring Beschaffungssteuerung ist, keine Katalogwahrheit.

## Sync-Ströme

**`catalog`-Ingest** (Intervall 6 h + Webhook-Trigger): Bestand lesen, `provider_ids` pflegen (Doppel-Brücke: `sonarr_series` ↔ MediaForge-Show via `tvdb` — die *arr-eigenen Provider-IDs machen das Matching fast immer eindeutig; Rest → SDK-Kaskade). Monitoring-Status wird als Katalog-Signal übernommen: `monitored=true` ohne Datei ⇒ Vorschlag `presence='wanted'` (Action mit Konfliktprüfung — steht MediaForge auf `absent` durch Benutzerhand, entsteht ein `connector_conflict`-Review statt Überschreiben).

**`activity`-Ingest** (Cursor über `history?since`, Intervall 5 min): Grab/Import/Fail-Ereignisse. Import-Ereignisse dispatchen einen gezielten Pfad-Scan (`ScanPathJob` auf den Import-Ordner — die Antwort auf die Import-Latenz); alle Ereignisse speisen die Aktivitäts-Sicht (unten). Queue-Snapshots (aktive Downloads) werden bei jedem Lauf als Ganzes gespiegelt (`arr_queue_snapshot`, flüchtige Tabelle, kein Audit — reine Anzeige).

**`health`-Ingest**: *arr-Health-Warnungen → Connector-Instanz-Health (`degraded` mit Detail) → [Health-Monitoring](../modules/health-monitoring.md).

**Egress**: Outbox-Items entstehen aus zwei Quellen: `presence`-Wechsel auf `wanted` (Katalog-Action) ⇒ Monitoring setzen + optional `POST /command` Suche (Setting `auto_search`, Default false); Workflow-/Regel-Aktionen (`dispatch_job` whitelisted: `RequestArrSearch`). Idempotent per Read-back des Monitoring-Flags.

## Datenmodell

Nur eine connector-eigene Tabelle über den SDK-Bestand hinaus:

```sql
CREATE TABLE arr_queue_snapshot (
    id            CHAR(26) PRIMARY KEY,
    instance_id   CHAR(26)    NOT NULL REFERENCES connector_instances(id) ON DELETE CASCADE,
    remote_ref    TEXT        NOT NULL,            -- *arr-Queue-Item-ID
    media_item_id CHAR(26)    REFERENCES media_items(id) ON DELETE SET NULL,
    title         TEXT        NOT NULL,
    status        TEXT        NOT NULL,            -- downloading|queued|importing|failed|…
    size_bytes    BIGINT,
    eta           TIMESTAMPTZ,
    payload       JSONB       NOT NULL DEFAULT '{}',
    snapshotted_at TIMESTAMPTZ NOT NULL,
    UNIQUE (instance_id, remote_ref)
);
```

Snapshot-Semantik: jeder Lauf ersetzt den Instanz-Bestand vollständig (Delete+Insert in einer Transaktion) — Queues sind flüchtig, Historisierung liefert der `activity`-Strom.

## Laravel-Klassen

| Klasse | Typ | Vertrag |
|---|---|---|
| `ArrClientBase` | Client (abstrakt) | v3-Muster (Auth, Pagination, Fehlerklassen); vier schlanke Ableitungen |
| `ArrEntityTranslatorBase` + 4 | Übersetzer (pure) | System-Entität ↔ CanonicalMediaRef mit systemspezifischen Provider-Keys |
| `ArrCatalogIngestHandler`, `ArrActivityIngestHandler`, `ArrHealthIngestHandler` | Handler | generisch über der Client-Abstraktion, parametriert per Manifest |
| `ArrMonitoringEgressHandler` | Handler | Monitoring-Flags + Suchkommandos, hart begrenzt (s. o.) |
| `ScanPathJob` | Job (`scan`) | gezielter Teilbaum-Scan (Fundament-Pipeline mit Pfad-Scope) — hier spezifiziert, gehört dem Fundament |
| `RequestArrSearch` | Action | Suche für ein wanted-Item anstoßen (Rule-Engine-whitelisted); Audit |

## API und UI

Keine Endpunkte über SDK-Standard + `GET /api/v1/acquisition/overview` (familienübergreifend: wanted-Zähler, Queue-Aggregat, letzte Importe, Health — die Gesamtsicht als eine Route, `manager`). UI **`Acquisition/Overview`**: die Klammer-Ansicht — Queue aller Instanzen gemischt (sortiert nach ETA), Wanted-Liste aus Katalog-`presence` mit Zuständigkeits-Spalte (welches *arr), Import-Feed (activity), Health-Ampeln; Aktionen: „Suche anstoßen", „Monitoring setzen" (Egress-Actions). Kern-Flow: Benutzer markiert Staffel-Lücke als `wanted` im Katalog → Sonarr-Egress setzt Monitoring → Grab erscheint in der Queue-Sicht → Import triggert Pfad-Scan → Episode wird `present`, Watch-State-frisch — ohne dass der Benutzer Sonarr geöffnet hat.

## Edge Cases

* **Dieselbe Entität in zwei Instanzen** (Radarr-4K-Setup: zwei Radarrs für denselben Film): beide Mappings koexistieren (`provider_ids` pro Instanz-Namensraum via `origin_detail`); Egress adressiert die per Setting bestimmte Primär-Instanz, die Aktivitäts-Sicht zeigt beide.
* **Readarr-Metadaten-Instabilität** (Goodreads-Umbrüche): der Readarr-Übersetzer behandelt fehlende/gewechselte Provider-IDs defensiv (Confidence 0.7 statt 0.95, Review-Neigung höher) — dokumentierte Sonderbehandlung statt Familien-Fiktion.
* **History-Lücken** (*arr rotiert History): Cursor-Läufe tolerieren Lücken (der Pfad-Scan-Effekt ist idempotent; verpasste Import-Events heilt der reguläre Bibliotheks-Scan).
* ***arr entfernt Item** (Benutzer löscht Serie in Sonarr): Mapping verwaist (SDK-Muster); `presence` bleibt unangetastet (Katalog-Hoheit) — es entsteht ein Hinweis in der Aktivitäts-Sicht, kein automatischer Katalog-Eingriff.
* **Version v4/v5-Sprünge der *arr-APIs**: Diagnostics erkennt die Version; inkompatible Versionen setzen die Instanz auf `degraded` mit klarer Meldung statt kryptischer Parse-Fehler.

## Performance

Vier Instanzen × 5-min-Activity-Cursor sind vernachlässigbar (History-Deltas sind klein). Bestands-Syncs (6 h) paginieren; Sonarr-Episoden-Vollbestand (100k+ Episoden bei großen Setups) läuft als ResumableJob (SDK-Erstsync-Regel). Queue-Snapshots sind begrenzt (< 1000 Items) — Delete+Insert ist billiger als Diff.

## Security

API-Keys im Secret-Store; die Egress-Begrenzung (nur Monitoring/Suche, keine Lösch- oder Einstellungs-Endpunkte im Client implementiert) begrenzt den Schaden kompromittierter MediaForge-Instanzen Richtung *arr. Webhooks nach SDK-Muster. Queue-Payloads können Release-Namen mit Tracker-Hinweisen enthalten — `arr_queue_snapshot.payload` unterliegt `manager`-Sicht und wird nicht in Notifications durchgereicht.

## Tests

Familien-Contract-Tests gegen Fixtures aller vier Systeme (v3-Antwortvarianten); Übersetzer-Matrix (Provider-Key-Extraktion je System); Konflikt-Szenario `monitored` vs. Benutzer-`absent` ⇒ Review; Import-Event ⇒ ScanPathJob-Dispatch (mit Pfad-Scope-Verifikation); Snapshot-Ersetzungs-Transaktionalität; Egress-Begrenzungstest (Metadaten-Schreibversuch existiert nicht — Architekturtest über die Client-Oberfläche).

## ADR-Verweise

[ADR-0003](../adr/0003-provider-id-mapping.md) (Doppel-Brücken-Matching), SDK-Regeln; `presence`-Hoheit aus [database/core-schema.md](../database/core-schema.md).

## Offene Punkte

* **Whisparr/andere Familien-Forks**: gleiche Basis, keine Priorität; die Abstraktion trägt sie, wenn gewünscht.
* **Qualitätsprofil-Sicht** (welches Profil ein wanted-Item bekäme): lesbar über die APIs, UI-Mehrwert unklar — vertagt.
* **Import-Konflikt** (*arr importiert eine Datei, die MediaForge als Dublette kennt): Signalfluss Fingerprinting ← Import-Scan existiert; eine proaktive Warnung Richtung *arr ist unspezifiziert.
