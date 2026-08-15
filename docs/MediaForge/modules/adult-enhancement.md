# Adult Enhancement Modul

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md).
UI: [Adult UI Enhancement](../ui-ux/adult-ui-enhancement.md).
Zielengine: [Adult Engine Target](adult-engine-target.md).

## Produktregel: unsichtbarer Private Mode

Adult ist **kein normal sichtbarer MediaForge-Bereich**.

Solange Private-/Adult-Mode gesperrt ist:
- kein Adult-Menüpunkt;
- keine Adult-Kachel auf Home;
- keine Adult-Suchergebnisse oder Autocomplete-Vorschläge;
- keine Adult-Inhalte in Continue Watching / Recently Added;
- keine Performer-/Studio-Namen in Activity/Notifications;
- keine Adult-Statistiken;
- keine Thumbnail-/Artwork-Preloads;
- keine unterscheidbare API-Antwort, die die Existenz eines Adult-Objekts verrät.

Der Einstieg erfolgt nur über einen bewusst geschützten Flow, z. B. Profil → Privater Modus → Passwort/PIN. Optional kann später ein konfigurierbarer Shortcut existieren. Nach Sperren wird der Adult-Kontext vollständig entfernt.

Die Filterung ist serverseitig. „Im DOM verstecken“ ist keine Security.

## Ziel

Adult wird langfristig ein vollständiger, schöner, privater Media-Server innerhalb von MediaForge:
- Scenes;
- Performer;
- Studios/Networks/Brands;
- Galleries/Remote Assets;
- lokale Files/Versionen;
- Player/Progress;
- Collections;
- Quality/Duplicates;
- Coverage;
- Metadata Sources;
- Historical Sources;
- Review/Matching.

Das sichtbare UI ist MediaForge. Der spätere Media-Core ist Stash-derived.

## Library-driven Aktivierung

Es wird **keine globale Performer-Datenbank auf Vorrat gespiegelt**.

Standard:
1. lokale Adult-Datei wird von der Library entdeckt;
2. Scene/File wird analysiert und gematcht;
3. Performerinnen werden erkannt;
4. nur Performerinnen, die in mindestens einem lokalen Video vorkommen, werden als aktive Sync-Ziele geführt;
5. manuell gepinnte/Favoriten dürfen als Ausnahme erhalten bleiben;
6. erst nach bewusstem Sync werden große Metadaten-, Scene- und Remote-Asset-Abfragen durchgeführt.

## Scene ist nicht File

```text
AdultScene
  0..n LocalMediaFiles
  0..n SourceRecords
  0..n RemoteAssets
  n PerformerCredits
```

Eine Scene kann mehrere lokale Versionen (720p/1080p/4K, H.264/HEVC/AV1 usw.) besitzen. Datei-Verschieben oder Re-Encoding erzeugt nicht automatisch eine neue Scene.

## Dateinamen

Bevorzugter Standard:

`Studio - YYYY-MM-DD - Performer 1, Performer 2 - Titel.ext`

Im Dateinamen werden standardmäßig nur weibliche Performer ausgegeben. Der kanonische Scene-Cast darf vollständig sein.

Parser muss mehrere konfigurierbare Varianten unterstützen, u. a.:
- `YYYY-MM-DD - Studio - Performer(s) - Titel`
- `Studio - Performer(s) - YYYY-MM-DD - Titel`
- `Performer(s) - Studio - YYYY-MM-DD - Titel`
- ordnerbasierte Muster;
- frei konfigurierbare Token-Templates.

**Nie ungefragt umbenennen.** Parsing liefert Kandidaten; Dateiumbenennung ist eine spätere, explizite Aktion mit Preview.

## Library Sync

Ein Jellyfin-artiger Sync startet asynchrone Jobs:
- Inventarisierung;
- ffprobe/technische Metadaten;
- Fingerprints;
- Scene Matching;
- Performer-Aktivierung;
- Thumbnail-Erzeugung;
- Hover-/Preview-Clips;
- Scrubber/Trickplay-Sprites;
- Quality-Auswertung;
- optionaler Metadaten-Sync.

UI bleibt währenddessen bedienbar und zeigt Fortschritt.

## Metadatenquellen

Quellen sind Datenlieferanten, niemals kanonische Identität.

Primäre/ergänzende Quellen können sein:
- StashDB;
- ThePornDB;
- FansDB;
- Original-Studio-/Paid-Sites;
- Creator-/Direct-Paid-Profile, soweit legitim zugänglich;
- offizielle Tube-Profile für Performer/Creator, bei denen Tube-Inhalte tatsächlich Teil des Primärkatalogs sind;
- historische Studio-/Brand-/Distributor-Quellen;
- weitere zuverlässige Metadatenquellen.

Bei Studio-Performerinnen werden beliebige Tube-Reuploads **nicht** als Primärquelle behandelt. Bei Tube-nativen Creatorinnen können offizielle Tube-Profile dagegen Primärquelle sein.

## Historische Quellen

Eine nicht mehr erreichbare Studioseite löscht keine Scene.

Source Records speichern mindestens:
- source type;
- external id;
- URL;
- first_seen_at;
- last_seen_alive_at;
- last_checked_at;
- current status;
- extrahierte Felder/Provenienz.

Statusbeispiele:
`active`, `historical`, `removed`, `source_dead`, `database_only`, `archive_only`, `unverified`, `conflict`.

## Remote Assets

Bilder/Galerien werden standardmäßig **nicht dauerhaft gespiegelt**.

Gespeichert werden:
- URL;
- Thumbnail URL;
- Quelle;
- Zuordnung;
- Dimensionen, soweit bekannt;
- Availability/last checked.

Client nutzt Lazy Loading und kleine Varianten. Optionaler Cache ist löschbar. Permanentes Archivieren ist eine spätere explizite Benutzeraktion.

## Canonical Merge

Jede Scene/Person/Studio-Entität hat eine eigene MediaForge-ID. Source-Felder werden mit Provenienz gespeichert. Manuelle Overrides gewinnen gegen spätere automatische Syncs, bis der Benutzer sie freigibt.

Identity Resolver:
- Namen/Aliase;
- Source IDs;
- URLs;
- gemeinsame Scenes;
- Studio-/Creator-Profile;
- weitere harte Metadaten.

Unsichere Identitäten werden **nicht** automatisch gemergt; sie landen im Review.

## Zero-Leak Security

Serverseitige Scopes gelten für:
- Query;
- Search Index;
- Recommendations;
- Home Rows;
- Activity;
- Notifications;
- WebSocket/Event Payloads;
- Artwork URLs;
- Cache Keys;
- Browser Metadata;
- Export/Backup UI.

Unautorisierte Detailzugriffe verhalten sich wie unbekannte IDs.

## Akzeptanzkriterien

- normaler Modus verrät nicht, dass Adult aktiviert/gefüllt ist;
- Adult hat ein eigenständiges Premium-Media-UI, aber dieselbe MediaForge-Designsprache;
- Library Sync erzeugt technische Media-Artefakte asynchron;
- nur relevante lokale Performerinnen werden standardmäßig voll synchronisiert;
- Scene und File sind getrennt;
- entfernte Quellen löschen keine historischen Daten;
- Remote Assets müssen nicht lokal gespeichert werden;
- keine automatische Änderung überschreibt einen manuellen Override.

---

# Erweiterung 2026-08 – verbindliche Detailmodule

Die nachfolgenden Spezifikationen sind Teil dieses Adult-Moduls und haben bei Überschneidungen Vorrang vor älteren, weniger präzisen Formulierungen:

- [Adult Full-Scene Analysis, Event Timeline und Taxonomie](adult-analysis-and-taxonomy.md)
- [Adult Scene Lineage, Studio-Historie und Catalog Completeness](adult-lineage-and-catalog.md)
- [Acquisition Center](acquisition-center.md)
- [Media Editions and Lineage](media-editions-and-lineage.md)
- [Routing/Adult URLs](../architecture/routing-and-public-urls.md)

## Zusätzliche verbindliche Regeln

1. Adult URL Prefix ist standardmäßig `/adult`.
2. Strict Private URLs sind optional, nicht Default.
3. Tags können zeitbezogene Events statt bloßer Scene-Labels sein.
4. Audio Events wie `crying`/`screaming` sind First-Class Events.
5. Base Tags unterstützen hierarchische Attribute (z. B. `puke` + consistency/appearance/amount).
6. Full Scan speichert messbare Coverage; AI Confidence wird separat dargestellt.
7. Evidence/Model Version/Verification müssen für AI Events nachvollziehbar sein.
8. `checked_absent` bzw. geprüft-nicht-vorhanden muss ausdrückbar sein.
9. Scene Lineage unterscheidet Original/Re-release/Compilation/Alternate Edit/Local Edition.
10. Performer Coverage zeigt Known/Local/Missing/Historical/Unresolved.
11. Datum wird als mehrere semantische Date Types mit Field Provenance gespeichert.
12. Acquisition-/Import-Herkunft ist nachvollziehbar, aber Adult Acquisition bleibt im Locked Mode vollständig verborgen.
