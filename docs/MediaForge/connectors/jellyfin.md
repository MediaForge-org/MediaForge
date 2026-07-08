# Jellyfin-Connector

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [connectors/connector-sdk.md](connector-sdk.md) (alle Rahmenverträge), [database/core-schema.md](../database/core-schema.md) (Watch-State, Provider-IDs). Dieses Kapitel spezifiziert nur Jellyfin-Spezifika; alles Generische (Outbox, Cursor, Konflikte, Health, Matching-Kaskade) gilt aus dem SDK und wird hier nicht wiederholt.

**Vertiefung**: [API-Mapping-Referenz](jellyfin/api-mapping.md) (Wire-Ebene: Endpunkte, Feldpfade, Versionsmatrix)

## Motivation

Jellyfin ist in der Zielarchitektur der Playback-Spezialist für Video (Masterdatei, Nicht-Ziele): MediaForge streamt nicht, Jellyfin schon. Der Connector macht diese Arbeitsteilung nahtlos — Leitszenario 3: Was in Jellyfin geschaut wird, erscheint lokal nachvollziehbar in MediaForge, und umgekehrt; der Katalog beider Systeme ist über Provider-IDs verknüpft. MediaForge hält die lokale Watch-State-Historie, Konfliktentscheidung und Audit-Spur, Jellyfin bleibt die Abspieloberfläche.

## Problemstellung

Jellyfin-spezifische Reibungspunkte, die der Connector lösen muss: **(1) Benutzer-Mapping** — Jellyfin hat eigene Benutzer; Wiedergabezustände sind pro Jellyfin-User. Ohne explizites Mapping Jellyfin-User ↔ MediaForge-User ist jeder Sync sinnlos oder gefährlich (fremde Zustände am falschen Konto — der Fall aus dem Audit-Kapitel-UI-Flow). **(2) Item-Identität** — Jellyfin-Item-IDs sind installationsgebunden und ändern sich bei Bibliotheks-Rebuilds; Jellyfin kennt aber selbst Provider-IDs (TMDB/TVDB/IMDb) an Movies/Series/Episodes — die stabile Brücke. **(3) Semantik-Übersetzung** — Jellyfins `UserData` (`Played`, `PlaybackPositionTicks`, `PlayCount`, `LastPlayedDate`) hat eigene Schwellwert-Logik (Jellyfin markiert selbst „Played" nach seinen Regeln); MediaForge übernimmt Positionen und Played-Fakten, wendet aber die eigene Schwellen-Policy an (Architekturregel 3: Connectoren liefern Fakten, der Core urteilt). **(4) Disc-Inhalte** — dieselbe Episode kann in MediaForge über eine Disc gemappt und in Jellyfin als Einzeldatei vorhanden sein; der Watch-State ist trotzdem genau einer (ADR-0004-Konsequenz), und der Connector darf ISO-Items in Jellyfin (falls dort sichtbar) niemals als eigenständige Werke ingestieren.

## Analyse der Gegenstelle

Relevante Jellyfin-API-Flächen (stabil seit 10.8, kompatibel bis 10.10+; Versionsprüfung im Diagnostics): Authentifizierung per API-Key (`X-Emby-Token`-Header) oder User-Login — der Connector verwendet ausschließlich einen Admin-API-Key plus explizite User-Kontexte (`userId`-Parameter), nie gespeicherte User-Passwörter. Katalog-Lektüre über `/Items` (paginiert, `fields=ProviderIds,Path`, Filter nach `IncludeItemTypes=Movie,Series,Season,Episode`); Wiedergabezustände über `/Users/{userId}/Items` mit `fields=UserData` bzw. gezielt `/UserItems/{itemId}/UserData`; Änderungsstrom: Jellyfin bietet keinen belastbaren Cursor — der Connector kombiniert Webhook-Trigger (Webhook-Plugin: `PlaybackStop`, `UserDataSaved`) mit Voll-Abgleich der `UserData` pro gemapptem User in Intervallen (die SDK-Regel „Webhooks triggern, Polls tragen" passt exakt); Schreiben: `POST /UserItems/{itemId}/UserData` (Position, Played) bzw. `/PlayedItems/{itemId}` — idempotente Ziele, gut für die Outbox. Sessions (`/Sessions`) liefern laufende Wiedergaben für die optionale Live-Fortschrittsanzeige.

`supportsCursorSync=false` ist die ehrliche Manifest-Deklaration: Der „Cursor" des Ingest ist ein Voll-Abgleichs-Wasserzeichen (letzter erfolgreicher Durchlauf + `LastPlayedDate`-Vorfilter, wo verlässlich), kein echtes Änderungsprotokoll. Konsequenz: Der Ingest-Vergleich läuft zustandsbasiert (Remote-Zustand vs. MediaForge-Zustand pro Item), nicht ereignisbasiert — das SDK unterstützt beide Ströme (`ChangeStream` abstrahiert darüber).

## Manifest

```php
final class JellyfinManifest implements ConnectorManifest
{
    public function key(): string { return 'jellyfin'; }

    public function capabilities(): CapabilitySet
    {
        return new CapabilitySet(
            ingestPlayState: true,
            egressPlayState: true,
            ingestCatalog:   true,     // lesend: Items + ProviderIds + Pfade
            egressCatalog:   false,    // bewusst: MediaForge schreibt keine Metadaten nach Jellyfin (offener Punkt im SDK)
            supportsWebhooks: true,
            supportsCursorSync: false,
            rateLimit: new RateLimit(requests: 20, perSeconds: 1),
        );
    }

    public function settingsSchema(): SettingsSchema { /* base_url, api_key(secret),
        user_mappings[], sync_interval, verify_tls, import_libraries[] */ }

    public function providerKeys(): array { return ['jellyfin_item','jellyfin_user','jellyfin_server']; }
}
```

## Benutzer-Mapping

Instanz-Setting `user_mappings`: explizite Paare `{jellyfin_user_id ↔ mediaforge_user_id}`, verwaltet im Instanz-UI (der Einrichtungs-Flow listet Jellyfin-User per API und bietet Zuordnung an; keine Automatik über Namensgleichheit — genau die Fehlkonfiguration aus dem Audit-Leitbeispiel entsteht durch solche Automatiken). Nicht gemappte Jellyfin-User werden vollständig ignoriert (weder Ingest noch Egress). Die Jellyfin-User-Identität wird zusätzlich als `provider_ids`-Mapping am MediaForge-User geführt (`provider='jellyfin_user'`), damit Identitätswechsel der Gegenstelle (SDK-Edge-Case) auch für User erkannt und neu verknüpft werden können.

## Item-Mapping und Katalog-Ingest

Der Katalog-Ingest (`stream='catalog'`) liest Items der konfigurierten Jellyfin-Bibliotheken und pflegt ausschließlich `provider_ids` (`provider='jellyfin_item'`) — er legt **keine** Katalog-Einträge an, solange die SDK-Matching-Kaskade greift: (1) Jellyfins eigene ProviderIds (TMDB/TVDB/IMDb) gegen bestehende MediaForge-Mappings; (2) Pfad-Abgleich (`Path` gegen `files`; der häufigste Treffer bei geteilten Mounts — Serien-Episode in Jellyfin und dieselbe Datei in MediaForge); (3) Rest → `unmatched` mit optionalem `media_match`-Review. Items ohne MediaForge-Gegenstück können per Instanz-Setting `import_missing=true` als Katalog-Einträge importiert werden (`presence='present'`, `source`-gekennzeichnet über den Audit-Actor) — Default false: MediaForge-Betreiber, die den Katalog kuratieren, wollen keinen ungefragten Massenimport.

**Disc-Sonderfall (normativ):** Items, deren `Path` auf eine in MediaForge bekannte Disc-Datei zeigt (ISO/BDMV — Abgleich über `files` → `disc_images`), werden nie als Werk-Mapping angelegt. Stattdessen entsteht ein `jellyfin_item`-Mapping auf die **Disc-Datei-Ebene** (entity_type `file`), und der Playstate-Ingest behandelt Wiedergaben dieses Items wie eine External-Player-Session mit `position_reporting='title_only'`-Äquivalent: Jellyfin kann ISO-Positionen nicht episodengenau liefern, also fließen solche Signale in `disc_playback_sessions` (Quelle `player_kind='other'`, Instanz-Kennung) und von dort durch die Disc-Engine-Übersetzung — niemals direkt in Watch-States. Damit ist ausgeschlossen, dass Jellyfins Disc-Verflachung die Episodengranularität von MediaForge unterläuft (Architekturregel 11 an der Systemgrenze).

## Playstate-Sync

**Ingest** (`stream='playstate'`, pro gemapptem User): Zustandsvergleich Remote-`UserData` vs. MediaForge-Watch-State über die `jellyfin_item`-Mappings. Übersetzung: `PlaybackPositionTicks` (100-ns-Ticks) → `position_ms` (÷ 10.000); `Played=true` → Fakt „Remote meldet gesehen" mit `occurred_at=LastPlayedDate` (fehlt das Datum: Empfangszeit, gekennzeichnet); `PlayCount` wird als Zähler-Fakt mitgeführt, aber MediaForge-`play_count` nur monoton angehoben (nie gesenkt — Jellyfin-Resets sollen MediaForge-Historie nicht schrumpfen). Differenzen laufen durch Echo-Unterdrückung und Konfliktstrategie des SDK und münden in `RecordPlaybackProgress`/`MarkWatched` mit `source='connector:jellyfin'`. Container-Items (Series/Season-`Played`-Flags, die Jellyfin kaskadiert setzt) werden **verworfen**: MediaForge akzeptiert nur Episode-/Movie-Fakten; ein Jellyfin-„Staffel als gesehen markiert" kommt als N Episode-Fakten an (Jellyfin kaskadiert selbst auf die Episoden-UserData) — die Container-Flags selbst sind redundant und werden ignoriert statt übersetzt.

**Egress**: `EpisodeWatched`-/Watch-State-Events → Outbox → `push()`: Position setzen bzw. Played-Status schreiben, gefolgt von Read-back (`UserData` des Items lesen) zur Verifikation und als `expected_state_hash`-Grundlage. Nicht in Jellyfin vorhandene Items (kein Mapping) erzeugen kein Outbox-Item (SDK-Filter). Discs sind vom Egress ausgenommen (es gibt keinen sinnvollen Disc-Playstate in Jellyfin zu schreiben).

## Laravel-Klassen

Namespace `App\Connectors\Jellyfin` — nur die Spezifika, der Rest ist SDK:

| Klasse | Typ | Vertrag |
|---|---|---|
| `JellyfinManifest` | Manifest | s. o. |
| `JellyfinClient` | Client | API-Kapselung; Fehlerklassifikation (401/403 → `auth_failed`-Health, 5xx/Timeout → transient); Versions-/Server-ID-Erkennung |
| `JellyfinItemTranslator` | Übersetzer (pure) | `Item` ↔ `CanonicalMediaRef` (inkl. ProviderIds-Extraktion, Pfad-Normalisierung) |
| `JellyfinUserDataTranslator` | Übersetzer (pure) | `UserData` ↔ `CanonicalPlayState` (Ticks↔ms, Datums-Fallback-Kennzeichnung) |
| `JellyfinPlaystateIngestHandler` | IngestHandler | Zustandsvergleichs-Strom pro User; Container-Filter; Disc-Umleitung |
| `JellyfinPlaystateEgressHandler` | EgressHandler | UserData-Schreiben + Read-back |
| `JellyfinCatalogIngestHandler` | IngestHandler | Item-Mapping-Pflege, optionaler Import |
| `JellyfinDiagnostics` | DiagnosticsProvider | Verbindung, Version, Server-ID, API-Key-Rechte, Webhook-Plugin-Präsenz |

## UI und Flows

Der generische SDK-Instanz-Flow wird um zwei Jellyfin-Schritte erweitert: Benutzer-Mapping-Tabelle (Jellyfin-User-Liste per API, Zuordnungs-Dropdowns, ungemappte ausdrücklich als „wird ignoriert" markiert) und Bibliotheks-Auswahl (`import_libraries`). Die Aktivitäts-Sicht zeigt jellyfin-spezifisch: letzte Playstate-Differenzen pro User (angewandt/Echo/Konflikt), unmatchte Items mit Sprung in den Matching-Review, Webhook-Empfangsstatistik (erkennt totes Webhook-Plugin: Webhooks konfiguriert, aber seit > 24 h nur Poll-Treffer ⇒ Health `degraded` mit Hinweis).

## Edge Cases

* **Jellyfin-Bibliotheks-Rebuild** (alle Item-IDs neu, Server-ID gleich): `jellyfin_item`-Mappings laufen ins Leere (404 beim Egress ⇒ Mapping als verwaist markiert); der nächste Katalog-Ingest baut über ProviderIds/Pfade neu auf. Watch-States sind nicht betroffen (sie hängen an MediaForge-Items).
* **Mehrere Jellyfin-Instanzen** mit denselben Medien: pro Instanz eigene Mappings und eigener Sync; Konflikte zwischen Instanzen löst die normale Strategie (die Watch-State-Historie unterscheidet die Quellen über die Instanz im Actor).
* **Jellyfin-„Un-Played"-Massenaktion** (User setzt Serie auf ungesehen): kommt als N `Played=false`-Fakten; `latest_wins` respektiert sie; die MediaForge-Historie behält die früheren `watched`-Events (append-only) — nichts geht verloren, der aktuelle Zustand folgt der jüngsten Willensäußerung.
* **Trickplay/Intro-Skip-Positionssprünge**: Positions-Updates mit Rückwärtssprüngen sind normale Fakten; MediaForge-Resume folgt der letzten Position, die Watched-Schwelle rechnet über die gemeldete Position, nicht über Spannen (anders als die Disc-Engine, die Rohsignale hat — Jellyfin liefert bereits konsolidierte Positionen).
* **Gelöschte Jellyfin-User**: Diagnostics markiert das Mapping als verwaist; Ingest/Egress für diesen User pausiert mit Health-Hinweis statt Fehlerserie.

## Performance

Der zustandsbasierte Playstate-Abgleich ist der teure Pfad: pro gemapptem User ein paginierter `UserData`-Durchlauf über die gemappten Items. Bei 50k gemappten Items und 5 Usern sind das ~250k Item-Vergleiche pro Intervall — deshalb (1) Vorfilter über `LastPlayedDate`-Fenster, wo die Version es verlässlich liefert (reduziert den Regelfall auf wenige hundert Kandidaten), (2) Vergleich in Speicher-Batches gegen einen einzigen MediaForge-seitigen Bulk-Read (kein N+1 gegen `user_watch_states`), (3) Default-Intervall 15 min mit Webhook-Verkürzung auf Sekunden für die tatsächlich aktiven Items. Der Katalog-Ingest läuft deutlich seltener (Default 24 h) und ist durch die `fields`-Beschränkung und Pagination (Batch 500) auf der Gegenstelle günstig. Das SDK-Rate-Limit (20 req/s) schützt schwache Jellyfin-Hosts; alle Läufe sind ResumableJobs mit Seiten-Checkpoints (Erstsync-Regel des SDK).

## Security

Der API-Key ist ein Admin-Key der Gegenstelle und entsprechend geschützt (Secret-Store des SDK, maskiert überall). Der Connector begrenzt seinen eigenen Wirkungskreis: Egress schreibt ausschließlich UserData gemappter User — keine Lösch-, Bibliotheks- oder Serververwaltungs-Endpunkte sind im Client überhaupt implementiert (nicht vorhandene Fähigkeiten können nicht missbraucht werden). Webhook-Eingang nach SDK-Muster (signierter Pfad, nur Trigger). TLS-Verifikation Default an; `verify_tls=false` nur mit Fingerprint-Pinning (SDK-Regel).

## Tests

Contract-Tests gegen aufgezeichnete Jellyfin-API-Fixtures (10.8/10.9/10.10-Antwortvarianten): Übersetzer-Bijektivität, Ticks/ms-Präzision, Datums-Fallbacks. Szenario-Tests über den SDK-Fake-Rahmen mit Jellyfin-Semantik: Roundtrip ohne Oszillation (MediaForge watched → Jellyfin → Poll → Echo unterdrückt), Rebuild-Recovery (Mappings verwaisen und heilen), Container-Flag-Verwerfung (Season-Played erzeugt keine Container-Writes — Architektur-Regressionstest), Disc-Umleitung (ISO-Item-Playback landet in `disc_playback_sessions`, nie direkt im Watch-State). Ein manueller Testplan gegen echte Jellyfin-Instanzen (Docker-Compose-Testumgebung im Repo) deckt die Webhook-Plugin-Konfiguration ab.

## ADR-Verweise

[ADR-0003](../adr/0003-provider-id-mapping.md) (Item-/User-Identität), [ADR-0004](../adr/0004-episode-granular-watch-state.md) (Container-Verwerfung, Disc-Umleitung). SDK-Regeln aus [connectors/connector-sdk.md](connector-sdk.md).

## Offene Punkte

* **Live-Sessions** (`/Sessions`-Polling für „läuft gerade"-Anzeige im MediaForge-Dashboard): nettes Feature, unspezifiziert; braucht eine Ephemeral-Daten-Konvention (nicht jede Session gehört in die Datenbank).
* **Kollektionen/Playlists-Sync**: Jellyfin-Collections ↔ MediaForge-Tags/Sets ist denkbar, aber Governance-Fragen (welches System hat Feldhoheit?) sind ungeklärt — wartet auf die Katalog-Egress-Entscheidung des SDK.
* **Jellyfin-Trickplay-/Kapitel-Daten** als zusätzliche Kapitelquelle für den [Assembler](../modules/audiobook-assembler.md) (Jellyfin extrahiert Kapitel aus Containern): geringer Mehrwert gegenüber eigener Extraktion, evtl. als Validierungsquelle.
