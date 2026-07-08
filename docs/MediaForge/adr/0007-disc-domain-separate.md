# ADR-0007: Disc-Strukturen als eigene Domäne neben dem Katalog

Status: accepted · Bezug: [modules/disc-engine.md](../modules/disc-engine.md)

## Kontext

Disc-Images enthalten Playlists, Clips, Segmente, Menüs und Bonusmaterial. Es stellt sich die Modellfrage: Sind diese Strukturen Katalogeinträge (`media_items`) oder eine eigene Domäne? Jellyfin-artige Systeme verflachen Discs in ihr Item-Modell und verlieren dabei die innere Struktur; ein naives „Playlist = media_item" würde die Struktur zwar erhalten, aber den Katalog mit Nicht-Werken fluten.

## Entscheidung

Disc-Strukturen (disc_images, disc_playlists, disc_clips, disc_segments, disc_menus, disc_sets) sind eine eigene Domäne mit eigenen Tabellen. Sie **zeigen** über `disc_episode_mappings` auf kanonische Katalog-Items (Episode, Film), sind aber selbst keine. Das Modell trennt drei Referenzebenen: Struktur (Analyse-Ergebnis, stabil zur Datei), Interpretation (Klassifikation, Segmente, Mappings — review-pflichtig, versioniert) und Nutzung (Playback-Sessions/-Events — append-only). Die Dateizuordnung im Katalog (`edition_files`) bindet das Image an den Container (Season/Show bzw. Film-Edition) und drückt Besitz aus; das Episode-Mapping drückt Inhalt aus.

## Konsequenzen

* Watch-State-, Such- und Sync-Logik operieren weiterhin nur über echte Werke; keine Sonderfälle für Playlists in Querschnittsmodulen.
* Der Disc-Status ist als View über Mappings + Watch-States definiert — es existiert kein beschreibbarer Disc-Zustand (strukturelle Durchsetzung von Architekturregel 11).
* Neuanalysen dürfen die Strukturebene ersetzen, ohne die Interpretationsebene zu verlieren (Mapping-Stabilität über Struktur-Signatur).
* Der Preis ist ein eigener Satz Tabellen und ein Mapping-Indirektionsschritt in Playback-Pfaden — bewusst bezahlt für die Trennung von Veröffentlichungsform und Werk.

## Erwogene Alternativen

Playlists als `media_items` (flutet den Katalog, unterläuft die Watch-State-Regel, zwingt Querschnittsmodule zu Sonderfällen); Disc als opake Einzeldatei (Status quo der Referenzsysteme — genau das zu behebende Defizit); Episoden-Extraktion statt Modellierung (gibt Menüs auf, verdoppelt Speicher; als optionaler Export-Workflow weiterhin denkbar).
