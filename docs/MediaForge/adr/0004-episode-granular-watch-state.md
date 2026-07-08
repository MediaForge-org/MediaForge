# ADR-0004: Watch-State auf Episoden-Granularität, Disc-Status nur abgeleitet

Status: accepted · Bezug: [modules/disc-engine.md](../modules/disc-engine.md), [database/core-schema.md](../database/core-schema.md), Architekturregel 11

## Kontext

Disc-Images (Blu-ray/UHD/DVD als ISO, BDMV, VIDEO_TS) enthalten häufig mehrere Episoden. Alle Referenzsysteme führen Wiedergabestatus auf Datei- bzw. Disc-Ebene: Kodi kann Disc-Menüs abspielen, kennt aber nur „Disc gesehen/nicht gesehen"; Jellyfin sieht eine ISO als ein Item. Wer Folge 2 von 6 schaut, hat nirgends „Folge 2 gesehen". Fachlich ist das falsch, und es zerstört den Wert episodenbasierter Features (Weiterschauen, Staffelfortschritt, Sync mit Jellyfin-Einzeldateien derselben Episoden).

## Entscheidung

Watch-State existiert ausschließlich auf konsumierbaren Katalogeinheiten (Episode, Film, Hörbuch, Track, …), nie auf Discs und nie auf Containern. Playback auf einer Disc wird über das Episode-Mapping der Disc-Engine (Playlist/Segment → Episode) auf Episoden abgebildet; Resume-Positionen werden pro Episode geführt, in Episodenzeit (nicht Disc-Zeit). Der Disc-Status (ungesehen/teilweise/gesehen) ist eine abgeleitete, nicht direkt setzbare Sicht über die gemappten Episoden. Es existiert keine Schreiboperation für Disc-Watch-State; die zentrale Action `RecordPlaybackProgress` lehnt Disc- und Container-Subjekte hart ab. Playback auf ungemappten oder unsicher gemappten Playlists erzeugt niemals automatisch ein „gesehen" — es wird als ungemapptes Playback-Ereignis vorgehalten und, wo sinnvoll, ein Review erzeugt; nach Mapping-Bestätigung kann es nachträglich angerechnet werden.

## Konsequenzen

* Die Disc-Engine muss Playlists, Clips und Segmente vollständig modellieren — der Preis der Korrektheit; siehe Disc-Engine-Kapitel.
* Externe Player liefern Positionen, nie Zustände; die Watched-Schwellen-Logik liegt zentral in MediaForge.
* Dieselbe Episode kann über Disc und Einzeldatei (Jellyfin) konsumiert werden und hat trotzdem genau einen Watch-State.
* Unsichere Mappings kosten Komfort (Review nötig), nie Korrektheit.

## Erwogene Alternativen

Disc-Level-Status mit Prozentheuristik (Kodi-artig) — genau der Fehler, den MediaForge beheben soll; automatisches Mapping ohne Confidence-Schwelle — erzeugt falsche „gesehen"-Markierungen, die schlimmer sind als fehlende; virtuelle Dateien pro Episode (Split der ISO) — verletzt Regel 4 (Originale unangetastet) und zerstört Menü-Playback.
