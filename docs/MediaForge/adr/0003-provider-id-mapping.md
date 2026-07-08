# ADR-0003: Provider-IDs ausschließlich als Mapping-Tabellen

Status: accepted · Bezug: [database/core-schema.md](../database/core-schema.md), Architekturregel 7

## Kontext

MediaForge verknüpft kanonische Entitäten mit vielen externen Identitäten: Metadaten-Provider (TMDB, TVDB, MusicBrainz, Audible, ISBN) und integrierte Systeme (Jellyfin-Item-IDs, ABS-IDs, Stash-IDs, *arr-IDs). Externe IDs sind außerhalb der Kontrolle von MediaForge: Provider mergen Einträge, recyceln IDs, korrigieren Fehlzuordnungen; Connector-Ziele werden neu installiert und vergeben alle IDs neu.

## Entscheidung

Externe IDs sind niemals Primärschlüssel, niemals Fremdschlüsselziel und niemals Spalten der Kern-Entitäten. Sie leben ausschließlich in der Tabelle `provider_ids` mit Provider-Kennung, externem Wert, Herkunft (`matcher|manual|connector|import|ai`), Confidence, Verifikations- und Sichtungszeitstempeln. Pro Entität und Provider ist höchstens ein Mapping aktiv (partieller Unique-Index); der Rückwärts-Lookup ist bewusst nicht unique, damit Kollisionen (zwei Entitäten beanspruchen dieselbe externe ID) repräsentierbar sind und als Dubletten-Signal dienen.

## Konsequenzen

* Provider-seitige Umbrüche (Merge, Recycling, Neuinstallation eines Jellyfin) sind Datenpflege in einer Tabelle, nie Identitätsverlust im Katalog.
* Jeder Lookup kostet einen Join — akzeptiert; der Lookup-Index deckt ihn ab.
* Matching-Confidence und menschliche Verifikation sind erstklassige Daten; das Review-System kann auf unsicheren Mappings aufsetzen.
* Feingranulare Provider-Namespaces (`tmdb_movie` vs. `tmdb_tv`) sind Pflicht, um ID-Kollisionen innerhalb eines Providers auszuschließen.

## Erwogene Alternativen

ID-Spalten am Item (Jellyfin-Stil: `tmdb_id` auf dem Item) — scheitert an Mehrfach-Providern, Herkunftsdokumentation und Recycling; JSONB-Map am Item — verletzt Regel 8 (Lookup und Unique-Garantien wären applikationsseitig); eine Mapping-Tabelle pro Provider — explodiert die Tabellenzahl und streut jede Sync-Operation über N Tabellen.
