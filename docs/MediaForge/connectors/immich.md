# Immich-Referenzarchitektur

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [connectors/connector-sdk.md](connector-sdk.md). Dieses Kapitel ist bewusst als **Referenzarchitektur** ausgewiesen, nicht als Vollconnector: Es definiert die Integrationsgrenze zu Immich und den schmalen Spiegel-Connector — und begründet, warum nicht mehr.

## Motivation

Immich ist der Foto-Spezialist der Zielarchitektur (Mobile-Backup, ML-Suche, Alben, Gesichter) und zugleich das Architektur-Vorbild für MediaForge' AI-Schicht (Masterdatei-Referenzanalyse). Fotos unterscheiden sich fundamental von den übrigen MediaForge-Medien: Es gibt keinen Watch-State, kein Editions-Modell, keine Beschaffung — die MediaForge-Kernmechanik greift fast nirgends. Was Betreiber trotzdem brauchen: den Foto-Bestand in Gesamtsichten (Speicher, Backup-Abdeckung, „was existiert wo") und Alben als navigierbare Katalog-Verweise. Genau das — und nur das — liefert der Spiegel-Connector.

## Integrationsgrenze (normativ)

**MediaForge übernimmt nie Foto-Binärdaten**, keine Thumbnails in eigene Artefakt-Ablagen, keine ML-Ergebnisse (Gesichter, CLIP-Embeddings bleiben Immich-intern), keine Schreiboperationen Richtung Immich. Der Spiegel umfasst: Alben (Name, Zählwerk, Cover-Referenz als Immich-URL), aggregierte Bestandszahlen (Assets, Speicher, Gerätequellen) und optional Asset-Metadaten auf Katalogebene (`photo_mirror`-Items — der im Kernschema reservierte Typ) für Alben, die der Betreiber explizit spiegelt. Die Anzeige verlinkt in Immich (Deep-Links), statt Inhalte zu duplizieren: MediaForge ist Verzeichnis, Immich bleibt Anwendung.

Begründung der Enge: Jede tiefere Integration (Asset-Spiegel komplett, Thumbnail-Proxy) dupliziert Immich-Funktionalität mit Sync-Kosten ohne MediaForge-Mehrwert — die Leitszenarien der Masterdatei enthalten keinen Foto-Fall, und die Kernschema-Frage „wie tief spiegeln?" ([core-schema](../database/core-schema.md), offene Punkte) wird hiermit beantwortet: **Alben-Ebene per Default, Asset-Ebene nur opt-in pro Album.**

## Analyse der Gegenstelle

Immich-API (1.1xx+, api-key-Header): `GET /api/albums` (mit Asset-Zählern), `GET /api/albums/{id}` (Assets mit EXIF-Auszug), `GET /api/server/statistics` (Bestand/Speicher), `GET /api/server/about` (Version). Änderungserkennung: `updatedAt`-Felder tragen einen Cursor-artigen Sync. Websockets/Events existieren, bleiben ungenutzt (Poll genügt für Spiegel-Zwecke).

## Manifest und Sync

```php
capabilities: ingestPlayState=false, egressPlayState=false,
              ingestCatalog=true, egressCatalog=false,
              supportsWebhooks=false, supportsCursorSync=true,
              rateLimit: 10 req/s
providerKeys: ['immich_album','immich_asset']
settings: base_url, api_key(secret), mirror_library_id,
          mirrored_albums[] (leer = nur Statistik/Albenliste), sync_interval
```

Zwei Ströme: **`stats`** (Intervall 1 h): Serverstatistik → Kennzahlen fürs Admin-Dashboard (Foto-Bestand als Teil der Gesamtspeicher-Sicht). **`albums`** (Intervall 6 h, Cursor): Albenliste als leichte Verweis-Objekte; für explizit gespiegelte Alben zusätzlich Assets als `photo_mirror`-Items (Titel=Dateiname, Aufnahmedatum, Immich-Deep-Link als einziges „Playback") in einer dedizierten Spiegel-Bibliothek (`media_kind='photo'`).

```sql
CREATE TABLE immich_album_mirrors (
    id            CHAR(26) PRIMARY KEY,
    instance_id   CHAR(26)    NOT NULL REFERENCES connector_instances(id) ON DELETE CASCADE,
    remote_ref    TEXT        NOT NULL,            -- Immich-Album-ID
    name          TEXT        NOT NULL,
    asset_count   INTEGER     NOT NULL DEFAULT 0,
    is_mirrored   BOOLEAN     NOT NULL DEFAULT false,  -- Asset-Ebene aktiv?
    cover_url     TEXT,                            -- Immich-URL, nie lokal gecacht
    synced_at     TIMESTAMPTZ NOT NULL,
    UNIQUE (instance_id, remote_ref)
);
```

## Laravel-Klassen

`ImmichManifest`, `ImmichClient` (vier Lese-Endpunkte; Schreib-Endpunkte nicht implementiert — Prowlarr-Muster), `ImmichAlbumTranslator` (pure), `ImmichStatsIngestHandler`, `ImmichAlbumsIngestHandler`, `ImmichDiagnostics`.

## UI

Instanz-Panel: Bestandskennzahlen, Albenliste mit Spiegel-Schaltern (pro Album opt-in zur Asset-Ebene), Deep-Link-Spalte. Gespiegelte Alben erscheinen als Bibliotheksansicht mit Kachel-Grid (Metadaten + „In Immich öffnen") — bewusst ohne eigenen Foto-Viewer.

## Referenzarchitektur-Anteil: was MediaForge von Immich übernimmt

Dieser Abschnitt dokumentiert die architektonischen Anleihen (bereits umgesetzt in den Fundament-Kapiteln, hier als Landkarte): dedizierte ML-Worker mit schmaler Job-Schnittstelle → [AI Engine](../modules/ai-engine.md); pgvector statt Vektor-Store → [Suche](../modules/search.md); Queue-Trennung nach Workload → [architecture/overview.md](../architecture/overview.md); Duplikat-Review-Flow → [Fingerprinting](../modules/dedup-fingerprinting.md). Wer die Immich-Codebasis studiert, findet die Entsprechungen entlang dieser Verweise — der eigentliche Wert der „Referenz" liegt in diesen Übernahmen, nicht im Spiegel-Connector.

## Edge Cases

* **Album gelöscht in Immich**: Spiegel-Items gehen den normalen `removed`-Weg; die Verweis-Zeile verschwindet beim nächsten Sync.
* **Externe Immich-Bibliotheken** (Immich liest dieselben NAS-Pfade wie eine MediaForge-Bibliothek): kein Konflikt — MediaForge fasst Fotos ohnehin nicht an; die Dubletten-Pipeline ignoriert `photo_mirror` (keine Datei-Ebene im Spiegel).
* **Sehr große Alben** (50k Assets): Asset-Spiegel paginiert als ResumableJob; die Empfehlung im UI warnt ab 10k („Spiegel-Ebene für Großalben nicht empfohlen — Statistik genügt").

## Performance

Vernachlässigbar bei Default (Statistik + Albenliste); Asset-Spiegel skaliert linear mit opt-in-Umfang — bewusst in Betreiberhand.

## Security

API-Key im Secret-Store; Deep-Links/Cover-URLs zeigen auf Immich — die Immich-Auth gilt dort (MediaForge proxied nichts, cached nichts, leakt also auch nichts). Read-only-Client nach Prowlarr-Muster (Architekturtest).

## Tests

Contract-Fixtures (albums/statistics-Varianten); Spiegel-Lebenszyklus (opt-in → Items, opt-out → Aufräumen, Album-Löschung); Read-only-Architekturtest; Paginierungs-Wiederaufnahme.

## ADR-Verweise

SDK-Regeln; Nicht-Ziel „kein Photo-Backup-Dienst" (Masterdatei) — dieses Kapitel ist seine Ausbuchstabierung.

## Offene Punkte

* **Personen-Brücke** (Immich-Gesichter ↔ MediaForge-`people`): reizvoll (dieselbe Person in Filmen und Fotos), aber Privatsphäre-Governance ungeklärt — ausdrücklich vertagt.
* **Backup-Abdeckungs-Sicht** (sind Immich-Originale im Backup-Konzept erfasst?): gehört ins [Backup-Kapitel](../modules/backup-restore.md); der Stats-Strom liefert die Zahlen.
