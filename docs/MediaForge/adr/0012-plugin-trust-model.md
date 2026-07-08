# ADR-0012: Plugin-Vertrauensmodell — informierte In-Process-Verträge statt Sandbox-Illusion

Status: accepted · Bezug: [developer-handbook/plugin-sdk.md](../developer-handbook/plugin-sdk.md)

## Kontext

MediaForge braucht Erweiterbarkeit durch Fremdcode (Nischen-Provider, Parser, Kanäle), aber PHP bietet keine tragfähige In-Process-Sandbox: Geladener Code hat technisch Vollzugriff. Jellyfin zeigt die Folgen des Vollzugriffs-Modells ohne Transparenz; eine erzwungene Container-Isolation für jede Kleinsterweiterung würde das Plugin-Ökosystem ersticken.

## Entscheidung

Zweigleisiges Modell: **In-Process-Plugins** gegen ein schmales, semantisch versioniertes Contracts-Paket mit geschlossenem Extension-Point-Katalog, deklarierten Capabilities (Egress-Hosts, FS-Scopes), Registrierung nur über die `PluginRegistrar`-Fassade, Audit-Actor `plugin:<name>`, Composer-Bezug statt Runtime-Upload, Boot-/Laufzeit-Fehlerisolation. Invariantentragende Pfade (Watch-State, Mapping-Bestätigung, Set-Aktivierung, Audit) haben keine Contracts und sind damit nicht erweiterbar. **Out-of-Process** (eigener Container nach dem media-tools-Muster) ist der dokumentierte Standardweg für alles mit Binärabhängigkeiten oder halbem Vertrauen. Ein aktiviertes Plugin gilt ausdrücklich als Code des Betreibers — das SDK macht die Entscheidung informiert (Capability-Anzeige, Verstoß-Erkennung), nicht überflüssig.

## Konsequenzen

* Keine falsche Sicherheitszusage; die Doku sagt klar, was Aktivierung bedeutet.
* Leichte Erweiterungen bleiben leicht (ein Interface, ein Manifest); schwere wandern hinter Prozessgrenzen — konsistent mit ADR-0002.
* Contracts-Versionierung wird Release-Pflicht; Inkompatibilität deaktiviert Plugins statt Laufzeitbrüche zu riskieren.
* Automatisiert erkennbare Verstöße (Registrar-Umgehung, Kern-Tabellen-Schreibzugriff) disqualifizieren ein Plugin formal.

## Erwogene Alternativen

Container pro Plugin (erstickt Kleinsterweiterungen), eingebettete Skriptsprache (Sandbox-Illusion, Ökosystem-Bruch), Runtime-Code-Upload (Supply-Chain-Alptraum), kein Plugin-System (Feature-Request-Stau auf den Kern).
