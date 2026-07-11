# Connector SDK

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [architecture/overview.md](../architecture/overview.md) (Modulgrenzen, Jobs), [database/core-schema.md](../database/core-schema.md) (Provider-IDs, Watch-State-Actions, Reviews), [modules/audit.md](../modules/audit.md) (Actor-Kontext `connector`). Konsumenten: alle Kapitel unter `connectors/`.

## Motivation

MediaForge integriert lokale Kernsysteme (Jellyfin und Audiobookshelf), optionale Ergänzungssysteme wie Sonarr, Radarr, Readarr, Lidarr und Prowlarr, optionale Importquellen wie Stash sowie die Immich-Referenz und External-Player. Ohne gemeinsames Fundament entstünde mehrfach dieselbe Infrastruktur — Authentifizierung, Rate-Limiting, Sync-Cursor, Fehlerzähler, Konfliktauflösung, Outbox — in Varianten mit unterschiedlichen Fehlerprofilen. Das SDK zieht diese Infrastruktur in eine geprüfte Basis und reduziert einen konkreten Connector auf das, was wirklich systemspezifisch ist: API-Client, Datenübersetzung, Fähigkeits-Deklaration. Zugleich erzwingt das SDK die Architekturregel 3 strukturell: Connectoren, die nur SDK-Verträge und Core-Actions kennen, **können** keine Core-Geschäftslogik enthalten.

## Problemstellung

**Verschiedenheit der Gegenstellen.** Die Zielsysteme unterscheiden sich in allem: Auth (API-Key-Header, Token-Login, Basic), Änderungserkennung (Jellyfin: Webhooks + Abfrage; *arr: nur Polling; ABS: Sessions-API), Schreibfähigkeit (Jellyfin/ABS: bidirektional; Prowlarr: praktisch nur lesend), Semantik (Jellyfin-„Played" vs. ABS-„Progress" vs. Stash-„o-counter"). Das SDK muss diese Verschiedenheit deklarierbar machen, statt sie zu verstecken — ein Connector, der nur lesen kann, darf vom Sync-Rahmen nie zum Schreiben aufgefordert werden.

**Konflikt und Kausalität.** Bidirektionaler Sync erzeugt zwangsläufig Konflikte (beide Seiten ändern denselben Watch-State) und Echo-Risiken (MediaForge schreibt nach Jellyfin; der nächste Poll liest die eigene Änderung als „fremde" zurück; im schlimmsten Fall oszilliert ein Zustand endlos). Beides braucht systematische Antworten im Rahmen, nicht in jedem Connector einzeln.

**Teilausfall als Normalzustand.** Gegenstellen sind Heimserver-Software: mal aus, mal mitten im Update, mal mit kaputtem Reverse Proxy. Der Sync-Rahmen muss degradieren (Backoff, Health-Status, Weiterarbeit der übrigen Connectoren) statt zu eskalieren, und nach Tagen Ausfall sauber aufholen (Cursor, nicht „alles neu").

**Identitätsabbildung.** Fremde IDs sind flüchtig (Neuinstallation der Gegenstelle vergibt alles neu). Die Abbildung läuft ausnahmslos über `provider_ids` ([ADR-0003](../adr/0003-provider-id-mapping.md)); das SDK liefert die Matching-Unterstützung, wenn IDs fehlen (Provider-übergreifende Schlüssel wie TMDB-IDs, Pfad-Heuristiken, Titel+Jahr-Matching mit Review-Fallback).

## Analyse bestehender Lösungen

Die *arr-Familie selbst ist das beste Studienobjekt: Ihre „Download Client"- und „Import List"-Abstraktionen zeigen ein reifes Fähigkeits-/Settings-Schema pro Integration (deklarative Felder, Test-Button, Health-Checks) — das Settings- und Capability-Modell des SDK folgt diesem Vorbild. **Home Assistant** demonstriert das ausgereifteste Integrations-SDK des Selfhosting-Ökosystems (Config Flows, Entity-Abstraktion, Diagnostics); übernommen wird die Idee verpflichtender Diagnose-Schnittstellen pro Connector. **Jellyfin-Plugins** zeigen das Gegenmodell — Plugins laufen im Serverprozess mit Vollzugriff; genau deshalb bleibt bei MediaForge der Connector im Monolith-Modul mit Architektur-Test-Grenzen statt als beliebiger Fremdcode (Fremdcode-Erweiterbarkeit ist Sache des [Plugin SDK](../developer-handbook/plugin-sdk.md), geplant, mit engerem Sandbox-Vertrag).

## Architekturentscheidung

Ein Connector ist ein Modul unter `app/Connectors/<Name>`, das genau vier Dinge beisteuert:

1. **Manifest** — statische Selbstbeschreibung: Fähigkeiten (Capabilities), Settings-Schema, unterstützte Entitätstypen, Sync-Richtungen, Provider-Kennungen (`provider_ids.provider`-Werte, die dieser Connector verwaltet).
2. **Client** — die HTTP-Kapselung der Gegenstelle (Auth, Endpunkte, Fehlerklassifikation transient/permanent). Nur der Client kennt die fremde API.
3. **Übersetzer** — pure Mapper zwischen Fremd-DTOs und kanonischen SDK-DTOs (`CanonicalMediaRef`, `CanonicalPlayState`, …).
4. **Handler** — Implementierungen der SDK-Sync-Verträge (unten), die Client + Übersetzer verdrahten und ausschließlich Core-Actions aufrufen.

Alles andere — Instanzverwaltung, Scheduling, Cursor, Outbox, Rate-Limiting, Konfliktstrategie, Health, Audit-Actor — liegt im SDK (`app/Connectors/Sdk`). Die zentralen Verträge:

```php
namespace App\Connectors\Sdk;

interface ConnectorManifest
{
    public function key(): string;                       // 'jellyfin'
    public function capabilities(): CapabilitySet;       // s. u.
    public function settingsSchema(): SettingsSchema;    // deklarative Felder inkl. Secrets
    public function providerKeys(): array;               // ['jellyfin_item','jellyfin_user']
}

final readonly class CapabilitySet
{
    public function __construct(
        public bool $ingestPlayState,      // liest Wiedergabezustände
        public bool $egressPlayState,      // schreibt Wiedergabezustände
        public bool $ingestCatalog,        // liest Katalog/Bibliothek
        public bool $egressCatalog,        // schreibt Katalog-Metadaten
        public bool $supportsWebhooks,     // Push-Änderungserkennung
        public bool $supportsCursorSync,   // inkrementeller Abruf ab Wasserzeichen
        public RateLimit $rateLimit,       // Requests/Intervall gegen die Gegenstelle
    ) {}
}

interface IngestHandler
{
    /** Liefert Änderungen seit $cursor als Strom kanonischer Ereignisse. */
    public function pull(ConnectorInstance $i, ?SyncCursor $cursor): ChangeStream;
}

interface EgressHandler
{
    /** Wendet eine kanonische Änderung auf die Gegenstelle an. Idempotent. */
    public function push(ConnectorInstance $i, OutboxItem $item): PushResult;
}

interface DiagnosticsProvider
{
    public function test(ConnectorInstance $i): DiagnosticsReport;  // Verbindung, Version, Rechte
}
```

**Instanzen statt Singletons:** Ein Connector-Typ kann mehrfach konfiguriert sein (zwei Jellyfin-Server, vier *arr-Instanzen). Alle Zustandsdaten hängen an der `connector_instances`-Zeile, nie am Typ.

**Sync-Topologie:** Pro Instanz und Richtung läuft ein `ShouldBeUnique`-Sync-Job (Queue `connector`), Scheduler-getrieben (Intervall aus Settings) plus Webhook-getriggert, wo verfügbar (Webhooks verkürzen die Latenz, ersetzen aber nie den Poll — verpasste Webhooks holt der nächste Cursor-Lauf auf; Webhook-Payloads werden nur als Trigger benutzt, nie als Datenquelle, weil ihre Semantik gegenüber der Abfrage-API regelmäßig verkürzt ist).

**Echo-Unterdrückung:** Jede Egress-Schreibung protokolliert (Outbox, unten) das erwartete Fremdsystem-Resultat (`expected_state_hash`). Der Ingest vergleicht eingehende Änderungen gegen die jüngsten eigenen Schreibungen derselben Entität (Zeitfenster, Default 10 min) und verwirft Echos — auditiert als verworfene Operation mit Grund `echo_suppressed`, damit die Unterdrückung selbst diagnostizierbar bleibt.

**Konfliktstrategie:** Kanonische Ereignisse tragen `occurred_at` der Gegenstelle (soweit verfügbar; sonst Empfangszeit mit Kennzeichnung). Der Core entscheidet nach der pro Instanz konfigurierten Strategie: `latest_wins` (Default; Vergleich über `occurred_at` gegen die Watch-State-Historie), `mediaforge_wins`, `remote_wins`, `review` (Konflikt ⇒ Review-Task `connector_conflict`). Die Entscheidung fällt in einer Core-Action (`ResolveWatchStateConflict`), nie im Connector (Architekturregel 3); der Connector liefert nur die Fakten.

## Alternativen

**Connectoren als externe Prozesse/Container** (Sprach-agnostisch, crash-isoliert): verworfen für die First-Party-Connectoren — die Betriebs- und Vertragsgrenzen-Kosten (siehe [ADR-0002](../adr/0002-modular-monolith.md)) überwiegen; genau dieser Weg bleibt dem Plugin SDK für Fremdcode vorbehalten. **Generischer Feld-Mapping-Konfigurator** (Sync deklarativ per YAML statt Code): an der semantischen Verschiedenheit der Gegenstellen gescheitert — „Played" ist eben nicht „Progress ≥ 90 %"; Übersetzung ist Code mit Tests, keine Konfiguration. **Event-Bus-Spiegelung** (alle Fremdereignisse roh einlagern, später interpretieren): als Vollarchitektur verworfen (Speicher- und Komplexitätskosten), aber als Muster punktuell übernommen — der Ingest hält Rohereignisse kurzzeitig (`connector_ingest_log`, 14 Tage) für Diagnose und Konflikt-Nachvollzug.

## Datenmodell und SQL-Schema

```sql
CREATE TABLE connector_instances (
    id             CHAR(26) PRIMARY KEY,
    connector_key  TEXT        NOT NULL,             -- 'jellyfin', 'sonarr', …
    name           TEXT        NOT NULL,             -- "Wohnzimmer-Jellyfin"
    base_url       TEXT        NOT NULL,
    settings       JSONB       NOT NULL DEFAULT '{}',   -- Nicht-Secrets gemäß SettingsSchema
    secrets_ref    TEXT        NOT NULL,             -- Verweis in den verschlüsselten Secret-Store
    enabled        BOOLEAN     NOT NULL DEFAULT true,
    conflict_strategy TEXT     NOT NULL DEFAULT 'latest_wins'
        CHECK (conflict_strategy IN ('latest_wins','mediaforge_wins','remote_wins','review')),
    health_status  TEXT        NOT NULL DEFAULT 'unknown'
        CHECK (health_status IN ('unknown','healthy','degraded','unreachable','auth_failed')),
    health_detail  TEXT,
    last_healthy_at TIMESTAMPTZ,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (connector_key, name)
);

CREATE TABLE connector_sync_states (
    id             CHAR(26) PRIMARY KEY,
    instance_id    CHAR(26)    NOT NULL REFERENCES connector_instances(id) ON DELETE CASCADE,
    stream         TEXT        NOT NULL,             -- 'playstate','catalog','sessions', …
    direction      TEXT        NOT NULL CHECK (direction IN ('ingest','egress')),
    cursor         JSONB,                            -- Wasserzeichen (Format je Connector dokumentiert)
    last_run_at    TIMESTAMPTZ,
    last_success_at TIMESTAMPTZ,
    consecutive_failures INTEGER NOT NULL DEFAULT 0,
    backoff_until  TIMESTAMPTZ,
    stats          JSONB       NOT NULL DEFAULT '{}',   -- Zähler des letzten Laufs (Diagnose)
    UNIQUE (instance_id, stream, direction)
);

CREATE TABLE connector_outbox (
    id             CHAR(26) PRIMARY KEY,
    instance_id    CHAR(26)    NOT NULL REFERENCES connector_instances(id) ON DELETE CASCADE,
    stream         TEXT        NOT NULL,
    subject_type   TEXT        NOT NULL,             -- kanonische Entität (Morph-Alias)
    subject_id     CHAR(26)    NOT NULL,
    payload        JSONB       NOT NULL,             -- kanonisches Änderungs-DTO, serialisiert
    expected_state_hash TEXT,                        -- für Echo-Unterdrückung
    status         TEXT        NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending','in_flight','delivered','failed','superseded')),
    attempts       INTEGER     NOT NULL DEFAULT 0,
    next_attempt_at TIMESTAMPTZ,
    last_error     TEXT,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    delivered_at   TIMESTAMPTZ
);

CREATE INDEX connector_outbox_due
    ON connector_outbox (instance_id, status, next_attempt_at)
    WHERE status IN ('pending','failed');

-- Neuere Änderung derselben Entität ersetzt ältere unzugestellte (Koaleszenz):
CREATE INDEX connector_outbox_subject
    ON connector_outbox (instance_id, stream, subject_type, subject_id)
    WHERE status IN ('pending','failed');

CREATE TABLE connector_ingest_log (
    id             CHAR(26) PRIMARY KEY,
    instance_id    CHAR(26)    NOT NULL REFERENCES connector_instances(id) ON DELETE CASCADE,
    stream         TEXT        NOT NULL,
    remote_ref     TEXT,                             -- Fremd-ID des Ereignisses, falls vorhanden
    payload        JSONB       NOT NULL,             -- Rohereignis (Diagnose, 14-Tage-Retention)
    outcome        TEXT        NOT NULL
        CHECK (outcome IN ('applied','echo_suppressed','conflict_review','unmatched','error')),
    detail         TEXT,
    received_at    TIMESTAMPTZ NOT NULL DEFAULT now()
) PARTITION BY RANGE (received_at);
```

Die **Outbox** setzt die Send-Idempotenz des Fundament-Job-Vertrags um: Core-Events (z. B. `EpisodeWatched`) werden von SDK-Listenern in Outbox-Items übersetzt — nur für Instanzen, deren Manifest die Capability deklariert und deren Mapping (`provider_ids`) das Subjekt kennt. Der Egress-Job arbeitet fällige Items ab (`push()` mit `attempts`/Backoff); Koaleszenz ersetzt ältere unzugestellte Änderungen derselben Entität (`superseded`) — nach drei Tagen Jellyfin-Ausfall wird ein Watch-State einmal geschrieben, nicht vierzigmal in historischer Reihenfolge. Secrets liegen nie in `settings`, sondern verschlüsselt im Secret-Store (Laravel-Encryption, Schlüssel aus `.env`; `secrets_ref` referenziert den Eintrag) — `connector_instances` ist damit gefahrlos exportier- und auditierbar.

## Matching-Unterstützung

Wenn ein Ingest-Ereignis keine bekannte Provider-ID trifft (`unmatched`), greift die SDK-Matching-Kaskade: (1) **Fremd-Provider-Schlüssel** — die Gegenstelle liefert oft selbst Provider-IDs (Jellyfin kennt TMDB/TVDB-IDs seiner Items); Treffer über `provider_ids` anderer Provider sind die stärkste Brücke und erzeugen nebenbei das fehlende Mapping (`source='connector'`, Confidence 0.95). (2) **Pfad-Abgleich** — teilen MediaForge und Gegenstelle Medienpfade (häufig: gleiche NAS-Mounts), matcht der normalisierte Pfad gegen `files` exakt. (3) **Titel+Jahr+Typ-Ähnlichkeit** (Trigram) — nur als Review-Vorschlag (`media_match`-Task), nie automatisch: Auto-Matching auf Textähnlichkeit ist die klassische Quelle vergifteter Kataloge. Unmatchte Ereignisse bleiben im Ingest-Log und werden nach Mapping-Ergänzung vom nächsten Lauf erneut angetroffen (Cursor bleibt hinter unverarbeiteten Ereignissen, wo die Gegenstelle das erlaubt; sonst Re-Pull-Fenster).

## Laravel-Klassen

| Klasse | Typ | Vertrag |
|---|---|---|
| `ConnectorRegistry` | Service | sammelt Manifeste (Service-Provider-Registrierung); liefert Instanz-Konfiguration + Handler |
| `ConnectorInstance`, `ConnectorSyncState`, `ConnectorOutboxItem` | Model | Zustandsverwaltung wie Schema |
| `RunConnectorIngestJob` | Job (`connector`, unique je Instanz+Stream) | zieht `ChangeStream`, wendet Kaskade an (Echo → Konflikt → Action), setzt Cursor transaktional **nach** erfolgreicher Verarbeitung des Batches |
| `RunConnectorEgressJob` | Job (`connector`, unique) | arbeitet fällige Outbox-Items ab; klassifiziert Fehler transient/permanent |
| `EnqueueOutboxListener` | Listener | Core-Events → Outbox-Items (capability- und mapping-gefiltert, koalesziert) |
| `CreateConnectorInstance`, `UpdateConnectorSettings`, `DisableConnectorInstance` | Action | Settings-Schema-Validierung, Secret-Verschlüsselung, Diagnostics-Testlauf; Audit |
| `ResolveWatchStateConflict` | Core-Action | Strategie-Anwendung; Audit mit beiden Sichten im Diff |
| `ConnectorHealthCheckJob` | Job (Scheduler) | `DiagnosticsProvider::test()` je Instanz; Health-Status-Übergänge als Events fürs Admin-Dashboard |

Der Audit-Actor ist während Ingest/Egress automatisch `connector:<key>:<instanz-ulid>` (Job-Middleware des SDK) — jede von einem Connector verursachte Änderung ist bis zur Instanz rückverfolgbar ([modules/audit.md](../modules/audit.md)).

## API-Endpunkte

| Route | Zweck | Rolle |
|---|---|---|
| `GET /api/v1/connectors` | Typen (Manifeste) + Instanzen mit Health | admin |
| `POST /api/v1/connectors/{key}/instances` | Instanz anlegen (inkl. Testlauf) | admin |
| `PUT /api/v1/connector-instances/{ulid}` | Settings ändern | admin |
| `POST /api/v1/connector-instances/{ulid}/test` | Diagnostics on demand | admin |
| `POST /api/v1/connector-instances/{ulid}/sync?stream=` | manuellen Lauf anstoßen | admin |
| `GET /api/v1/connector-instances/{ulid}/activity` | Sync-States, Outbox-Rückstau, Ingest-Log-Auszug | manager |
| `POST /api/v1/webhooks/{key}/{instanz-ulid}/{signatur}` | Webhook-Eingang (signierter Pfad, öffentlich erreichbar) | signiert |

## React-/Inertia-Komponenten und UI-Flows

**`Connectors/Index`** — Instanz-Karten mit Health-Ampel, letztem Sync, Outbox-Rückstau; „Instanz hinzufügen"-Flow rendert das Settings-Schema des Manifests generisch (Feldtypen, Secret-Felder maskiert, Pflichtprüfung serverseitig) und schließt mit dem Diagnostics-Testlauf ab (Erfolg zeigt Gegenstellen-Version und erkannte Rechte). **`Connectors/Activity`** — Betriebs-Sicht einer Instanz: Stream-Tabelle (Cursor-Alter, Fehlerserie, Backoff), Outbox-Liste mit manueller Zustell-Wiederholung, Ingest-Log-Browser mit Outcome-Filter (`unmatched`-Einträge verlinken direkt in den Matching-Review). Konflikt-Reviews erscheinen in der Fundament-Review-Inbox mit Gegenüberstellung beider Zustände und Ein-Klick-Anwendung einer Seite.

## Edge Cases

* **Gegenstelle neu installiert** (alle Fremd-IDs neu): Diagnostics erkennt den Identitätswechsel (Server-ID/Instanz-GUID weicht ab) ⇒ Instanz geht auf `degraded` mit Review „Neuverknüpfung nötig"; der Matching-Lauf über Fremd-Provider-Schlüssel baut die `provider_ids` neu auf, ohne Katalogschaden (genau der Fall, für den ADR-0003 existiert).
* **Uhrzeit-Drift der Gegenstelle**: `occurred_at`-basierte Konfliktentscheidung toleriert konfigurierbaren Skew (Default 5 min); jenseits dessen wird `latest_wins` zu `review` eskaliert — falsche Uhren dürfen keine stillen Sieger machen.
* **Rate-Limit-Antworten (429)**: Client-Fehlerklassifikation transient mit Retry-After-Respekt; der SDK-Rate-Limiter (Redis, pro Instanz) hält die Grundlast darunter.
* **Riesen-Erstsync** (Jellyfin mit 200k Items): Erstlauf ist ein ResumableJob mit Seiten-Checkpoints statt des normalen Sync-Jobs; der Cursor-Sync übernimmt erst nach Abschluss.
* **Gegenstelle liefert Duplikate/Reihenfolgeverletzungen im Änderungsstrom**: Verarbeitung ist per Entität idempotent (Vergleich gegen aktuellen Zustand vor Action-Aufruf — „keine Änderung" erzeugt keine Operation); Reihenfolge heilt die Watch-State-Historie über `occurred_at`.
* **Webhook-Flut** (Jellyfin-Bulk-Operation feuert tausende Hooks): Webhooks sind nur Trigger — sie debouncen auf „Sync-Lauf anstoßen, falls keiner läuft/ansteht" (Redis-Debounce 30 s), die Datenarbeit macht der Cursor-Lauf einmal.

## Performance

Die `connector`-Queue ist netzwerk-, nicht CPU-gebunden; Parallelität 4 mit per-Instanz-Rate-Limits. Outbox- und Sync-State-Tabellen bleiben klein (Koaleszenz, Delivered-Aufräumjob nach 30 Tagen); `connector_ingest_log` ist partitioniert mit 14-Tage-Retention. Cursor-Läufe verarbeiten in Batches (Default 500 Ereignisse) mit Transaktion pro Batch — ein Abbruch verliert maximal einen Batch, nie den Cursor-Stand davor.

## Security

Webhook-Endpunkte sind die einzige öffentlich erreichbare Connector-Fläche: Pfad-Signatur (HMAC über Instanz-ULID mit instanzspezifischem Secret) plus optionale Header-Verifikation, keine Payload-Verarbeitung (nur Trigger — ein gefälschter Webhook kann höchstens einen regulären, authentifizierten Poll auslösen). Ausgehende Verbindungen validieren TLS (selbstsignierte Zertifikate nur per explizitem Instanz-Setting mit Fingerprint-Pinning, nie global). Basis-URLs werden gegen SSRF-Muster geprüft (kein Redirect-Follow auf andere Hosts; interne MediaForge-Dienste als Ziel verweigert). Secrets: verschlüsselt at rest, maskiert in UI/Audit/Diagnostics, nie in `connector_ingest_log`-Payloads (Denyliste des Recorders greift auch hier).

## Tests

SDK-Kern gegen einen **Referenz-Fake-Connector** (Teil der Testsuite): deklariert alle Capabilities, simuliert Gegenstellen-Verhalten aus Szenario-Skripten (Ausfall, 429, Duplikate, Uhr-Drift, Identitätswechsel). Damit testbar ohne echte Systeme: Cursor-Semantik (Batch-Abbruch verliert nichts), Outbox-Koaleszenz und -Idempotenz (Doppelzustellung ⇒ ein Fremdsystem-Schreibvorgang), Echo-Unterdrückung (Roundtrip erzeugt keine Oszillation — als Endlos-Schleifen-Regressionstest), Konfliktmatrix (4 Strategien × 6 Konstellationen als Tabellentest), Health-Übergänge. Jeder konkrete Connector erbt eine Contract-Testbasis: „Manifest konsistent", „Übersetzer bijektiv auf Fixture-Paaren", „Fehlerklassifikation vollständig".

## ADR-Verweise

[ADR-0003](../adr/0003-provider-id-mapping.md) (Identitätsabbildung), [ADR-0002](../adr/0002-modular-monolith.md) (Connectoren als Module), [ADR-0006](../adr/0006-action-level-audit.md) (Connector-Actor). Setzt um: Architekturregeln 3, 9, 10.

## Offene Punkte

* **Kavita/Komga-Connectoren** sind im Manifest-Modell vorgesehen, aber unspezifiziert (bewusst nach den primären Jellyfin-/Audiobookshelf-Integrationen priorisiert, siehe Masterdatei).
* **Egress von Katalog-Metadaten** (MediaForge-Korrekturen zurück in Gegenstellen schreiben) ist als Capability modelliert, aber fachlich heikel (welches System hat Feldhoheit?); die Feld-Governance wird mit dem Enrichment-Kapitel spezifiziert, bis dahin bleiben First-Party-Connectoren katalog-lesend.
* **Payload-Verschlüsselung der Outbox** bei besonders sensiblen Gegenstellen ist im optionalen Stash-Import/Connector konkretisiert und bleibt für weitere Adult- oder Restricted-Integrationen verbindliches Muster.
