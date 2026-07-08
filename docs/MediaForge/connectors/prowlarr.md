# Prowlarr-Connector

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [connectors/connector-sdk.md](connector-sdk.md), [connectors/arr-family.md](arr-family.md) (gemeinsame API-Basis). Der schmalste Connector des Systems — bewusst.

## Motivation

Prowlarr verwaltet Indexer für die gesamte *arr-Familie. MediaForge spricht nie selbst mit Indexern (Nicht-Ziel) und braucht Prowlarr auch nicht zu steuern — die *arrs beziehen ihre Indexer selbst von dort. Was MediaForge braucht, ist die **Beobachtungslücke schließen**: Wenn die Beschaffungskette hakt („nichts kommt an"), liegt die Ursache oft bei Prowlarr (Indexer down, Rate-limited, API-Limit erschöpft). Ohne diese Sicht zeigt die Acquisition-Übersicht der [*arr-Connectoren](arr-family.md) Symptome ohne Ursache. Der Connector liefert also: Health, Indexer-Status und Statistiken — lesend, als Diagnose-Baustein.

## Problemstellung

Die einzige echte Designfrage ist Zurückhaltung: Prowlarrs API erlaubt Indexer-Verwaltung, Suchen, App-Sync-Steuerung — alles Dinge, die MediaForge **nicht** tun soll (Suche wäre Indexer-Kontakt durch die Hintertür; Verwaltung wäre Doppelzuständigkeit mit Prowlarrs eigenem UI). Der Connector muss so gebaut sein, dass diese Fähigkeiten nicht existieren, statt nur ungenutzt zu sein.

## Analyse der Gegenstelle

Prowlarr teilt die *arr-API-Architektur (`/api/v1` hier, API-Key-Header): `GET /api/v1/health`, `GET /api/v1/indexer` (Konfigurationsbestand mit Enable-Status), `GET /api/v1/indexerstatus` (Ausfälle/Backoffs pro Indexer), `GET /api/v1/indexerstats` (Erfolgs-/Fehlerraten, Antwortzeiten). Webhooks: On Health Issue. Mehr braucht der Connector nicht — und implementiert mehr auch nicht.

## Manifest

```php
capabilities: ingestPlayState=false, egressPlayState=false,
              ingestCatalog=false, egressCatalog=false,      // Prowlarr hat keinen Katalog-Bezug
              supportsWebhooks=true, supportsCursorSync=false,
              rateLimit: 5 req/s
providerKeys: []                                             // keine Entitäts-Mappings — reiner Status-Connector
streams: ['health']                                          // einziger Strom
```

Der leere `providerKeys`-Satz ist die formale Aussage: Dieser Connector mappt nichts auf den Katalog. Das SDK trägt solche Status-only-Connectoren ohne Sonderfall (der `health`-Strom ist ein regulärer Ingest ohne Subjekt-Matching).

## Sync

Ein Strom, `health` (Intervall 15 min + Webhook-Trigger): Health-Items, Indexer-Status und Statistiken werden zu einem Instanz-Gesundheitsbild aggregiert — Connector-Health `healthy`/`degraded` mit strukturiertem Detail (welche Indexer down, seit wann, Backoff bis) und als Snapshot in `prowlarr_indexer_snapshot` (analog zum Queue-Snapshot der *arr-Connectoren: flüchtig, Delete+Insert, kein Audit):

```sql
CREATE TABLE prowlarr_indexer_snapshot (
    id            CHAR(26) PRIMARY KEY,
    instance_id   CHAR(26)    NOT NULL REFERENCES connector_instances(id) ON DELETE CASCADE,
    remote_ref    TEXT        NOT NULL,           -- Indexer-ID
    name          TEXT        NOT NULL,
    enabled       BOOLEAN     NOT NULL,
    status        TEXT        NOT NULL,           -- ok|failing|disabled_until
    disabled_until TIMESTAMPTZ,
    stats         JSONB       NOT NULL DEFAULT '{}',   -- Raten/Latenzen (Anzeige)
    snapshotted_at TIMESTAMPTZ NOT NULL,
    UNIQUE (instance_id, remote_ref)
);
```

## Laravel-Klassen

`ProwlarrManifest`, `ProwlarrClient` (nur die vier Lese-Endpunkte — Verwaltungs-/Such-Endpunkte sind im Client **nicht implementiert**, die Zurückhaltung ist Code-Struktur, per Architekturtest fixiert), `ProwlarrHealthIngestHandler`, `ProwlarrDiagnostics` (Version, Key-Gültigkeit).

## API und UI

Kein eigener Endpunkt; die Snapshot-Daten fließen in `GET /api/v1/acquisition/overview` ([*arr-Kapitel](arr-family.md)) als Ursachen-Spalte: Die Acquisition-Übersicht zeigt bei stockender Queue direkt „Prowlarr: 3 Indexer im Backoff" statt nackter Symptome. Im Instanz-UI: Indexer-Tabelle (Status, Raten, Backoff-Countdown) als Diagnose-Panel.

## Edge Cases

* **Prowlarr ohne konfigurierte *arrs** (Standalone-Betrieb): Connector funktioniert unverändert — er hängt nicht an den *arr-Instanzen; die Übersicht zeigt die Sicht dann isoliert.
* **Indexer-Namen als sensible Information** (private Tracker): Snapshot unterliegt `manager`; Namen erscheinen nie in Notifications (dieselbe Regel wie Release-Namen im *arr-Kapitel).
* **API-v1-Drift**: Diagnostics-Versionsprüfung, `degraded` bei Inkompatibilität (Familien-Muster).

## Performance

Trivial: drei kleine GETs alle 15 min, Snapshot < 200 Zeilen.

## Security

API-Key im Secret-Store; die Nicht-Implementierung der Such-/Verwaltungs-Endpunkte ist die zentrale Sicherheitseigenschaft (eine kompromittierte MediaForge-Instanz kann über diesen Connector weder suchen noch Indexer umkonfigurieren). Webhook nach SDK-Muster.

## Tests

Contract-Fixtures (health/indexerstatus/stats-Varianten); Aggregations-Logik (Statuskombinationen ⇒ erwartete Instanz-Health); Architekturtest der Client-Oberfläche (keine Such-/Schreib-Methoden); Snapshot-Ersetzung.

## ADR-Verweise

SDK-Regeln; Nicht-Ziel-Abgrenzung der Masterdatei (kein Indexer-Kontakt) — dieser Connector ist ihre technische Durchsetzung.

## Offene Punkte

* **Statistik-Historie** (Indexer-Trends über Wochen): Snapshots sind flüchtig; ob Trends aufgehoben werden, entscheidet das Admin-Dashboard-Kapitel (Metrik-Zeitreihen) — Datenquelle wäre dieser Strom.
