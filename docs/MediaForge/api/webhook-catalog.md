# Webhook-Gesamtkatalog

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Vertiefung zu [api/conventions.md](conventions.md) und [connectors/connector-sdk.md](../connectors/connector-sdk.md). `POST /api/v1/webhooks/{key}/{instanz-ulid}/{signatur}` ist die **einzige tokenlose Fläche** des Systems ([Endpunkt-Katalog](endpoint-catalog.md)) — dieses Dokument katalogisiert, was jede Connector-Gegenstelle tatsächlich an diesen Pfad sendet, wie die Signatur geprüft wird und was mit dem Payload geschieht (SDK-Regel: „nur Trigger, nie Datenquelle" — hier wörtlich nachvollziehbar je Gegenstelle).

## Gemeinsamer Vertrag

**Pfad-Signatur**: HMAC-SHA256 über den Instanz-ULID-Pfad mit einem instanzspezifischen Secret (Connector-SDK, [Security-Abschnitt](../connectors/connector-sdk.md)); die `{signatur}`-Komponente ist der Hex-codierte HMAC, vom Betreiber beim Einrichten des Webhooks in der Gegenstelle einmalig kopiert. Eine falsche/fehlende Signatur ⇒ `connector.webhook_signature_invalid` (401), gezählt für den Health-Check `security.webhook_signature_failures` ([Health-Check-Referenz](../modules/health-monitoring/health-check-reference.md)). Eine unbekannte Instanz-ULID ⇒ `connector.webhook_instance_unknown` (404).

**Verarbeitung**: Jeder eingehende Webhook debounct einen Sync-Lauf des zuständigen Streams (Redis-Debounce, Default 30 s, [Connector SDK](../connectors/connector-sdk.md)) — der Payload-Inhalt wird geparst, um die **Relevanz** zu prüfen (welcher Stream? welches Subjekt?), fließt aber nie direkt in eine Fach-Action; die eigentliche Datenwahrheit holt der reguläre Cursor-/Poll-Lauf. Diese Regel ist hier je Gegenstelle konkret nachvollziehbar (Spalte „Payload-Nutzung").

## Jellyfin (Webhook-Plugin)

**Einrichtung**: Jellyfin-Dashboard → Plugins → Webhooks → Generic Destination, Ziel-URL = der signierte MediaForge-Pfad, Payload-Template JSON (Standard-Vorlage). **Relevante Ereignistypen**: `PlaybackStop`, `UserDataSaved`, `ItemAdded` (optional, für schnelleren Katalog-Ingest).

```json
{"NotificationType": "PlaybackStop", "ItemId": "a1b2c3…", "UserId": "d4e5f6…",
 "PlaybackPositionTicks": 12345670000, "Played": false, "ServerId": "…"}
```

**Payload-Nutzung**: `ItemId`+`UserId` werden extrahiert und gegen bekannte Mappings geprüft (SDK-Kaskade); bei Treffer sofortiger Ziel-Poll (`GET /Users/{userId}/Items/{itemId}?Fields=UserData`, [Jellyfin-API-Mapping](../connectors/jellyfin/api-mapping.md)) statt Payload-Übernahme — der Payload-Wert für `PlaybackPositionTicks` wird **nie** direkt verarbeitet (Modulkapitel: „Webhook-Semantik ist verkürzt gegenüber der Abfrage-API"). `ItemAdded` triggert einen gezielten Katalog-Ingest-Poll statt des vollen 24-h-Zyklus.

## Audiobookshelf

**Kein Webhook-Support** (`supportsWebhooks=false`, [ABS-Connector](../connectors/audiobookshelf.md)) — ABS-Instanzen erscheinen nie unter diesem Pfad; reiner Poll (`/me/progress`, 5-Minuten-Intervall). Aufgenommen zur Vollständigkeit des Katalogs (Negativ-Eintrag, damit ein Leser nicht nach einem ABS-Webhook sucht, der nicht existiert).

## *arr-Familie (Connect-Webhooks)

**Einrichtung**: Settings → Connect → Webhook, je Instanz (Sonarr/Radarr/Readarr/Lidarr identisch). **Relevante Ereignistypen**: `Grab`, `Download` (= Import), `Health`.

```json
{"eventType": "Download", "series": {"id": 42, "tvdbId": 121361}, "episodes": [{...}],
 "downloadClient": "…", "isUpgrade": false}
```

**Payload-Nutzung**: `eventType='Download'` mit `series`/`movie`/`author`/`artist`-ID triggert sofort `ScanPathJob` auf den im Payload genannten Zielordner (`data.importedPath` bzw. äquivalentes Feld, [*arr-API-Mapping](../connectors/arr-family/api-mapping.md)) — hier ist der Payload-Inhalt (der Pfad) tatsächlich **direkt genutzt**, nicht nur Trigger, weil der Pfad-Scope selbst die Nutzlast der Aktion ist (kein „Katalogfakt", sondern ein Scan-Ziel — die SDK-Regel „nie Datenquelle" bezieht sich auf Fachwerte wie Watch-States/Monitoring, nicht auf Scan-Koordinaten). `eventType='Health'` triggert einen sofortigen Health-Ingest-Poll statt auf den regulären Zyklus zu warten.

## Stash

**Kein Webhook-Support** (`supportsWebhooks=false`, [optionalem Stash-Import/Connector](../connectors/stash.md)) — Cursor-Sync über `updated_at`-Filter (1-Stunden-Intervall) ist der einzige Änderungserkennungs-Mechanismus. Negativ-Eintrag wie ABS.

## External-Player (kein Webhook-Pfad — eigener Kanal)

Kodi/MPV nutzen **nicht** den generischen Webhook-Pfad, sondern das dedizierte Player-Protokoll (`POST /playback/disc-sessions/{ulid}/events`, [Disc-Engine-API](../modules/disc-engine/api-reference.md)) mit eigener Token-Authentifizierung (`playback:report`-Scope) statt Pfad-Signatur — aufgenommen zur Abgrenzung: Nicht jede „Gegenstelle meldet etwas an MediaForge"-Fläche ist ein Webhook im hiesigen Sinne; das Player-Protokoll ist authentifizierter API-Zugriff, kein Fire-and-Forget-Trigger.

## Immich (Referenzarchitektur, kein aktiver Connector)

Kein Webhook-Kapitel spezifiziert (Immich-Kapitel ist Referenzarchitektur-Analyse, kein Produktions-Connector, [connectors/immich.md](../connectors/immich.md)) — kein Katalog-Eintrag.

## Vollständigkeits-Tabelle

| Connector | Webhook-Support | Ereignistypen | Payload direkt genutzt? |
|---|---|---|---|
| Jellyfin | ja | `PlaybackStop`, `UserDataSaved`, `ItemAdded` | nein (nur Trigger) |
| Audiobookshelf | nein | — | — |
| *arr-Familie | ja | `Grab`, `Download`, `Health` | ja (Pfad bei `Download`) |
| Stash | nein | — | — |
| External-Player | n/a (eigener authentifizierter Kanal) | — | ja (Positions-/Zustandsdaten sind der Zweck des Kanals) |

## Signatur-Einrichtung (Betreiber-Ablauf, Kurzfassung)

Beim Anlegen einer Connector-Instanz mit Webhook-Fähigkeit generiert `CreateConnectorInstance` ein instanzspezifisches Secret (Secret-Store, [Connector SDK](../connectors/connector-sdk.md)) und zeigt den vollständigen Ziel-Pfad inkl. berechneter Signatur einmalig im UI an (`Connectors/Index`-Einrichtungsflow, [Seiten-Katalog](../ui/page-catalog.md)) — der Betreiber kopiert ihn in die Gegenstellen-Konfiguration. Eine Secret-Rotation (`RotateConnectorSecret`, falls implementiert) invalidiert alte Signaturen sofort; der Health-Check `security.webhook_signature_failures` macht eine vergessene Aktualisierung der Gegenstelle sichtbar (Runbook-Anker `#webhook-signature`, [runbooks.md](../developer-handbook/runbooks.md)).

## Security-Zusammenfassung

Der Webhook-Pfad ist bewusst die einzige Ausnahme von „kein tokenloser Schreibzugriff" ([api/conventions.md](conventions.md)) — die Pfad-Signatur ersetzt das Token, ist aber schwächer als ein Bearer-Token (kein Ablauf, keine Scope-Beschränkung über „diese eine Instanz"). Deshalb gilt strikt: Payload-Inhalte fließen nie ungeprüft in Fach-Actions (Ausnahme *arr-Pfad, oben begründet — und selbst dort löst der Payload nur einen **Scan**, keine Katalog-/Watch-State-Änderung aus). Rate-Limiting des Webhook-Pfads folgt der Connector-Instanz-Konfiguration, nicht dem generischen API-Limit (ein Webhook-Sturm einer Gegenstelle darf die reguläre API-Nutzung anderer Instanzen nicht beeinträchtigen — separater Redis-Bucket je Instanz).

## Tests

Signatur-Verifikations-Suite (gültig/ungültig/fehlend je Connector-Typ); Debounce-Test (Webhook-Flut ⇒ genau ein Sync-Lauf pro Debounce-Fenster, [Connector-SDK-Edge-Case](../connectors/connector-sdk.md) „Webhook-Flut"); Payload-Nutzungs-Grenztest (Jellyfin-Payload-Werte werden nachweislich verworfen zugunsten des Ziel-Polls; *arr-Pfad wird nachweislich für den Scan-Scope verwendet) — der Test, der die Katalog-Tabellenzeile „Payload direkt genutzt?" gegen den tatsächlichen Code verifiziert.
