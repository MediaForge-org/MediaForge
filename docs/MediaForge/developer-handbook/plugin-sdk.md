# Plugin SDK

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [architecture/overview.md](../architecture/overview.md) (Modulgrenzen), [connectors/connector-sdk.md](../connectors/connector-sdk.md) (Abgrenzung), [architecture/security.md](../architecture/security.md) (Vertrauensmodell). Zielgruppe dieses Kapitels: Dritt-Entwickler und die MediaForge-Entwickler, die die Erweiterungspunkte pflegen.

## Motivation

MediaForge kann nicht jede Nische selbst bedienen: exotische Metadaten-Provider, lokale Verlags-APIs für Kapitelquellen, Spezial-Detektoren für ungewöhnliche Ordnerschemata, zusätzliche Notification-Kanäle, weitere Backup-Ziele. Die First-Party-Connectoren bleiben MediaForge-Module (Vertrauensgrenze des [ADR-0002](../adr/0002-modular-monolith.md)); das Plugin SDK ist das Ventil für **Fremdcode** — mit einem ehrlicheren Vertrauensmodell als die Referenzprojekte: Jellyfin-Plugins laufen mit Vollzugriff im Serverprozess (die Negativ-Referenz aus dem Connector-SDK-Kapitel); MediaForge-Plugins laufen im Prozess, aber gegen **enge, versionierte Verträge mit deklarierten Fähigkeiten** — kein Sandkasten-Theater, das PHP nicht halten kann, sondern klare Grenzen plus informierte Betreiber-Entscheidung.

## Problemstellung

**PHP kann nicht sandboxen.** In-Process-Code hat technisch Vollzugriff; jede „Sandbox" wäre Illusion. Die ehrliche Architektur: (a) die Erweiterungspunkte so schneiden, dass Plugins mit wenigen, schmalen Interfaces auskommen; (b) Fähigkeiten deklarieren und anzeigen (der Betreiber entscheidet informiert); (c) den gefährlichsten Bedarf (externe Prozesse, eigene Binärabhängigkeiten) auf einen Out-of-Process-Pfad lenken.

**API-Stabilität.** Plugins überleben Releases nur, wenn die Verträge es tun. Das SDK braucht eine eigene, semantisch versionierte Vertragsfläche (`mediaforge/plugin-contracts` als Composer-Paket) — Plugins hängen an Contracts-Major-Versionen, nie an MediaForge-Interna.

**Herkunfts-Disziplin.** Plugin-Daten (Metadaten, Kapitelquellen, Vorschläge) unterliegen denselben Regeln wie alles andere: Herkunftskennzeichnung, keine stille Beförderung zum aktiven Stand, Audit. Ein Plugin darf das nicht umgehen können, ohne dass es auffällt.

## Architekturentscheidung

**Erweiterungspunkte (geschlossener Katalog, wächst per Release):**

| Extension Point | Interface (Contracts-Paket) | Beispiel |
|---|---|---|
| Metadaten-Provider | `MetadataProviderInterface` | lokaler Verlags-Katalog |
| Kapitelquellen-Parser | `ChapterSourceParserInterface` ([Assembler](../modules/audiobook-assembler.md)-Registry) | proprietäres Kapitel-Sidecar-Format |
| Kandidaten-Detektor | `CandidateDetectorInterface` (Scan-Pipeline) | Spezial-Ordnerschema |
| Rule-Prädikat / -Aktion | `PredicateInterface` / `RuleActionInterface` ([Rule Engine](../modules/rule-engine.md)-Registries, Aktions-Verbotsliste gilt unverändert) | Prädikat über Plugin-Daten |
| Quality-/Health-Check | die jeweiligen Registry-Interfaces | Verlags-API-Erreichbarkeit |
| Notification-Kanal | `NotificationChannelInterface` ([Monitoring](../modules/health-monitoring.md)) | Matrix/Signal |
| Backup-Ziel | `BackupTargetInterface` ([Backup](../modules/backup-restore.md)) | S3-kompatibel |
| Dashboard-Card | `DashboardCardInterface` | Plugin-Statusfläche |

Bewusst **nicht** erweiterbar: Watch-State-Schreibwege, Mapping-Bestätigungen, Chapter-Set-Aktivierung, Audit — die invariantentragenden Pfade bleiben First-Party (dieselbe Verbotsliste wie bei Rule-Aktionen, hier auf Interface-Ebene: es gibt schlicht keine Contracts dafür).

**Plugin-Paket**: ein Composer-Paket mit `mediaforge-plugin.json`-Manifest (Name, Version, Contracts-Version, Extension Points, **Capabilities**: `network_egress` (Ziel-Hosts!), `filesystem_read` (Scopes), `queue_jobs`, `settings_schema`) und einem Service Provider, der ausschließlich über die `PluginRegistrar`-Fassade registriert (direkter Container-Zugriff ist per Architekturtest des Plugin-Loaders unterbunden — nicht wasserdicht gegen Böswilligkeit, aber ein klarer Verstoß ist automatisiert erkennbar und disqualifizierend). Installation: Composer-basiert (`composer require` + `mediaforge:plugin:enable`) — kein Runtime-Upload von Code (die Jellyfin-Lektion); Aktivierung zeigt die deklarierten Capabilities zur Bestätigung und auditiert sie.

**Out-of-Process-Pfad**: Plugins mit eigenen Binärbedürfnissen (Spezial-Parser, ML) implementieren das media-tools-Kommandodienst-Muster: eigener Container, interner HTTP-Vertrag, vom Plugin nur der schmale Client — die Doku macht diesen Pfad zum empfohlenen Standard für alles Nicht-Triviale (Prozessgrenze statt Vertrauens-Streckung, konsistent mit [ADR-0002](../adr/0002-modular-monolith.md)).

**Daten-Disziplin technisch verankert**: Plugin-gelieferte Werte laufen durch dieselben Actions wie alle anderen (`source`-Kennzeichnung: `import`/`provider` mit `origin_detail='plugin:<name>@<version>'`); der Audit-Actor während Plugin-Code-Ausführung ist `plugin:<name>` (Kontext-Stack des [Audit-Moduls](../modules/audit.md) — Plugin-Wirkungen sind als solche rückverfolgbar). Plugin-eigene Persistenz: eigener Tabellen-Namespace (`plugin_<name>_*`, Migrations-Konvention) — Schreiben in Kern-Tabellen an Actions vorbei ist der disqualifizierende Verstoß.

## Alternativen

**Echte Prozess-Sandbox für alle Plugins** (jedes Plugin ein Container): maximal sicher, aber die leichten Erweiterungspunkte (ein Parser, ein Prädikat) würden absurd schwer — der zweigleisige Schnitt (in-process für Schmales, out-of-process für Schweres) ist der dokumentierte Kompromiss. **Skript-Sprache eingebettet** (Lua): Sandbox-Illusion in anderer Farbe, Ökosystem-Bruch. **Kein Plugin-System**: staut Nischenbedarf als Feature-Requests — genau was das Ventil verhindern soll. **Runtime-Code-Upload** (Jellyfin-Stil): Supply-Chain-Alptraum, verworfen.

## Datenmodell und SQL-Schema

```sql
CREATE TABLE plugins (
    id             CHAR(26) PRIMARY KEY,
    name           TEXT        NOT NULL,             -- Composer-Paketname
    version        TEXT        NOT NULL,
    contracts_version TEXT     NOT NULL,
    manifest       JSONB       NOT NULL,             -- mediaforge-plugin.json-Kopie (Capabilities!)
    status         TEXT        NOT NULL DEFAULT 'disabled'
        CHECK (status IN ('disabled','enabled','failed','incompatible')),
    enabled_by     CHAR(26)    REFERENCES users(id) ON DELETE SET NULL,
    enabled_at     TIMESTAMPTZ,
    failure_detail TEXT,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (name)
);
```

## Laravel-Klassen

| Klasse | Typ | Vertrag |
|---|---|---|
| `PluginRegistrar` | Fassade | einzige Registrierungs-Schnittstelle (Extension-Point-Registries dahinter) |
| `PluginLoader` | Service | Manifest-Validierung, Contracts-Versionsprüfung, Capability-Erfassung, Boot-Isolation (werfendes Plugin ⇒ `failed`, System bootet weiter — die Dashboard-Fehler-Kachel-Mechanik auf Boot-Ebene) |
| `EnablePlugin`, `DisablePlugin` | Action | Capability-Anzeige/Bestätigung; Audit |
| `PluginHealthCheck` | Health-Check | failed/incompatible Plugins als Befund |
| `mediaforge:plugin:list/enable/disable` | Artisan | CLI-Pfade |

## API und UI

`GET /api/v1/plugins` (admin); Enable/Disable-POSTs. UI: Plugin-Liste im Admin (Name, Version, Capabilities als Badges — `network_egress` mit Ziel-Hosts prominent, Status, Settings-Formular aus dem deklarierten Schema). Kein Plugin-Marketplace in Version 1 (Kuratierungs-/Haftungsfragen; die Doku listet bekannte Plugins).

## Edge Cases

* **Contracts-Major-Sprung**: Plugins mit alter Major werden `incompatible` (nicht geladen, Befund) statt zur Laufzeit zu brechen; die Contracts-Changelog-Disziplin ist Release-Pflicht.
* **Plugin wirft im Betrieb** (nicht nur Boot): Registry-Aufrufe sind isoliert (Try-Catch + Zähler); wiederholte Fehler eskalieren zum Health-Befund und deaktivieren den betroffenen Extension Point des Plugins temporär — ein kaputter Parser stoppt nicht den Scan.
* **Zwei Plugins, gleicher Extension-Zweck** (zwei Parser fürs gleiche Format): Registries erlauben Mehrfachregistrierung, wo die Fach-Semantik es trägt (Parser: alle laufen, Chapter Sets koexistieren); wo nicht (Backup-Ziel gleichen Namens): Kollisionsfehler beim Enable.
* **Plugin-Deinstallation mit Datenbestand**: `plugin_<name>_*`-Tabellen bleiben (Daten gehören dem Betreiber); `mediaforge:plugin:purge <name>` räumt explizit, auditiert.

## Performance

Boot-Kosten pro Plugin gemessen und im Plugin-UI angezeigt (Transparenz statt Limits); Registry-Aufrufe tragen die Fehler-Isolation als einzigen Overhead (Mikrosekunden).

## Security

Das Vertrauensmodell in einem Satz: **Ein aktiviertes Plugin ist Code des Betreibers** — das SDK macht die Entscheidung informiert (Capabilities, Audit-Actor, Verstoß-Erkennung), nicht überflüssig. Composer-Bezug bedeutet Supply-Chain-Verantwortung beim Betreiber (die Doku empfiehlt Version-Pinning und Quell-Prüfung); der Out-of-Process-Pfad ist die Empfehlung für alles, dem man nur halb traut. Netzwerk-Egress deklarierter Hosts wird nicht technisch erzwungen (in-process unmöglich), aber deklariert, angezeigt und im SSRF-Schutz der HTTP-Fassade geprüft, wenn das Plugin die SDK-HTTP-Clients nutzt (was die Contracts nahelegen: eigene HTTP-Clients sind ein Review-Signal).

## Tests

Contracts-Paket mit eigener Testbasis, die Plugin-Autoren erben (`PluginTestCase`: Boot im Fake-Kern, Registry-Verträge, Fixture-Helfer). Kern-seitig: Loader-Isolation (werfendes Plugin), Inkompatibilitäts-Matrix, Capability-Audit, Purge-Verhalten, Architekturtest der Registrar-Umgehung.

## ADR-Verweise

[ADR-0012](../adr/0012-plugin-trust-model.md) (Plugin-Vertrauensmodell: informierte In-Process-Verträge + Out-of-Process-Empfehlung statt Sandbox-Illusion).

## Offene Punkte

* **Contracts-Erstumfang**: die Tabelle oben ist der Zielkatalog; welcher Teil zum Release stabil genug ist, entscheidet die Implementierungserfahrung der First-Party-Module (die dieselben Registries nutzen — der beste Stabilitätstest).
* **Plugin-Verzeichnis/Kuratierung**: bewusst nach Version 1.
* **Frontend-Erweiterungspunkte** (eigene React-Seiten): schwierig (Build-Integration); die erste freigegebene Plugin-Phase bietet nur klar begrenzte Dashboard-Cards und Settings-Formulare aus Schemas.
