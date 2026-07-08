# Watch-State (Core-Modul)

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [database/core-schema.md](../database/core-schema.md) (`user_watch_states`, `watch_state_events` — SQL-Schema dort normativ, hier die vollständige Verhaltensspezifikation), [modules/audit.md](audit.md) (Übergänge auditiert), [connectors/connector-sdk.md](../connectors/connector-sdk.md) (Konfliktstrategie externer Quellen). Konsumenten: praktisch jedes Fach-Engine-Modul, das Wiedergabefortschritt erzeugt (Disc-Engine, künftige Player-Integrationen), sowie jeder Connector.

Dieses Kapitel formalisiert, was das Kernschema als Tabellen zeigt, aber bewusst nicht vollständig verhaltensspezifiziert (Schema-Konventionen sind kein Ort für Schwellenwert-Tabellen und Konfliktmatrizen). Es ist das Modulkapitel, auf das jede Modulspezifikation verweist, wenn sie „die Fundament-Schwellenlogik" oder „die kanonischen Watch-State-Actions" erwähnt.

## Motivation

Watch-State ist die am häufigsten geschriebene Fachinformation des Systems (Playback-Events kommen im Sekundentakt, [modules/audit.md](audit.md)) und zugleich die mit der schärfsten Korrektheitsanforderung: Architekturregel 3 verlangt, dass Connectoren und Player **nie** selbst über „gesehen" urteilen, Architekturregel 11 verlangt Episodengranularität auch bei Disc-Quellen. Ein Modul, das diese beiden Regeln durchsetzt, muss mehr können als eine Zeile pro (Benutzer, Werk) verwalten — es muss Schwellen medientypabhängig anwenden, Mehrfachquellen (Disc-Engine, Connectoren, manuelle UI-Aktion, künftige native Player) ohne Widerspruch konsolidieren und eine vollständige, prüfbare Historie führen. Dieses Kapitel ist die vollständige Spezifikation dieser Konsolidierungslogik.

## Problemstellung

**Schwellen sind nicht universell.** „90 % gesehen" ist für einen 22-Minuten-Sitcom-Cold-Open eine andere Aussage als für ein 40-Stunden-Hörbuch (vier ungehörte Stunden bei 90 %, Kernschema-Beispiel) oder ein Musikalbum (ein einzelner Track ist in Sekunden „gehört"). Die Schwelle muss pro Medientyp konfigurierbar sein, ohne dass jeder Aufrufer sie kennen muss.

**Mehrfachquellen ohne Wettlauf.** Zwei Geräte desselben Benutzers können gleichzeitig dieselbe Episode abspielen (Multi-Room-Setup); ein Connector kann einen Fortschritt Tage verzögert nachliefern (Jellyfin-Sync nach Ausfall); die Disc-Engine kann rückwirkend nachverrechnen (Mapping-Bestätigung nach Tagen). Kein Fall darf einen bereits fortgeschritteneren Zustand durch einen älteren Fakt zurückwerfen — aber „älter" bezieht sich auf **Ereigniszeit**, nicht Ankunftszeit.

**Resume vs. Rewatch.** Ein Benutzer, der eine bereits gesehene Episode erneut startet, erzeugt einen fachlich anderen Fall als einer, der eine unterbrochene Wiedergabe fortsetzt — beide sehen sich zunächst identisch an (Position wächst von 0 oder von einer Zwischenposition), aber `play_count` und `first_played_at` müssen sich unterschiedlich verhalten.

**Abandonment.** Ein Benutzer, der eine Serie nach zwei Folgen nie wieder anrührt, hat weder „gesehen" noch ist er „in Arbeit" im Sinne einer aktiven Weiterschauen-Liste — ohne einen dritten Zustand (`abandoned`) verstopfen tote Fortschritte die „Weiterschauen"-Sicht auf ewig.

## Analyse bestehender Lösungen

**Jellyfin/Plex** wenden feste, nicht medientypabhängige Prozent-Schwellen an und kennen kein `abandoned` — ihre „Weiterschauen"-Listen wachsen unbegrenzt mit nie fortgesetzten Fragmenten (verbreitete Nutzerklage in beiden Communities). **Audiobookshelf** differenziert immerhin `isFinished` als expliziten Fakt getrennt von Prozent-Position — genau das Vorbild für die hiesige Trennung „expliziter Fakt vs. Schwellenwert-Ableitung" ([Audiobookshelf-Connector](../connectors/audiobookshelf.md)). **Trakt.tv** führt Rewatch als expliziten Zähler mit Datumshistorie — Vorbild für `play_count`/`watch_state_events`. Die Synthese: explizite Fakten (Connector meldet „isFinished"/„Played") haben Vorrang vor Schwellenwert-Ableitung aus Positionsdaten, aber nur, wenn die Fakten-Quelle vertrauenswürdig deklariert ist (Architekturregel 3 bleibt: Schwellen wendet MediaForge an, auch wenn ein externer Fakt vorliegt — der Fakt ist Eingabe, nicht Urteil, siehe Konfliktkatalog unten).

## Architekturentscheidung

**Eine zentrale Action-Familie, keine Nebenwege**: `RecordPlaybackProgress` (Positionsfortschritt, alle Quellen), `MarkWatched`/`MarkUnwatched` (explizite manuelle/Fakt-Markierung), `AbandonWatchState` (Übergang in `abandoned`), `ResetWatchState` (vollständiger Rücksetzer, selten, auditiert). Jede dieser Actions ist die **einzige** Schreibstelle ihres Übergangstyps (Kernschema-Invariante, hier vollständig ausformuliert); kein Connector, kein Player-Protokoll, keine UI-Komponente schreibt `user_watch_states` direkt.

**Schwellen als Medientyp-Konfiguration** (`watch_state.thresholds`-Setting, typisierte Settings-Klasse): jeder konsumierbare `media_type` trägt einen Prozentsatz und eine „Resume löschen ab"-Regel:

| Medientyp | Watched-Schwelle (Default) | Begründung |
|---|---|---|
| `movie` | 90 % | Abspann-Zeit ist bei Filmen kurz relativ zur Gesamtlänge |
| `episode` | 90 % | wie Film; Doppelfolgen laufen unabhängig (Disc-Engine-Konsequenz) |
| `audiobook` | 99 % | Kernschema-Beispiel: 90 % eines 40-Stunden-Werks sind vier Stunden — inakzeptabel als „gesehen" |
| `track` | 95 % | Musik-Ausblendungen/Outros sind kurz, aber ein Skip vor dem Ende soll nicht als „gehört" zählen |
| `comic_volume` | 95 % | seitenbasierte Bezugsdauer (Lesefortschritt als Pseudo-„Position") |
| `ebook` | 95 % | wie Comic; Lesefortschritt aus Connector-/Reader-Metadaten, sofern integriert |

Schwellen sind Betreiber-Settings (Fundament-Konvention, [architecture/overview.md](../architecture/overview.md): „Datenbank-gestützte Settings … versioniert im Audit"), nicht Code-Konstanten — ein Betreiber, der Hörbücher schon ab 95 % als gehört zählen will, ändert eine Zahl, keine Logik.

**Explizite Fakten vs. Schwellenwert-Ableitung**: Eine Quelle kann zwei Arten von Information liefern — eine **Position** (Zahl, aus der die Schwelle das Urteil ableitet) oder einen **expliziten Fakt** („Played=true", „isFinished=true", Connector-SDK-Terminologie). Beide landen in derselben Action (`RecordPlaybackProgress` mit optionalem `explicitFact`-Feld im Input-DTO), aber die Verarbeitung unterscheidet sich: Ein expliziter Fakt setzt `watched` **unabhängig** von der mitgelieferten Position (ein Connector, der „Played=true" bei 60 % Position meldet, wird respektiert — der Benutzer hat es dort beendet erklärt, sei es durch Skip-Intro oder bewusstes Abbrechen-und-als-gesehen-Markieren in der Gegenstelle); eine reine Position wird gegen die Medientyp-Schwelle geprüft. Das ist keine Ausnahme von Architekturregel 3, sondern deren korrekte Anwendung: MediaForge entscheidet **weiterhin**, welche Bedeutung ein „Played=true"-Fakt hat (nämlich: sofortiges `watched`, keine Nachprüfung) — die Entscheidungsregel liegt in MediaForge-Code, nicht in der Gegenstelle.

## Zustandsmaschine

```
                    ┌──────────────┐
        Progress   │              │  Progress ≥ Schwelle
      ───────────► │ in_progress  │ ───────────────────────► watched
                    │              │                              │
                    └──────┬───────┘                              │
                           │ AbandonWatchState                     │ MarkUnwatched /
                           │ (manuell oder Inaktivitäts-Regel)      │ neuer Rewatch-Start
                           ▼                                       ▼
                    ┌──────────────┐                         (zurück zu in_progress
                    │  abandoned   │                          oder direkt watched bei
                    └──────────────┘                          explizitem Fakt)
```

Es gibt **keinen** impliziten Übergang von `abandoned` zurück in einen aktiven Zustand — ein neuer `RecordPlaybackProgress`-Aufruf auf ein `abandoned`-Item verhält sich exakt wie auf ein Item ohne Zeile (frischer Start, neues `first_played_at` bleibt aber erhalten — siehe Rewatch-Regeln unten, `first_played_at` wird nie überschrieben, nur `last_played_at`).

### Abandonment-Regel (normativ)

`abandoned` entsteht **nie automatisch als Timeout** (keine stille Umdeutung von Benutzerabsicht) — es ist ausschließlich erreichbar über: (1) explizite Benutzeraktion („als abgebrochen markieren" im UI), (2) eine optionale, standardmäßig **deaktivierte** Rule-Engine-Regel (`age.days_since(last_played_at, n)` kombiniert mit `media.watch_status(in_progress)`, [Rule-Engine-Prädikatreferenz](rule-engine/predicate-reference.md)) — ein Betreiber kann sich also eine Automatik **bauen**, aber das Fundament erzwingt sie nicht. Diese Zurückhaltung ist bewusst: Ein automatisch verworfener Fortschritt, den der Benutzer eigentlich fortsetzen wollte, ist ein Vertrauensbruch derselben Klasse wie ein falsches Disc-Mapping.

## Mehrfachquellen-Konsolidierung

### Gleichzeitige Sessions (Multi-Device)

Zwei Geräte, dieselbe Episode, überlappende Zeitfenster: Jedes `RecordPlaybackProgress`-Ereignis trägt seine eigene `occurred_at`; die Action wendet **„jüngste Ereigniszeit gewinnt"** auf `position_ms` an, unabhängig davon, welches Gerät „zuerst" schrieb (Ankunftsreihenfolge ist irrelevant, Ereigniszeit entscheidet — dieselbe Regel wie die Connector-Konfliktstrategie, hier auf geräte-interne Konkurrenz verallgemeinert). `play_count` wird **nicht** bei jedem Positions-Update erhöht, sondern nur beim Übergang in `watched` (ein Play-Count-Inkrement pro tatsächlichem Abschluss, nicht pro Session-Start — zwei Geräte, die dieselbe Sitzung „teilen", erzeugen keinen doppelten Play-Count).

### Verzögerte Nachlieferung (Retroaktive Events)

Ein Connector liefert ein `occurred_at` aus der Vergangenheit (Sync nach Tagen Ausfall, [Connector-SDK](../connectors/connector-sdk.md)): Die Action prüft, ob das nachgelieferte Ereignis den **aktuellen** `user_watch_states`-Stand verändern würde, wenn es zeitlich vor dem letzten bekannten Ereignis läge — wenn ja, wird der aktuelle Stand **nicht** rückwirkend verändert (ein späteres, bereits verarbeitetes `watched` wird nicht durch ein nachgeliefertes älteres `in_progress` zurückgesetzt), aber das Ereignis wird trotzdem in `watch_state_events` mit korrektem `occurred_at` eingefügt (die Historie bleibt vollständig und zeitlich korrekt sortiert, auch wenn sie den aktuellen Zustand nicht mehr beeinflusst). Diese Regel ist die Verallgemeinerung dessen, was der Jellyfin-Connector als „latest_wins respektiert die Historie" beschreibt.

### Rewatch-Semantik

Ein `RecordPlaybackProgress`- oder `MarkWatched`-Aufruf auf ein bereits `watched`-Item mit **neuer** Positionsaktivität (Position beginnt wieder nahe 0, `occurred_at` liegt nach `watched_at`) wird als Rewatch erkannt: `status` wechselt zurück auf `in_progress`, `play_count` bleibt bis zum erneuten Erreichen der Schwelle unverändert, `first_played_at` bleibt unangetastet, `watched_at` wird erst beim erneuten Schwellen-Erreichen aktualisiert (und `play_count` dann inkrementiert). Ein `MarkWatched`-Aufruf **ohne** vorherige Positionsaktivität (Direktsprung „nochmal als gesehen markieren" im UI) inkrementiert `play_count` sofort (der Benutzer erklärt explizit einen weiteren Durchlauf, auch ohne dass MediaForge ihn in Echtzeit beobachtet hat).

## Laravel-Klassen (vollständig, Kernschema-Ergänzung)

| Klasse | Typ | Vertrag |
|---|---|---|
| `RecordPlaybackProgress` | Action | wie [core-schema.md](../database/core-schema.md) beschrieben; hier vollständig: wendet Medientyp-Schwelle **oder** expliziten Fakt an, Mehrfachquellen-Konsolidierung (oben), emittiert `EpisodeWatched` nur bei echtem Übergang |
| `MarkWatched` / `MarkUnwatched` | Action | manuelle Markierung; `MarkUnwatched` setzt `status=NULL`-Äquivalent (Zeile bleibt für Historie, `status` auf einen Vor-Zustand oder Löschung der Zeile bei nie vorhandenem Fortschritt — Implementierungsdetail: Löschung nur, wenn `play_count=0` und keine Events existieren, sonst `abandoned` mit Notiz) |
| `AbandonWatchState` | Action | manueller oder Rule-Engine-getriggerter Übergang; Audit mit Auslöser-Kennzeichnung (`manual`/`rule:<name>`) |
| `ResetWatchState` | Action | vollständiger Rücksetzer (`admin`/`manager`-Werkzeug für Fehlerkorrektur); löscht **nicht** `watch_state_events` (Historie bleibt, ein `reset`-Event wird angehängt) |
| `WatchStateThresholdPolicy` | Service (pure) | `thresholdFor(mediaType): float`, `shouldResumeKeep(mediaType, positionRatio): bool` — die Schwellen-Logik als pure Service (Muster 2, [contracts-reference.md](../developer-handbook/contracts-reference.md)), gegen Settings kompiliert |

## API-Endpunkte (Ergänzung zum Kernschema)

| Route | Zweck | Rolle |
|---|---|---|
| `POST /api/v1/items/{ulid}/watch-state` | manuelle Markierung (`watched`/`unwatched`/`abandoned`) | member (eigener State) |
| `GET /api/v1/items/{ulid}/watch-state/history` | `watch_state_events`-Auszug (Diagnose, „warum steht das hier auf gesehen") | member (eigener State), manager (fremder, aggregiert) |
| `POST /api/v1/items/{ulid}/watch-state/reset` | vollständiger Rücksetzer | manager |
| `GET /api/v1/settings/watch-state-thresholds` | aktive Schwellen je Medientyp | admin |

## UI

**Item-Detail**: Watch-Status-Icon mit Klartext-Tooltip („95 % gesehen, zuletzt 12.05."), Ein-Klick „als gesehen/ungesehen markieren", „Fortschritt verwerfen" (→ `abandoned`, mit Bestätigungsdialog — irreversibel im UI-Sinn, auch wenn die Historie bleibt). **Verlaufs-Popover**: `watch_state_events`-Zeitleiste (Design-System-Evidence-Popover-Muster) — beantwortet die „warum steht das hier auf gesehen"-Frage direkt am Item, ohne Admin-Umweg. **Weiterschauen-Leiste**: liest ausschließlich `in_progress`, nie `abandoned` (die Kernmotivation dieses Zustands).

## Edge Cases

* **Schwellenwert-Änderung im laufenden Betrieb**: wirkt nur auf künftige Auswertungen; bestehende `watched`-Zeilen werden nicht rückwirkend neu bewertet (ein rückwirkender Neubewertungs-Job ist ein expliziter Admin-Befehl, kein impliziter Nebeneffekt einer Setting-Änderung).
* **Container-Aufruf** (Versuch, `show`/`season`/`album` als Subjekt zu übergeben): harte Ablehnung in der Action (Kernschema, hier bestätigt) — betrifft auch neue Player-Integrationen, die versehentlich Container-IDs senden.
* **Negative/Sprung-Positionen** (Player meldet `position_ms > duration_ms` durch Rundungsfehler): Position wird auf `duration_ms` geklemmt, nie als Fachfehler behandelt (Toleranz für reale Player-Ungenauigkeit).
* **Gelöschtes Item mit offenem Fortschritt**: `user_watch_states` kaskadiert mit dem Item (Kernschema-FK); `watch_state_events` ebenso — die Historie eines gelöschten Werks hat keinen Wert ohne das Werk selbst (anders als die Disc-Playback-Sessions, die bewusst über Datei-Löschung hinweg erhalten bleiben, weil dort die Datei, nicht der Katalogeintrag, verschwindet).

## Performance

`user_watch_states`-Upserts sind PK-basiert und günstig (< 10 ms, [Query-Katalog](../database/query-catalog.md) Q-10); `watch_state_events`-Inserts sind Partition-lokal und append-only (kein Update, kein Index-Rebalancing). Die Threshold-Policy ist ein In-Memory-Lookup gegen gecachte Settings (kein DB-Zugriff pro Aufruf).

## Security

Watch-States sind strikt benutzergebunden (Kernschema-Zugriffsmatrix: kein Admin sieht fremde Einzel-Watch-States, nur Aggregatstatistik). Reset-Aktionen sind `manager`-Werkzeug mit Pflicht-Audit-Kommentar (Missbrauchsschutz gegen „fremden Fortschritt gelöscht ohne Begründung").

## Tests

Schwellen-Matrix (jeder Medientyp × Grenzwert ± 1 Prozentpunkt); expliziter-Fakt-vs-Position-Vorrangregel; Mehrfachquellen-Konsolidierung (zwei gleichzeitige Sessions, verzögerte Nachlieferung — je ein konstruiertes Szenario mit erwartetem Endzustand); Rewatch-Zähler-Korrektheit (Direkt-Markierung vs. beobachteter Durchlauf); Abandonment-Nichtautomatik (Property-Test: kein Zeitablauf allein erzeugt `abandoned` ohne explizite Aktion oder aktivierte Regel); Container-Ablehnung (Invarianten-Suite-Mitglied, [testing.md](../developer-handbook/testing.md)).

## ADR-Verweise

[ADR-0004](../adr/0004-episode-granular-watch-state.md) (Episodengranularität — die Grundlage jeder Regel hier), [ADR-0006](../adr/0006-action-level-audit.md) (Übergänge auditiert). Setzt um: Architekturregeln 3 und 11 vollständig ausformuliert.

## Offene Punkte

* **Konfigurierbare Rewatch-Zählweise** (zählt eine abgebrochene und neu gestartete Wiedergabe als Rewatch oder als Fortsetzung derselben „Sitzung"?): aktuelle Regel ist bewusst einfach (Positionsnähe zu 0 + Zeitpunkt nach `watched_at`); eine Grace-Period-Konfiguration ist denkbar, aber unspezifiziert.
* **Soziale Watch-States** (gemeinsames Schauen, „wir haben gesehen" für Paare/Familien auf einem Account): kein Anwendungsfall bisher benannt.
