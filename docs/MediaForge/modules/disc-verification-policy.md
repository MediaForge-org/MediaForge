# Disc Verification Policy — Verified-only Episode Mapping

Status: **normativ**

## Grundsatz

MediaForge darf bei ISO/BDMV/VIDEO_TS **niemals raten**, welche Playlist zu welcher Episode gehört.

Ein Vorschlag kann intern einen Score besitzen. Ein Score ist keine Wahrheit und darf kein automatisches Mapping auslösen.

## Status

Ein Disc-Episode-Mapping besitzt genau einen fachlichen Verifikationsstatus:

- `unresolved` — nicht ausreichend bewiesen;
- `verified` — alle automatischen Verifikationsregeln erfüllt;
- `manual` — explizit durch Benutzer bestätigt;
- `rejected`;
- `superseded`.

Nur `verified` und `manual` dürfen Watch-State auf eine Episode übersetzen.

## Laufzeit

Die Disc-Analyse speichert Laufzeiten mindestens in Millisekunden, soweit das Quellformat diese Präzision zuverlässig liefert.

Für externe Episodenreferenzen gilt:
- bevorzugt sekundengenaue Laufzeit;
- bloße Minutenangaben wie „43 min“ sind **nicht verifikationsfähig**;
- Rundungsregeln einer Quelle müssen bekannt sein;
- Edition/Region/Cut müssen berücksichtigt werden;
- eine Differenz darf nur akzeptiert werden, wenn sie durch eine dokumentierte, deterministische Container-/Timebase-Regel erklärt wird; kein frei gewähltes Toleranzfenster.

## Externe Recherche

MediaForge recherchiert selbst. Der Benutzer soll nicht Laufzeiten manuell googeln müssen.

Provider-Adapter dürfen später u. a. verwenden:
- legitimerweise zugängliche Streaming-Service-Metadaten;
- offizielle Episoden-/Studio-/Publisher-Daten;
- spezialisierte Disc-/Episode-Datenbanken;
- weitere technische Kataloge mit ausreichend präziser Laufzeit.

Jeder Provider deklariert:
- Autorität/Vertrauensstufe;
- Genauigkeit und Rundungsverhalten;
- Edition-/Region-Unterstützung;
- Nutzungs-/Rate-Limit-Regeln;
- `last_checked_at`.

Keine Zugangskontrollen umgehen.

## Auto-Verified Bedingungen

Automatisches `verified` ist nur erlaubt, wenn **alle** Bedingungen erfüllt sind:

1. Disc/Season/Edition-Kontext ist eindeutig.
2. Playlist-Struktur wurde lokal erfolgreich analysiert.
3. Der Episode-Kandidat besitzt eine verifikationsfähige externe Referenzlaufzeit.
4. Playlist- und Referenzlaufzeit stimmen gemäß dokumentierter Timebase-Regel eindeutig überein.
5. Kein zweiter Episode-Kandidat erfüllt dieselben harten Bedingungen.
6. Kein zweiter Playlist-Kandidat konkurriert um dieselbe Episode unter denselben Bedingungen.
7. Mindestens eine autoritative Quelle oder eine definierte Kombination unabhängiger vertrauenswürdiger Quellen bestätigt die Identität.
8. Es gibt keine gleich- oder höherwertige widersprechende Quelle.
9. Edition/Region/Cut-Widersprüche sind ausgeschlossen.
10. Alle verwendeten Evidence-Felder werden persistiert und sind auditierbar.

Wenn irgendein Punkt fehlt: `unresolved`.

## Beispiele

### Darf automatisch verifiziert werden

- Disc eindeutig Staffel 3 Disc 2.
- Playlist exakt 00:43:17.xxx.
- Autoritative Referenz nennt S03E07 00:43:17.
- Keine andere Episode/Playlist passt.
- Keine widersprechende Quelle.

### Darf NICHT automatisch verifiziert werden

- Provider sagt nur „43 min“.
- Zwei Episoden dauern beide 43:17.
- Reihenfolge sieht plausibel aus.
- Confidence = 99.9 %, aber keine sekundengenaue Referenz.
- Streaming-Service und Disc-DB widersprechen sich.
- Extended Cut vs. Broadcast Cut unklar.
- Playlist passt nur nach frei gewählter ±30-s-Toleranz.

## Retry statt Benutzerarbeit

`unresolved` ist kein Fehler. MediaForge darf bei späteren Provider-/Source-Updates automatisch erneut prüfen. Der Benutzer muss nur eingreifen, wenn er das ausdrücklich will.

## Playback

Unverifiziertes Disc-Playback wird als Rohsession gespeichert, aber nicht auf Episoden-Watch-State gebucht. Wird das Mapping später `verified`/`manual`, darf eine klar zuordenbare gespeicherte Session nach den Playback-Regeln neu verarbeitet werden.

## Tests

Pflicht:
- Equal-runtime ambiguity => unresolved;
- rounded-minutes-only => unresolved;
- contradictory sources => unresolved;
- wrong edition => unresolved;
- unique exact verified evidence => verified;
- no automatic watched state from unresolved mapping.

## Runtime Evidence Storage 2026-08

Jede automatische Disc-Verifikation muss reproduzierbare Evidence referenzieren:

- local disc fingerprint;
- playlist id;
- exact measured duration/timebase;
- disc/edition/region identity;
- external source fact(s);
- source retrieval timestamp;
- normalization rule;
- conflict check result;
- verification algorithm version.

Wird eine externe Quelle später geändert, bleibt die ursprüngliche Evidence des bereits geprüften Mappings auditierbar. Revalidation darf den Zustand ändern, aber nicht die Historie löschen.
