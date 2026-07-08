# Security-Gesamtkonzept

Security wird lokal gedacht: lokale Anmeldung, Rollen, Bibliotheksrechte, Adult-Sichtbarkeit, lokale API-Keys, Secrets, Audit Logs und sichere Defaults. Externe Metadatenquellen oder AI-Dienste sind optionale Integrationen und müssen gegen SSRF, Secret-Leaks und unbeabsichtigten Datenabfluss abgesichert werden. Der sichere Grundzustand funktioniert ohne Cloudkonto.

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Dieses Kapitel konsolidiert die in den Modulkapiteln verstreuten Security-Abschnitte zu einem Gesamtbild: Bedrohungsmodell, Identitäts- und Berechtigungsmodell, Härtungsebenen, Secrets, und die verbindlichen Querschnittsregeln. Modulspezifische Details bleiben in den Modulen; hier steht, was systemweit gilt und wo die Verantwortungen liegen.

## Bedrohungsmodell

MediaForge läuft im Heimnetz hinter einem Reverse Proxy, oft aber via VPN oder (gegen die Empfehlung) direkt exponiert. Die relevanten Angreiferklassen, absteigend nach Eintrittswahrscheinlichkeit:

1. **Feindliche Eingabedaten** — präparierte Medien (ISOs, Audio-Container, CUE/NFO-Sidecars), die Parser-Schwachstellen ausnutzen. Wahrscheinlichster Vektor, weil Medien aus unkontrollierten Quellen stammen.
2. **Kompromittierte Gegenstellen** — ein gehacktes Jellyfin/ABS/*arr im selben Netz, das über die Connector-Kanäle falsche Daten liefert oder MediaForge-Credentials missbraucht.
3. **Böswillige oder nachlässige Mitbenutzer** — Haushaltsmitglieder mit `member`-Konten: Neugier auf fremde Watch-Histories, restriktive Bibliotheken, Admin-Funktionen.
4. **Externer Angreifer mit Netzzugang** — Exposition durch Fehlkonfiguration; klassische Web-Angriffsfläche.
5. **Datenbank-/Backup-Exfiltration** — physischer oder Datei-Zugriff auf `pgdata`/Backups.

Nicht im Modell: staatliche Akteure, Seitenkanal-Angriffe, DRM-Fragen (MediaForge verarbeitet nur bereits lesbare Strukturen — Masterdatei-Nicht-Ziel).

## Verteidigungsebenen

**Ebene 1 — Prozess-Isolation** (gegen Klasse 1): Alle Parser feindlicher Formate laufen ausschließlich in `media-tools`/`ai-worker`: unprivilegiert, ohne Netz-Egress (internes Compose-Netz ohne Gateway), read-only-Medien, Schreibrecht nur auf Task-Ausgabepfade, Ressourcen-Limits (CPU-Zeit, RAM, Output-Größe) pro Aufruf. PHP parst nie Binärformate ([ADR-0001](../adr/0001-technology-stack.md)); PHP-seitige Sidecar-Parser (CUE/JSON) arbeiten mit Größen-Limits, striktem Encoding und ohne Pfadauflösung aus Dateiinhalten ([Assembler](../modules/audiobook-assembler.md)). Ein erfolgreicher Parser-Exploit erreicht damit: einen Wegwerf-Container ohne Daten und ohne Netz.

**Ebene 2 — Zonen und Least-Privilege der Integrationen** (gegen Klasse 2): Connector-Clients implementieren nur die benötigten Endpunkte (Prowlarr/Immich read-only, Jellyfin ohne Verwaltungs-Endpunkte, *arr ohne Lösch-Endpunkte — jeweils per Architekturtest fixiert); eingehende Connector-Daten sind grundsätzlich unvertraut (Schema-Validierung, Matching statt Vertrauen, Konflikt-Reviews statt Durchschreiben); Webhooks sind reine Trigger mit signierten Pfaden — gefälschte Webhooks können nur authentifizierte Polls auslösen. SSRF-Schutz für alle ausgehenden Basis-URLs (kein Redirect-Follow auf fremde Hosts, interne Dienste als Ziel verweigert), TLS-Verifikation mit Fingerprint-Pinning als einziger Ausnahme-Mechanik ([SDK](../connectors/connector-sdk.md)).

**Ebene 3 — Identität und Autorisierung** (gegen Klasse 3): unten ausgeführt.

**Ebene 4 — Web-Härtung** (gegen Klasse 4): Laravel-Standardschutz (CSRF für Inertia, Session-Härtung, Argon2id) plus: Rate-Limits auf Auth-Routen mit Backoff, konstante Fehlerzeiten (kein User-/Token-Enumerationsorakel), Security-Header (CSP für das Inertia-UI, deny-by-default-CORS der API), signierte Kurzzeit-URLs als einziges Dateizugriffs-Muster (nie rohe Pfade — Kernschema-Regel bis in die API durchgezogen), keine Debug-Ausgaben in Responses ([api/conventions.md](../api/conventions.md)).

**Ebene 5 — Daten in Ruhe** (gegen Klasse 5): Secrets verschlüsselt (unten); Outbox-Payloads restriktiver Inhalte verschlüsselt ([Stash](../connectors/stash.md)); Backup-Verschlüsselung als Pflicht-Feature des [Backup-Moduls](../modules/backup-restore.md); Audit-REVOKE-Härtung (append-only auch gegen SQL-Zugriff mit App-Rolle).

## Identitäts- und Berechtigungsmodell

Die konsolidierte Sicht der in [core-schema](../database/core-schema.md) eingeführten Elemente:

**Rollen** (global): `admin` (System, Benutzer, Connectoren, Regeln, Settings), `manager` (Katalog-Pflege, Reviews, Mappings, Workflows, Betriebssichten), `member` (Konsum, eigene Watch-States, eigene Tokens/Geräte). Rollen sind bewusst grob; Feinsteuerung leisten:

**Bibliotheks-Grants**: `restricted`-Bibliotheken ([Stash-Kapitel](../connectors/stash.md), aber generisch nutzbar) sind nur mit explizitem Grant sichtbar — der Sichtbarkeitsfilter ist **eine** zentrale Query-Scope-Implementierung, die alle lesenden Pfade (Suche, Graph, Dashboards, Reviews, Audit-Zusammenfassungen, Notifications) verpflichtend nutzen; jede neue Lesefläche muss den Scope-Test der Sichtbarkeits-Suite bestehen.

**Benutzergebundene Daten**: Watch-States, Historien, Sessions, Player-Geräte, Tokens — strikt eigentümergebunden; `manager` sieht fremde Watch-Daten nicht (Audit-Vollzugriff für Recherchen bleibt `manager` mit dokumentierter Begründung — die Abwägung aus [modules/audit.md](../modules/audit.md)).

**Policies**: Laravel-Policies pro Modell, generiert aus einer zentralen Berechtigungs-Matrix (Dokument-Tabelle ↔ Code-Konstanten, per Test synchron gehalten) — die Matrix ist die normative Quelle, verstreute Ad-hoc-Checks sind ein Review-Defekt.

**API-Tokens**: Scope-Modell aus [api/conventions.md](../api/conventions.md); Sonderfälle: Player-Tokens (`playback:report`, gerätegebunden), Worker-Shared-Secret (AI-Registrierung, [ai-engine](../modules/ai-engine.md)).

## Secrets-Verwaltung

Drei Klassen mit je einer Regel: **Infrastruktur-Secrets** (`APP_KEY`, DB/Redis-Passwörter) leben in der ro-gemounteten `.env` — Rotation dokumentiert pro Secret (APP_KEY-Rotation re-verschlüsselt den Secret-Store per Kommando). **Integrations-Secrets** (Connector-API-Keys, Player-RPC-Credentials, Webhook-HMACs) leben verschlüsselt (App-Key) im Secret-Store des SDK — nie in `settings`-JSONB, nie im Audit-Kontext, überall maskiert; die Recorder-Denyliste (`*token*`, `*secret*`, `*password*`) ist die letzte Verteidigungslinie gegen versehentliches Loggen. **Benutzer-Credentials**: Argon2id; Passwort-Reset nur über Admin (kein Mail-Reset im Selfhosting-Default — Mail-Infrastruktur ist nicht vorauszusetzen; ein optionaler SMTP-Reset ist Setting).

## Querschnittsregeln (verbindlich, testgestützt)

1. Kein roher Dateisystempfad verlässt den Server an Nicht-Admin-Clients; Dateizugriff nur über signierte Kurzzeit-URLs.
2. Jede neue Lesefläche implementiert den zentralen Sichtbarkeits-Scope und besteht die Sichtbarkeits-Suite.
3. Jeder Connector-Client implementiert nur benötigte Endpunkte (Architekturtest der Client-Oberfläche).
4. Kein Secret in JSONB-Spalten, Logs, Traces, Evidence oder Notifications (Denyliste + Reviews).
5. Feindliche Formate werden nur in No-Egress-Containern geparst.
6. Auth-/Existenzfragen antworten zeitkonstant und mit 404-statt-403 für Unsichtbares.
7. Alle sicherheitsrelevanten Verwaltungsakte (Rollenwechsel, Grants, Token-Erzeugung, Connector-Anlage, Regel-Änderung) sind auditierte Actions.

## Sicherheitsrelevante Ereignisse und Reaktion

Der Health-/Monitoring-Stack ([health-monitoring](../modules/health-monitoring.md)) führt eine Security-Ereignisklasse: fehlgeschlagene Login-Serien, Token-Nutzung nach Widerruf (Race-Fenster), Webhook-Signaturfehler-Serien, Worker-Registrierung mit falschem Secret, Schema-Fingerprint-Abweichungen. Reaktionen sind dokumentierte Runbooks (Betriebshandbuch) — MediaForge automatisiert Erkennung und Meldung, nicht die Reaktion (kein Auto-Bann im Heimnetz, wo der „Angreifer" meist ein fehlkonfiguriertes Gerät ist).

## Edge Cases

* **Admin sperrt sich aus**: `php artisan mediaforge:admin-recover` (Konsolen-Zugriff als Wurzel des Vertrauens — wer den Host hat, hat das System; das ist im Selfhosting ehrlich statt gefährlich).
* **Reverse Proxy fehlkonfiguriert** (X-Forwarded-For-Spoofing): Trusted-Proxy-Konfiguration ist Teil des Setup-Checks; Rate-Limits fallen sonst auf konservative IP-lose Limits zurück.
* **Mehrere Admins, gegenseitige Kontrolle**: Audit macht Admin-Handlungen sichtbar füreinander; ein Vier-Augen-Modus für destruktive Aktionen ist bewusst nicht Version 1 (Heimkontext), als Erweiterung notiert.
* **Gerät im Heimnetz kompromittiert** (Klasse 2/4-Mischfall): Der Schaden ist durch Token-Scopes, Client-Minimalismus und Grant-Grenzen kompartimentiert — das Modell akzeptiert, dass das Heimnetz keine vertrauenswürdige Zone ist.

## Performance

Sicherheitsmechanik mit Laufzeitkosten (Scope-Filter, Signatur-Prüfungen, Argon2id) ist in den Budgets der Module eingepreist; der Sichtbarkeits-Scope ist als Query-Bestandteil indexverträglich entworfen (Bibliotheks-Grant-Join statt Post-Filter). Keine Sicherheitsprüfung darf aus Performance-Gründen gecacht werden, wenn der Cache die Widerrufs-Latenz über 60 s höbe (Token-/Grant-Widerruf wirkt binnen einer Minute — dokumentierte Garantie).

## Tests

Die **Sichtbarkeits-Suite** (systemweit, wächst mit jeder Lesefläche) und die **Client-Oberflächen-Architekturtests** sind die beiden tragenden Testfamilien. Dazu: Policy-Matrix-Synchrontest, Scope-Matrix der API ([api/conventions.md](../api/conventions.md)), Denyliste-Tests (Secrets in allen JSONB-Senken), Zeitkonstanz-Stichproben der Auth-Pfade, Signatur-/TTL-Tests aller Kurzzeit-URL-Typen, Recovery-Kommando.

## ADR-Verweise

Konsolidiert Sicherheitsentscheidungen aus [ADR-0001](../adr/0001-technology-stack.md) (Prozessgrenzen), [ADR-0005](../adr/0005-immutable-originals.md) (ro-Mounts), [ADR-0006](../adr/0006-action-level-audit.md) (auditierte Verwaltungsakte) und den Connector-Kapiteln; keine neue ADR — das Kapitel ordnet, es entscheidet nicht neu.

## Offene Punkte

* **2FA/Passkeys**: für exponierte Installationen wertvoll; WebAuthn als opt-in ist Kandidat für eine frühe Folgeversion.
* **Vier-Augen-Modus** für destruktive Massenaktionen: notiert (oben).
* **SBOM-/CVE-Prozess** (wie schnell fließen Basis-Image-Fixes): Release-Prozess-Frage, gehört ins Betriebs-/Release-Handbuch.
