# API-Konventionen

API bedeutet in MediaForge standardmäßig lokale Kommunikation: MediaForge zu Jellyfin, Audiobookshelf, Laravel-internen Endpunkten, PostgreSQL, Redis und lokalen Worker-Diensten. Öffentliche oder externe APIs sind optionale Integrationsflächen und werden explizit als solche markiert. Keine API-Konvention in diesem Kapitel begründet eine Cloudpflicht.

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [architecture/overview.md](../architecture/overview.md) (Inertia/REST-Trennung), [database/core-schema.md](../database/core-schema.md) (Rollen). Dieses Kapitel ist normativ für jede `/api/v1`-Route aller Module; die Modulkapitel definieren nur ihre Routen und Payload-Spezifika.

**Vertiefungen**: [Endpunkt-Gesamtkatalog](endpoint-catalog.md) (alle Routen, Konsumenten-Landkarte, OpenAPI-Struktur) · [Fehlercode-Gesamtkatalog](error-catalog.md) (alle `code`-Werte, Namensraum-Register, Konsistenzregeln) · [Webhook-Gesamtkatalog](webhook-catalog.md) (die tokenlose Fläche je Connector)

## Geltungsbereich und Grundsatz

Die REST-API existiert ausschließlich für **externe Konsumenten**: Automatisierung, CLI, fremde Systeme, das Kodi-Add-on. Das eigene Web-UI läuft über Inertia und benutzt die REST-API nicht (Masterdatei, Stack-Begründung). Daraus folgt der wichtigste Grundsatz: Die API ist klein, stabil und verändert sich nur additiv innerhalb einer Version — sie muss keine UI-Launen mitmachen, und UI-Features erzwingen keine API-Änderungen. Jede neue Route braucht einen benannten externen Anwendungsfall; „das UI könnte es brauchen" ist explizit kein Grund.

## Versionierung

Pfad-Versionierung (`/api/v1/…`). Innerhalb von v1 sind erlaubt: neue Routen, neue optionale Felder in Responses, neue optionale Request-Parameter. Verboten: Feld-Entfernung, Typänderung, Semantikänderung, neue Pflichtparameter. Breaking Changes eröffnen `/api/v2` mit Parallelbetrieb (v1 mindestens 12 Monate ab v2-Release, Abkündigung über `Deprecation`- und `Sunset`-Header). Die API-Version ist unabhängig von der Produktversion — ein MediaForge-Major-Release ohne API-Bruch bleibt bei v1.

## Authentifizierung und Tokens

Bearer-Tokens (Laravel Sanctum, `Authorization: Bearer <token>`), erzeugt im Benutzerkontext mit **Fähigkeits-Scopes**. Der Scope-Katalog ist geschlossen und klein:

| Scope | Erlaubt |
|---|---|
| `read` | alle GET-Routen im Rollenrahmen des Token-Users |
| `write:catalog` | Katalog-/Review-/Mapping-Mutationen (Rollenrahmen) |
| `write:operations` | Jobs/Workflows/Syncs anstoßen |
| `playback:report` | ausschließlich das Player-Protokoll ([Disc-Engine](../modules/disc-engine.md)) |
| `admin` | Instanz-/Regel-/Settings-Verwaltung (nur für Token von admin-Usern) |

Tokens sind benannt, listbar, einzeln widerrufbar, optional ablaufend (Default 1 Jahr); der Klartext ist nur bei Erzeugung sichtbar. Jede Token-Nutzung aktualisiert `last_used_at` (Anzeige in der Token-Verwaltung — tote Tokens sollen auffallen). Rollen ([core-schema](../database/core-schema.md)) begrenzen zusätzlich: Ein `member`-Token mit `write:catalog` kann trotzdem keine Reviews auflösen. Webhook-Eingänge authentifizieren über signierte Pfade (SDK-Regel), nie über Tokens.

## Ressourcen-Konventionen

* **IDs**: ULIDs im Pfad, nie interne Sequenzen, nie Provider-IDs.
* **Benennungen**: Plural-Nomen (`/discs`, `/rules`), Aktionen als POST auf Unterressource (`/discs/{ulid}/reanalyze`) — kein RPC-Stil im Pfadnamen, aber auch kein REST-Dogmatismus, der `PATCH`-Semantik-Akrobatik erzwingt: Zustandsübergänge mit Fachbedeutung (confirm, cancel, activate) sind explizite POST-Aktionen, weil sie Actions im Sinne der Architektur sind.
* **Feldnamen**: snake_case, identisch zu den Schema-Spalten, wo eine 1:1-Entsprechung existiert; berechnete Felder erkennbar benannt (`derived_status`).
* **Zeit**: ISO 8601 UTC (`2026-07-06T14:31:22Z`), Dauern/Positionen als Ganzzahl-Millisekunden (`position_ms`) — konsistent mit dem Schema, keine Sekunden-Floats an der Grenze (die ABS-Übersetzung bleibt im Connector).
* **Antwort-Hülle**: Einzelressource nackt (`{"id": …}`), Listen als `{"data": […], "meta": {…}, "links": {…}}` (Laravel-Resource-Standard).

## Pagination, Filter, Sortierung

Cursor-Pagination als Default (`?cursor=`, `meta.next_cursor`) — bei wachsenden Tabellen sind Offsets sowohl langsam als auch anomal (Verschiebung bei Einfügungen). Offset-Pagination (`?page=`) nur, wo UI-artige Sprungnavigation extern gebraucht wird und die Tabelle klein bleibt (Regeln, Instanzen). Seitengröße `?per_page=` (Default 50, Max 200). Filter als benannte Query-Parameter, dokumentiert pro Route; kein generischer Filter-DSL (die Rule Engine ist der Ort für komplexe Bedingungen, nicht die URL). Sortierung `?sort=field` / `?sort=-field`, nur über indizierte Felder (Whitelist pro Route — ein Sort-Parameter erzwingt nie einen Seq-Scan).

## Fehlerformat

RFC 9457 (Problem Details), durchgängig:

```json
{
  "type": "https://mediaforge.dev/problems/mapping-conflict",
  "title": "Mapping-Konflikt",
  "status": 409,
  "detail": "Playlist 00004 hat bereits ein bestätigtes Ganz-Playlist-Mapping.",
  "instance": "/api/v1/disc-mappings/01J8…/confirm",
  "code": "disc.mapping_conflict",
  "errors": null
}
```

`code` ist der maschinenlesbare, stabile Fehlercode (Namensraum `modul.fehler`); `errors` trägt bei 422 die Feld-Validierungsfehler (Laravel-Format). Statuscodes kanonisch: 400 (Syntax), 401 (Token), 403 (Scope/Rolle/Policy), 404 (auch für existierende, aber nicht sichtbare Ressourcen — kein Existenz-Orakel), 409 (Zustandskonflikt, z. B. Unique-Verletzungen der Fachlogik), 422 (Validierung), 429 (Rate-Limit, mit `Retry-After`), 500/503. Interne Fehlerdetails (Stacktraces, SQL) erscheinen nie in Responses (Debug-Modus ist API-seitig wirkungslos).

## Asynchrone Operationen

Alles Langlaufende folgt einem Muster (Regel 9): Der anstoßende POST antwortet `202 Accepted` mit einer Operations-Referenz:

```json
{"operation": {"kind": "job", "id": "01J8…", "status_url": "/api/v1/operations/01J8…"}}
```

`GET /api/v1/operations/{ulid}` liefert den einheitlichen Status aus `job_progress`/`workflow_instances` (Phase, done/total, outcome, Fehlerdetail) — externe Konsumenten pollen diese eine Route statt modulspezifischer Status-Endpunkte. Polling-Etikette per Header (`Retry-After` in der 202-Antwort als Empfehlung).

## Rate-Limiting

Pro Token: 300 Anfragen/min Standard, `playback:report` 600 Events/min (Disc-Engine-Regel), Schreibrouten 60/min. Antwort-Header `RateLimit-*` (draft-ietf-Standard). Limits sind Settings (Betreiber mit Automatisierungs-Lasten erhöhen bewusst), Redis-gestützt.

## Idempotenz externer Schreibzugriffe

POST-Routen, die Ressourcen erzeugen oder teure Operationen anstoßen, akzeptieren `Idempotency-Key` (Client-ULID im Header): Wiederholung mit gleichem Schlüssel liefert die ursprüngliche Antwort statt Doppelwirkung (Speicherung 24 h in Redis mit DB-Fallback für Operations-Starts). Für das Player-Protokoll übernehmen die Event-ULIDs diese Rolle (Disc-Engine). Konsumenten mit Retry-Logik (das Kodi-Add-on, CLI-Skripte) sind damit gefahrlos.

## Dokumentation

OpenAPI 3.1-Spezifikation, generiert aus Code-Attributen an den Controllern (Single Source: der Code), ausgeliefert unter `/api/v1/openapi.json` plus gerenderte Referenz im Admin-UI. CI prüft: jede registrierte Route ist dokumentiert, jedes dokumentierte Schema validiert gegen die Response-Resources (Contract-Drift bricht den Build).

## Security

Zusätzlich zu Auth/Scopes: CORS Default aus (API ist nicht für Browser-Fremdorigins gedacht; explizite Origin-Whitelist als Setting für bewusste Ausnahmen). Keine API-Route liefert absolute Dateisystempfade oder interne URLs (die Pfad-Regel des Kernschemas endet nicht an der Datenbank); signierte Kurzzeit-URLs sind das einzige Muster für Dateizugriff (Audio-Vorschau, Vergleichsausschnitte). Auth-Fehler sind verzögerungskonstant (kein Timing-Orakel über Token-Existenz). Alle Mutationen tragen den Token-User als Audit-Actor — API-Änderungen sind im Audit von UI-Änderungen unterscheidbar (`context.channel='api'`, Token-Name).

## Tests

Contract-Tests: OpenAPI-Schema gegen echte Responses aller Routen (Fixture-Bestand); Fehlerformat-Konsistenz (jede Fehlerquelle liefert Problem Details mit `code`); Scope-Matrix (jede Route × jeder Scope ⇒ erwartetes 403/2xx als Tabellentest); Pagination-Invarianten (Cursor-Stabilität bei Einfügungen); Idempotency-Key-Verhalten; Rate-Limit-Header.

## ADR-Verweise

Setzt die Inertia/REST-Trennung aus [ADR-0001](../adr/0001-technology-stack.md) um; keine eigene ADR nötig (die Einzelentscheidungen sind konventionell und hier normativ fixiert).

## Offene Punkte

* **Bulk-Endpunkte** (Massen-Statusabfrage für Automatisierung): erst bei nachgewiesenem externem Bedarf — Bulk verführt zur UI-Nutzung der API.
* **GraphQL**: bewusst nicht (Masterdatei, Stash-Analyse); festgehalten, falls die Frage wiederkehrt.
* **Webhook-Ausgang** (MediaForge ruft externe URLs bei Events): als `notify`-Kanal der Rule Engine angerissen; ein generisches Outbound-Webhook-System ist unspezifiziert.
