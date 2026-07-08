# External-Player-Konzept (Kodi, MPV & Co.)

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [connectors/connector-sdk.md](connector-sdk.md), [modules/disc-engine.md](../modules/disc-engine.md) — das Player-Protokoll (Sessions/Events/Reporting-Modi) ist dort normativ definiert; dieses Kapitel spezifiziert die Player-Anbindungen, die es bedienen.

**Vertiefung**: [API-Mapping-Referenz](external-player/api-mapping.md) (JSON-RPC/WebSocket/Add-on-Protokoll, MPV-IPC)

## Motivation

MediaForge streamt nicht (Nicht-Ziel) und spielt selbst keine Disc-Menüs ab. Das Disc-Menü-Erlebnis — der Grund, ISOs statt Remuxes zu archivieren — braucht einen externen Player mit libbluray/libdvdread-Fähigkeiten. Kodi ist der Machbarkeitsbeweis (Masterdatei-Referenzanalyse) und die erste Ziel-Integration. Das Konzept dreht die übliche Rollenverteilung um: Der Player ist **Anzeigegerät und Sensor**, MediaForge ist Zustandsführer. Kodis eigene Watch-State-Verwaltung wird ausdrücklich nicht benutzt und nicht synchronisiert — sie führt Disc-Level-Zustände, deren Übernahme genau den Fehler reproduzieren würde, den Architekturregel 11 verbietet.

## Problemstellung

**Launch-Kette.** Vom MediaForge-UI („Disc im Player öffnen") muss der Player auf dem richtigen Gerät die richtige Datei mit Menü-Modus öffnen — über Gerätegrenzen (MediaForge auf dem Server, Kodi am TV) und ohne rohe Pfad-Preisgabe an Clients ([Disc-Engine, Security](../modules/disc-engine.md)).

**Sensor-Qualität.** Die Watch-State-Übersetzung braucht `playlist_position`-Reporting (Playlist + Position). Kodi kennt intern die aktive Playlist beim BD-Playback, exponiert sie aber nicht über die Standard-JSON-RPC-Player-Properties — deshalb braucht Kodi ein MediaForge-Add-on; ohne Add-on degradiert die Integration ehrlich auf die schwächeren Modi (`title_only`/`open_close_only`, Disc-Engine-Semantik).

**Benutzer-Zuordnung.** Ein Wohnzimmer-Kodi hat keinen MediaForge-Login; Sessions müssen trotzdem einem MediaForge-Benutzer gehören (Watch-State ist benutzergebunden). Geräte-zu-Benutzer-Bindung muss explizit, sichtbar und widerrufbar sein.

## Analyse der Gegenstellen

**Kodi**: JSON-RPC-API (`Player.Open` mit Dateipfad bzw. `bluray://`-URL, `Player.OnPlay/OnStop`-Notifications via WebSocket, `Player.GetProperties` für Zeit/Position); Add-on-System (Python) mit vollem Zugriff auf `xbmc.Player`-Callbacks und — entscheidend — auf die laufende Datei inklusive BD-Playlist-Kontext (`ListItem`/InfoLabels, aktive MPLS über den VideoPlayer). BD-J-Menüs erfordern aktivierte libbluray-BD-J-Unterstützung (Java-VM am Kodi-Host); DVD-Menüs sind nativ. **MPV**: `--bluray-device`/`bd://`-Playback ohne Menüs (Playlist direkt adressierbar: `bd://mpls/4`), Lua-Scripting mit `mpv-ipc` — gut für „Episode direkt abspielen" (gemappte Playlist ohne Menü-Umweg), Reporting via Script möglich (`playlist_position`-fähig, da die Playlist explizit gewählt wird). **VLC**: BD-Menüs teilweise (libbluray, BD-J wackelig), Reporting-Schnittstellen schwach — Integration nur `open_close_only`, dokumentiert als Minimalmodus.

## Architekturentscheidung

Die Integration besteht aus drei Teilen:

**Player-Endpunkte-Registry** (`player_devices`): Ein registriertes Abspielgerät mit Typ (kodi/mpv/vlc/other), Erreichbarkeit (Host/Port für JSON-RPC bzw. IPC-Bridge), gebundenem MediaForge-Benutzer (oder „fragt pro Session"), Fähigkeits-Deklaration (Menü-Playback ja/nein, BD-J ja/nein, Reporting-Modus) und einem gerätegebundenen Player-Token (Fähigkeit `playback:report`, [Disc-Engine, API](../modules/disc-engine.md)).

**Launch-Flow**: Die UI-Aktion erzeugt eine `disc_playback_session` (Status `active`, `position_reporting` aus der Geräte-Deklaration) plus eine kurzlebige signierte Launch-Referenz; der Launch-Job ruft die Geräte-API (Kodi: `Player.Open`) mit dem serverseitig aufgelösten Pfad auf. Discs erreichen den Player über denselben Mount, den auch MediaForge sieht (Pfad-Mapping-Tabelle pro Gerät: MediaForge-Pfad → Geräte-Pfad, z. B. `/media/discs` → `smb://nas/discs`) — MediaForge streamt die ISO nicht selbst.

**Reporting-Flow**: Das MediaForge-Kodi-Add-on (ausgeliefert im Repo, `resources/kodi-addon/`) meldet über das Player-Protokoll: Session-Übernahme per Launch-Referenz, dann Event-Batches (`play`/`position` alle 10 s/`playlist_change`/`pause`/`stop`) mit aktiver MPLS-Referenz und Playlist-Position. Startet der Benutzer Disc-Playback **am Kodi selbst** (ohne MediaForge-Launch), erkennt das Add-on die Datei, fragt MediaForge nach einer Session (`POST /playback/disc-sessions` mit Dateipfad-Hash — der Server matcht über die Pfad-Mapping-Tabelle und `files`) und berichtet normal; unbekannte Dateien werden nicht berichtet (kein Datenabfluss über fremde Inhalte).

Alle fachliche Interpretation (Segment-Auflösung, Schwellen, Watch-State) bleibt in der Disc-Engine — das Add-on ist ein dummer Sensor mit < 500 Zeilen Python, bewusst ohne eigene Logik (Updatezyklen von Add-ons sind langsam; Logik im Server ist sofort korrigierbar).

## Alternativen

**Kodi-Watch-State-Sync** (Kodis Datenbank lesen/schreiben): verworfen — Disc-Level-Semantik, genau das Anti-Pattern (Masterdatei, Kodi-Analyse). **MediaForge als UPnP/DLNA-Controller**: Menü-Playback über DLNA existiert nicht; verworfen. **Eigener Player im Browser** (libbluray-WASM-Träume): technisch unreif, BD-J unmöglich; verworfen. **Nur-Launch ohne Reporting** (MediaForge startet, fragt danach manuell): der `open_close_only`-Modus **ist** dieser Fallback — als Basis immer verfügbar, aber das Add-on hebt Kodi auf Vollautomatik.

## Datenmodell und SQL-Schema

```sql
CREATE TABLE player_devices (
    id             CHAR(26) PRIMARY KEY,
    name           TEXT        NOT NULL,               -- "Wohnzimmer-Kodi"
    player_kind    TEXT        NOT NULL
        CHECK (player_kind IN ('kodi','mpv','vlc','other')),
    endpoint       TEXT,                               -- JSON-RPC-URL / IPC-Bridge; NULL = nur Push-Reporting
    bound_user_id  CHAR(26)    REFERENCES users(id) ON DELETE SET NULL,  -- NULL = Session fragt
    capabilities   JSONB       NOT NULL DEFAULT '{}',  -- {menus:true,bdj:false,reporting:'playlist_position'}
    path_mappings  JSONB       NOT NULL DEFAULT '[]',  -- [{mediaforge_prefix, device_prefix}]
    token_id       TEXT        NOT NULL,               -- Referenz auf das Player-API-Token
    enabled        BOOLEAN     NOT NULL DEFAULT true,
    last_seen_at   TIMESTAMPTZ,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (name)
);
```

`capabilities` und `path_mappings` sind Regel-8-konform (Konfigurationsstruktur mit gemeinsamer Lebensdauer, nie gejoint). Sessions/Events liegen in der Disc-Engine (`disc_playback_sessions.player_instance` referenziert `player_devices.id` als Kennung).

## Laravel-Klassen

| Klasse | Typ | Vertrag |
|---|---|---|
| `PlayerDevice` | Model | Registry |
| `RegisterPlayerDevice`, `BindPlayerUser`, `DisablePlayerDevice` | Action | Verwaltung inkl. Token-Erzeugung/-Rotation; Audit |
| `LaunchDiscPlaybackAction` | Action | Session anlegen, Launch-Referenz signieren, Launch-Job dispatchen; Audit |
| `LaunchOnKodiJob` | Job (`connector`) | JSON-RPC `Player.Open` mit gemapptem Pfad; Fehlerklassifikation (Gerät aus = transient kurz, dann Session `discarded` mit UI-Hinweis) |
| `KodiClient`, `MpvIpcClient` | Client | Geräte-APIs; nur Launch/Status, nie Zustands-Schreiben Richtung Player |
| `SessionAdoptionController`-Anteil | HTTP | Add-on-initiierte Sessions (Pfad-Hash-Matching) — Teil der Player-Protokoll-Routen der Disc-Engine |

## API-Endpunkte

Zusätzlich zum Player-Protokoll der [Disc-Engine](../modules/disc-engine.md):

| Route | Zweck | Rolle |
|---|---|---|
| `GET /api/v1/player-devices` | Geräte mit Fähigkeiten und Bindung | member (eigene), admin (alle) |
| `POST /api/v1/player-devices` / `PUT …/{ulid}` | Registrierung/Pflege inkl. Verbindungstest | admin |
| `POST /api/v1/discs/{ulid}/play?device=` | Launch-Flow | member (nur an Geräte mit eigener Bindung bzw. Session-Frage) |

## UI und Flows

**Geräte-Verwaltung** (`Admin/PlayerDevices`): Registrierung mit Verbindungstest (Kodi: RPC-Ping + Versions-/BD-J-Erkennung), Fähigkeits-Matrix, Pfad-Mapping-Editor mit Live-Validierung (Testpfad auflösen), Token-Anzeige für die Add-on-Konfiguration (QR-Code für die Add-on-Einrichtung am TV). **Launch** von der Disc-Detailseite: Geräteauswahl (gefiltert nach Fähigkeit — UHD-BD-J-Disc zeigt nur BD-J-fähige Geräte), danach Live-Session-Panel (aktive Playlist, übersetzte Episode sobald gemappt, Positionsbalken) aus den Session-Events. **Ungebundene Geräte**: Session-Start am Gerät ohne `bound_user_id` erzeugt eine „Session zuordnen"-Karte in der MediaForge-UI aller `member` (wer sie beansprucht, bekommt sie — auditiert); Default-Empfehlung ist feste Bindung.

## Edge Cases

* **Kodi ohne Add-on** (nur JSON-RPC): MediaForge kann launchen und via RPC `OnPlay`/`OnStop` sehen, aber keine Playlist ⇒ Gerät deklariert `title_only` maximal dann, wenn RPC die laufende Datei liefert; sonst `open_close_only`. Die Disc-Engine-Semantik dieser Modi greift unverändert — keine Sonderpfade.
* **BD-J-Disc auf Nicht-BD-J-Gerät**: Launch wird abgelehnt mit Begründung (Fähigkeits-Matrix), statt den Benutzer in ein schwarzes Bild laufen zu lassen; `disc_menus.requires_bdj` liefert die Information.
* **Zwei Geräte spielen dieselbe Disc** (Haushalt): zwei Sessions, ggf. zweier Benutzer — sauber getrennt; derselbe Benutzer parallel auf zwei Geräten ist zulässig (die Span-Rekonstruktion arbeitet pro Session).
* **Add-on-Version veraltet** (Protokoll-Erweiterung): Events tragen `schema`-Version; der Server akzeptiert n−1 und markiert die Session mit Update-Hinweis im Geräte-Panel.
* **Gerät hinter wechselnder IP**: Launch schlägt fehl → Gerät `last_seen`-Warnung; Push-Reporting (Add-on → Server) funktioniert weiter, da die Richtung auslaufend vom Gerät ist — Launch und Reporting sind bewusst unabhängige Kanäle.

## Performance

Vernachlässigbar serverseitig (Events sind das Player-Protokoll der Disc-Engine, dort budgetiert). Kodi-seitig: Das Add-on batcht Events (10-s-Position, sofortige Zustandswechsel) und puffert bei Serverausfall lokal (Ringpuffer 1000 Events) mit Nachlieferung — kurze MediaForge-Neustarts kosten keine Spannen.

## Security

Player-Tokens: einzige Fähigkeit `playback:report`, gerätegebunden, rotierbar, Anzeige nur bei Erzeugung ([Disc-Engine, Security](../modules/disc-engine.md)). Launch-Referenzen: signiert, 60 s TTL, einmalverwendbar — ein mitgelesener Launch-Link ist wertlos. Kodi-RPC-Zugang (Endpoint + ggf. RPC-Credentials) liegt im Secret-Store des SDK. Die Pfad-Mapping-Auflösung findet serverseitig statt; das Add-on erhält Pfade nur für Inhalte, die es ohnehin abspielen soll. Session-Adoption per Pfad-Hash verhindert, dass ein Gerät fremde Bibliotheksexistenz abfragt (Hash-Lookup antwortet nur für Dateien, die dem gebundenen Benutzer zugänglich sind).

## Tests

Fake-Kodi (JSON-RPC-Stub) für Launch-Kette und Fehlerklassifikation; Add-on-Protokolltests als Contract-Fixtures (Event-Batches gegen die Disc-Engine-Übersetzungstests — gemeinsame Fixtures, ein Referenzsatz); Pfad-Mapping-Matrix (SMB/NFS/lokal, Sonderzeichen); Modus-Degradation (Add-on weg ⇒ title_only-Verhalten greift); Puffer-Nachlieferung (Events mit altem `occurred_at` nach Serverpause ⇒ korrekte historische Anrechnung, kein Konflikt mit zwischenzeitlichen manuellen Markierungen — nutzt die occurred_at-Konfliktlogik des Fundaments).

## ADR-Verweise

[ADR-0004](../adr/0004-episode-granular-watch-state.md) (Player als Sensor, nie Zustandsführer), SDK-Regeln aus [connectors/connector-sdk.md](connector-sdk.md). Kodi-Referenzrolle: Masterdatei, Referenzanalyse.

## Offene Punkte

* **MPV-Direktabspiel gemappter Episoden** (`bd://mpls/N` ohne Menü): spezifiziert als Fähigkeit, Add-on/Script noch zu bauen; UI-Einstieg („Episode direkt abspielen") hängt daran.
* **Kodi-Add-on-Distribution** (eigenes Repo vs. Kodi-Repository-Einreichung): Betriebsfrage, offen.
* **Wake-on-LAN** für Launch auf schlafende Geräte: nettes Betriebsfeature, unspezifiziert.
