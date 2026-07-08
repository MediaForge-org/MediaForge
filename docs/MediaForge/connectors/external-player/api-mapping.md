# External-Player: API-Mapping-Referenz

Vertiefung zu [connectors/external-player.md](../external-player.md). Wire-Ebene für `KodiClient`/`MpvIpcClient` und das MediaForge-Kodi-Add-on-Protokoll: exakte JSON-RPC-Aufrufe, WebSocket-Notifications, Add-on-Event-Schema und die Fähigkeits-Erkennung. Das Player-Protokoll selbst (Sessions/Events zur Disc-Engine) ist in [modules/disc-engine/api-reference.md](../../modules/disc-engine/api-reference.md) normiert; hier geht es um die Strecke davor — wie MediaForge den Player überhaupt anspricht und wie das Add-on die Rohsignale gewinnt.

## Kodi JSON-RPC: Launch

```
POST http://{host}:{port}/jsonrpc
Content-Type: application/json

{"jsonrpc": "2.0", "id": 1, "method": "Player.Open",
 "params": {"item": {"file": "smb://nas/discs/Staffel3/Disc2.iso"}}}
```

Antwort: `{"jsonrpc":"2.0","id":1,"result":"OK"}` bei Erfolg. Der `file`-Pfad ist das Ergebnis der Pfad-Mapping-Auflösung (`player_devices.path_mappings`, Modulkapitel): MediaForge-Pfad `/media/discs/Staffel3/Disc2.iso` → Geräte-Pfad `smb://nas/discs/Staffel3/Disc2.iso` über den längsten passenden Präfix. Für BD-Menü-Playback wird zusätzlich `params.item.file` auf ISO-Ebene belassen (Kodi öffnet BD-Strukturen aus ISOs direkt über libbluray; kein `bluray://`-Präfix nötig bei aktueller Kodi-Generation — ältere Versionen erfordern `bluray://{iso_path}/`, per Versions-Erkennung im Client umgeschaltet).

### Verbindungstest (Diagnostics)

```
POST /jsonrpc  {"method": "JSONRPC.Ping"}                        → Erreichbarkeit
POST /jsonrpc  {"method": "Application.GetProperties",
                "params": {"properties": ["version"]}}            → Kodi-Version
POST /jsonrpc  {"method": "Addons.GetAddonDetails",
                "params": {"addonid": "service.mediaforge.reporter"}}   → Add-on-Präsenz + Version
```

`Addons.GetAddonDetails` mit 404-artigem Fehler (`{"error":{"code":-32602}}`) ⇒ Add-on nicht installiert ⇒ Gerät wird auf maximal `title_only`/`open_close_only` deklariert (Modulkapitel Edge Case) — die Fähigkeits-Erkennung ist damit vollautomatisch, kein manuelles Capability-Flag nötig.

## Kodi JSON-RPC: Status-Polling (ohne Add-on)

```
POST /jsonrpc {"method": "Player.GetActivePlayers"}
POST /jsonrpc {"method": "Player.GetItem", "params": {"playerid": 1, "properties": ["file"]}}
POST /jsonrpc {"method": "Player.GetProperties", "params": {"playerid": 1,
               "properties": ["time", "totaltime", "speed"]}}
```

`Player.GetItem.properties.file` liefert den abspielenden Dateipfad, aber **nicht** die aktive BD-Playlist-Nummer (Modulkapitel-Kernproblem) — dieser Pfad trägt maximal `title_only`-Semantik (Datei bekannt, Playlist-Position unbekannt). `Player.GetProperties.time`/`totaltime` sind `{hours,minutes,seconds,milliseconds}`-Objekte; Umrechnung: `ms = ((h*60+m)*60+s)*1000+ms`. Ohne Add-on pollt MediaForge diese Endpunkte im `title_only`-Intervall (Setting, Default 15 s) statt Push-Events zu empfangen.

## WebSocket-Notifications (mit oder ohne Add-on)

```
ws://{host}:{port}/jsonrpc
→ {"jsonrpc":"2.0","method":"Player.OnPlay","params":{"data":{"item":{...},"player":{"playerid":1,"speed":1}}}}
→ {"jsonrpc":"2.0","method":"Player.OnStop","params":{"data":{"item":{...},"end":false}}}
→ {"jsonrpc":"2.0","method":"Player.OnPause","params":{...}}
```

Der `KodiClient` hält eine persistente WebSocket-Verbindung je registriertem Gerät (Reconnect mit Backoff bei Abbruch); `OnPlay`/`OnStop`/`OnPause` triggern sofortige `Player.GetProperties`-Nachfragen statt auf das Poll-Intervall zu warten — das ist die „ohne Add-on, aber besser als reines Polling"-Zwischenstufe, die das Modulkapitel unter `title_only` mit Push-getriggerter Aktualisierung führt.

## Add-on-Protokoll (`service.mediaforge.reporter`)

Das Add-on (Python, `resources/kodi-addon/`) meldet direkt an die Disc-Engine-Player-Routen ([API-Referenz](../../modules/disc-engine/api-reference.md)), nicht über JSON-RPC — es ist selbst ein HTTP-Client Richtung MediaForge. Interner Aufbau (Wire-Vertrag des Add-ons, den ein Reimplementierer für eine andere Kodi-Fork-Version einhalten müsste):

### Session-Übernahme (Launch-initiiert)

Das Add-on empfängt die Launch-Referenz nicht direkt (Kodi hat keinen Callback-Mechanismus dafür); stattdessen erkennt es den `Player.OnAVStart`-Event, liest `xbmc.Player().getPlayingFile()` und prüft, ob eine offene `disc_playback_session` mit passendem `path_hint` existiert (`GET /api/v1/users/me/disc-sessions?status=active` mit dem Add-on-eigenen Player-Token). Treffer ⇒ Übernahme (`session_key` = Launch-Referenz-abgeleitete ULID, bereits vom Launch-Flow serverseitig angelegt); kein Treffer ⇒ Selbst-Eröffnung (`POST /playback/disc-sessions` mit `path_hint`, Modulkapitel „Add-on erkennt die Datei... fragt MediaForge nach einer Session").

### BD-Playlist-Erkennung

```python
info = xbmc.getInfoLabel('Player.Filenameandpath')
# oder, praeziser fuer BD-Menue-Kontext:
playlist_ref = xbmc.getInfoLabel('VideoPlayer.PlaylistPosition')  # Kodi-intern: aktive MPLS
```

Die exakte InfoLabel-Quelle variiert zwischen Kodi-Versionen (Matrix/Nexus/Omega); das Add-on kapselt drei Erkennungsstrategien in Versuchsreihenfolge (`ListItem.Property(bluray_playlist)`, `VideoPlayer.PlaylistPosition`, Fallback: Datei-Rescan der zuletzt über `Player.Open` mit `bluray://`-Präfix übergebenen Playlist-Nummer, falls MediaForge selbst gestartet hat — dann kennt das Add-on die Nummer aus dem Launch-Kontext direkt). Liefert keine der drei Strategien einen Wert, degradiert das Add-on selbst auf `title_only`-Reporting für diese Session (Selbst-Degradation, nicht nur Geräte-Deklaration).

### Event-Batch an MediaForge

```
POST /api/v1/playback/disc-sessions/{ulid}/events
{"events": [
  {"id": "{uuid4}", "event_type": "position", "playlist_ref": "00004",
   "position_ms": 10250, "occurred_at": "2026-07-06T20:32:20Z"}
]}
```

Erzeugungs-Takt: `position` alle 10 s (Timer-Callback, Modulkapitel-Konformitätspunkt PB-02), sofort bei `OnPlay`/`OnStop`/`OnPause`/Playlist-Wechsel (zusätzliches `playlist_change`-Event). Event-IDs sind Python-`uuid4`, lokal generiert (Client-ULID-Äquivalent — das Add-on nutzt UUID4 statt ULID, da keine ULID-Bibliothek in der Kodi-Python-Umgebung vorausgesetzt wird; der Server akzeptiert beide Formate als Dedup-Schlüssel, solange sie eindeutig sind).

### Ringpuffer bei Serverausfall

```python
# Pseudocode der Add-on-internen Pufferung:
if not post_succeeded:
    ring_buffer.append(event)  # Kapazität 1000, älteste verworfen bei Überlauf
    retry_timer.start(backoff)
else:
    flush_ring_buffer_first()  # gepufferte vor aktuellem Event senden, occurred_at bleibt original
```

Modulkapitel Performance: „kurze MediaForge-Neustarts kosten keine Spannen" — der Ringpuffer ist der Mechanismus dahinter; `occurred_at` bleibt beim ursprünglichen Zeitpunkt, die Server-Konfliktlogik (`recorded_at` getrennt) verarbeitet nachgelieferte Events korrekt in die Vergangenheit.

## MPV IPC (Alternative ohne Menü)

```
echo '{"command": ["set_property", "playlist-pos", 3]}' | socat - /tmp/mpv-socket
```

MPV wird über Unix-Socket-IPC (`--input-ipc-server=/tmp/mpv-socket`) angesprochen; Playlist-Adressierung erfolgt bereits beim Start (`bd://mpls/{n}` als Datei-Argument, Modulkapitel: „Playlist direkt adressierbar"), wodurch `playlist_ref` von vornherein bekannt ist (kein Erkennungsproblem wie bei Kodi). Property-Observer für Position:

```
echo '{"command": ["observe_property", 1, "time-pos"]}' | socat - /tmp/mpv-socket
→ {"event": "property-change", "id": 1, "name": "time-pos", "data": 611.25}
```

`time-pos` ist Sekunden-Float; Umrechnung `position_ms = round(data * 1000)`. Ein Lua-Script (analog dem Kodi-Add-on, aber ~150 Zeilen dank expliziter Playlist-Adressierung) meldet im selben Event-Batch-Format an die Disc-Engine-Routen.

## Fehlerklassifikation (`KodiClient`)

| Bedingung | Klasse | Wirkung |
|---|---|---|
| Connection Refused / Timeout auf `/jsonrpc` | transient (kurz) | Launch schlägt fehl, UI-Hinweis „Gerät nicht erreichbar"; Session bleibt `discarded` (Modulkapitel) |
| WebSocket-Verbindungsabbruch | transient | Reconnect mit Backoff `[5,15,60]` s, unbegrenzt (Geräte schlafen/wachen) |
| `Player.Open`-Fehlerantwort (Datei nicht gefunden am Geräte-Pfad) | permanent (für diesen Launch) | Pfad-Mapping-Fehlkonfiguration vermutet, UI-Hinweis mit Mapping-Editor-Link |
| Add-on-Version-Mismatch (`schema` im Event-Batch n−2 oder älter) | permanent | Server lehnt Batch ab (`disc.reporting_unsupported`-Analogon), Geräte-Panel zeigt Update-Pflicht |

## Fixture-Index

`tests/fixtures/connectors/external-player/`: `jsonrpc-ping.json`, `jsonrpc-addon-details-present.json`/`-absent.json`, `websocket-onplay.json`/`-onstop.json`, `addon-event-batch.json` (mehrere Schema-Versionen für die n−1-Toleranz-Tests), `mpv-ipc-property-change.json`. Launch-Kette-Tests laufen gegen einen Fake-JSON-RPC-Server (HTTP + WebSocket kombiniert); Add-on-Protokolltests teilen ihre Fixtures mit den Disc-Engine-Playback-Übersetzungstests (Modulkapitel: „gemeinsame Fixtures, ein Referenzsatz") — dieselbe Event-Batch-JSON validiert hier das Add-on-Sendeverhalten und dort die Server-Empfangsverarbeitung.
