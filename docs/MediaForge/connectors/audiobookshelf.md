# Audiobookshelf-Connector

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [connectors/connector-sdk.md](connector-sdk.md) (Rahmen), [modules/audiobook-assembler.md](../modules/audiobook-assembler.md) (Export-Gegenstück). Nur ABS-Spezifika; Generisches gilt aus dem SDK.

**Vertiefung**: [API-Mapping-Referenz](audiobookshelf/api-mapping.md) (Wire-Ebene: Endpunkte, Feldpfade, Sidecar-Erkennung)

## Motivation

Audiobookshelf (ABS) bleibt in der Zielarchitektur der Hörbuch-Player (Apps, Streaming, Sleep-Timer); MediaForge ist lokale Kurations-, Enhancement- und Audit-Schicht für kuratierte Kapitel- und Fortschrittsdaten. Der Connector schließt den Kreis aus Leitszenario 2: Der Assembler produziert saubere Strukturen, der Export materialisiert sie ABS-kompatibel, der Connector synchronisiert Hörfortschritte zurück — wer im Auto per ABS-App hört, hat in MediaForge den korrekten Stand, und die MediaForge-Kapitelarbeit erscheint in ABS.

## Problemstellung

ABS-Spezifika: **(1) Fortschrittsmodell** — ABS führt `currentTime` (Sekunden, float) relativ zur **Gesamtdauer des Items** über alle Dateien plus `isFinished`; bei Editionen aus vielen Tracks entspricht das der Werkzeit-Achse des Assemblers — die Übersetzung braucht exakt dieselbe Zeitachse, sonst driftet der Fortschritt zwischen den Systemen. **(2) Item-Identität** — ABS-Item-IDs sind installationsgebunden; ABS kennt ASIN/ISBN-Metadaten, aber unzuverlässig gepflegt; die stabilste Brücke ist der Bibliothekspfad (bei Export-Bibliotheken trivial: MediaForge hat die Struktur selbst geschrieben). **(3) Doppel-Bibliotheks-Szenario** — Betreiber können ABS auf die Original-Ordner **und/oder** auf den MediaForge-Export zeigen lassen; der Connector muss beide Fälle sauber halten und darf Fortschritte nicht doppelt zählen, wenn dasselbe Werk über zwei ABS-Items sichtbar ist.

## Analyse der Gegenstelle

Relevante ABS-API (stabil ab 2.x): Token-Auth (Bearer, pro ABS-User); `GET /api/libraries/{id}/items` (paginiert, mit `media.metadata` inkl. ASIN/ISBN, `libraryFiles` mit Pfaden); `GET /api/me/listening-sessions` und `GET /api/me/progress` (Fortschritte des Token-Users); `PATCH /api/me/progress/{itemId}` (Fortschritt schreiben: `currentTime`, `isFinished`); Webhooks existieren nicht verlässlich über Versionen — Manifest: `supportsWebhooks=false`, reiner Poll (`supportsCursorSync=false`, Zustandsvergleich wie Jellyfin, aber über die schlanke `/me/progress`-Liste billig). Session-basierte Feinsignale (`listening-sessions` mit Zeitspannen) werden gelesen, aber nur als `occurred_at`-Quelle genutzt — die Positionswahrheit ist `progress`.

Benutzer-Modell: Ein Connector-**Instanz-Token pro gemapptem User-Paar** (ABS-Token sind benutzergebunden; ein Admin-Token kann fremde Fortschritte nur eingeschränkt schreiben) — das Settings-Schema erlaubt mehrere User-Einträge mit je eigenem Secret, der Sync läuft pro Paar.

## Manifest

```php
capabilities: ingestPlayState=true, egressPlayState=true,
              ingestCatalog=true, egressCatalog=false,
              supportsWebhooks=false, supportsCursorSync=false,
              rateLimit: 10 req/s
providerKeys: ['abs_item','abs_user','abs_server']
settings: base_url, user_mappings[{abs_user, mediaforge_user, token(secret)}],
          sync_interval, verify_tls, import_libraries[], export_library_id
```

## Item-Mapping

Kaskade (SDK-konform, ABS-priorisiert): (1) **Export-Pfad-Treffer** — liegt das ABS-Item unter dem MediaForge-Export (`export_library_id`-Bibliothek), identifiziert der Exportpfad die Quell-Edition deterministisch (der Export schreibt eine `mediaforge.json`-Sidecar mit Edition-ULID; ABS listet sie in `libraryFiles` — Mapping ohne Heuristik). (2) **Original-Pfad-Treffer** — ABS zeigt auf dieselben Original-Ordner wie eine MediaForge-Bibliothek: Pfad-Normalisierung gegen `files`. (3) **ASIN/ISBN** aus ABS-Metadaten gegen `provider_ids`. (4) Rest → `unmatched`/Review. Sind Original- und Export-Item **desselben Werks** beide gemappt (Doppel-Bibliotheks-Szenario), markiert der Connector das Paar (`provider_ids` beider ABS-Items auf dieselbe Edition) und behandelt Fortschritte beider Items als eine Quelle — die jüngste gewinnt, Echo-Fenster gilt paarweise.

## Fortschritts-Sync

**Ingest**: `/me/progress` pro User-Paar; Übersetzung `currentTime`·1000 → Werkzeit-`position_ms`. Trägt die Ziel-Edition eine Assembler-Zeitachse, wird gegen `total_duration_ms` plausibilisiert (Abweichung der Gesamtdauern > 1 % ⇒ Review `connector_conflict` mit Verdacht „ABS sieht andere Dateien" statt stiller Prozent-Umrechnung — Prozent-Mapping über verschiedene Dateimengen ist die klassische Drift-Quelle). `isFinished=true` → Fakt „gehört" mit ABS-`finishedAt` als `occurred_at`. Alles mündet in `RecordPlaybackProgress`/`MarkWatched` mit `source='connector:abs'`; die Hörbuch-Watched-Schwelle (99 %, Fundament) bleibt MediaForge-Sache — ein ABS-`isFinished` ist ein expliziter Fakt und wird als solcher übernommen, aber eine ABS-Position von 97 % macht in MediaForge noch kein „gehört".

**Egress**: Watch-State-Änderungen (MediaForge-seitig oder von anderen Quellen) → Outbox → `PATCH /me/progress/{itemId}` mit übersetzter `currentTime` bzw. `isFinished`; Read-back als Echo-Hash. Beim Doppel-Bibliotheks-Paar wird nur **ein** Item beschrieben (das Export-Item, falls vorhanden — es ist die kuratierte Sicht), nie beide.

## Export-Kopplung

Der Connector rundet den Assembler-Export ab: Nach `BuildAbsExportJob` (Assembler) stößt ein Listener optional einen ABS-Bibliotheks-Scan an (`POST /api/libraries/{id}/scan`) — Setting `trigger_abs_scan`, Default true. Kapitel-Updates (neues aktives Chapter Set ⇒ neuer Export) erscheinen so ohne manuelles Zutun in ABS. Der Connector schreibt Kapitel **nie direkt** über die ABS-API (`PATCH /api/items/{id}/chapters` existiert, bleibt aber ungenutzt): Der Export-Weg hält die Materialisierung in MediaForge-Hand (Artefakt, Signatur, Audit) statt flüchtiger API-Zustände — eine bewusste Engstelle.

## Laravel-Klassen

| Klasse | Typ | Vertrag |
|---|---|---|
| `AbsManifest`, `AbsClient`, `AbsDiagnostics` | SDK-Trio | Client kapselt die o. g. Endpunkte; Diagnostics prüft Version, Token-Gültigkeit pro Paar, Bibliothekszugriff |
| `AbsItemTranslator`, `AbsProgressTranslator` | Übersetzer (pure) | Item↔CanonicalMediaRef (inkl. Sidecar-Erkennung), progress↔CanonicalPlayState (s·float ↔ ms, Plausibilisierung) |
| `AbsProgressIngestHandler`, `AbsProgressEgressHandler` | Handler | pro User-Paar; Doppel-Item-Konsolidierung |
| `AbsCatalogIngestHandler` | Handler | Mapping-Pflege, optionaler Import (Default false, wie Jellyfin) |
| `TriggerAbsScanListener` | Listener | auf `AudiobookArtifactBuilt` (Export-Typ), debounced |

## API/UI

Keine Endpunkte über den SDK-Standard hinaus. Instanz-UI ergänzt: User-Paar-Tabelle mit Token-Test je Paar, Export-Bibliotheks-Zuordnung (Dropdown der ABS-Bibliotheken), Doppel-Bibliotheks-Anzeige (erkannte Paare mit Konsolidierungs-Status).

## Edge Cases

* **ABS re-scannt und teilt ein Werk anders** (ABS-Item zerfällt in zwei): Pfad-Mapping erkennt die neuen Items; die Dauer-Plausibilisierung schlägt an; Review statt Prozent-Raten.
* **Fortschritt auf dem Original, Export kommt später hinzu**: Erst-Mapping des Export-Items übernimmt den bestehenden Watch-State (kein Reset); das Paar wird konsolidiert.
* **ABS-Podcasts** in importierten Bibliotheken: `mediaType='podcast'`-Items werden ignoriert (MediaForge-Katalog kennt Podcasts nicht; ehrliches Nicht-Feature statt halber Abbildung).
* **Token läuft ab / User in ABS gelöscht**: Paar-Health `auth_failed`, andere Paare laufen weiter (Fehlerisolation pro Paar, nicht pro Instanz).
* **Uhr-Drift**: SDK-Skew-Regel; ABS liefert Epochen-Millisekunden, Drift ist selten, aber die `review`-Eskalation greift identisch.

## Performance

`/me/progress` ist klein (eine Zeile pro begonnenem Item); Poll pro Paar alle 5 min ist vernachlässigbar. Katalog-Ingest (24 h) paginiert mit 200er-Batches. Export-Scan-Trigger debounced (5 min), damit Serien-Builds nicht N Scans feuern.

## Security

Pro-User-Tokens minimieren den Radius (ein Token kann nur eigene Fortschritte); Secret-Store, Maskierung, TLS-Regeln nach SDK. Die `mediaforge.json`-Sidecar im Export enthält nur die Edition-ULID und Struktur-Metadaten — keine internen Pfade, keine Benutzer- oder Systemdaten (sie liegt in einem Ordner, den ABS-Nutzer sehen können).

## Tests

Contract-Fixtures gegen ABS-2.x-Antworten (Items mit/ohne Sidecar, progress-Varianten, float-Randfälle). Szenario-Tests: Zeitachsen-Roundtrip (MediaForge-Position → ABS → Ingest ⇒ identische ms ±1 s Float-Toleranz), Doppel-Bibliotheks-Konsolidierung (beide Items melden ⇒ ein Watch-State, keine Oszillation), Dauer-Mismatch ⇒ Review, isFinished-Übernahme vs. 97-%-Position-Nicht-Übernahme (Schwellen-Hoheit — Architekturregel-3-Regressionstest).

## ADR-Verweise

[ADR-0003](../adr/0003-provider-id-mapping.md), [ADR-0008](../adr/0008-chapter-source-hierarchy.md) (Export trägt Herkunft nach außen), SDK-Regeln.

## Offene Punkte

* **ABS-Kapitel als Validierungsquelle** rückwärts (ABS-Nutzer editiert Kapitel in ABS): derzeit ignoriert; ob solche Edits als Kapitelquelle (`sidecar`-analog) eingelesen werden sollen, ist Governance-offen.
* **Lesestatistiken** (ABS-Sessions als Hörstatistik-Quelle für ein künftiges Statistik-Modul): Daten vorhanden, Modul unspezifiziert.
