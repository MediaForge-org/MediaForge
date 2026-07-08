# Metadata Enrichment

Zurück zur [Masterdatei](../MediaForge_Master_Engineering.md). Abhängigkeiten: [database/core-schema.md](../database/core-schema.md) (`provider_ids`, `metadata_locked_fields`, Editionen, Review-Tasks), [architecture/overview.md](../architecture/overview.md) (Jobs, Events, Scan-Pipeline), [modules/audit.md](audit.md). Verwandt: [modules/data-quality.md](data-quality.md) (konsumiert Enrichment-Bestand als Referenz), [modules/knowledge-graph.md](knowledge-graph.md) (`ImportProviderRelationsJob` hängt an Enrichment-Läufen), [connectors/connector-sdk.md](../connectors/connector-sdk.md) (Feld-Governance für Egress — hier normativ definiert), [modules/dedup-fingerprinting.md](dedup-fingerprinting.md) (Provider-ID-Kollisionen als Dubletten-Signal).

**Vertiefungen**: [Provider-Referenz](enrichment/provider-reference.md)

## Motivation

Zwischen Scan und nutzbarem Katalog liegt die Anreicherung: Aus „Datei erkannt und gematcht" wird erst durch Provider-Metadaten ein Katalogeintrag mit Titel, Beschreibung, Personen, Terminen, Covern. Die Scan-Pipeline der Masterdatei endet mit „Matching- und Enrichment-Jobs reagieren auf Events" — dieses Kapitel spezifiziert die Enrichment-Seite: **welche Felder aus welchen Quellen mit welcher Präzedenz in den Katalog fließen, wie Benutzerkorrekturen dauerhaft geschützt bleiben (`metadata_locked_fields`), wie Provider-Drift erkannt und behandelt wird und wie Assets (Cover, Backdrops) als Artefakte verwaltet werden.** Der Matcher selbst (Datei/Ordner → Katalog-Kandidat) ist Fundament-Bestandteil; Enrichment beginnt, wo eine Entität eine Provider-ID trägt.

## Problemstellung

**Feld-Herkunft und -Hoheit.** Ein `media_item` hat Dutzende Felder aus potenziell vier Quellen: Provider (TMDB sagt „Titel X"), Benutzer (Korrektur „Titel Y"), Connector-Ingest (Jellyfin lieferte beim Erstimport „Titel Z") und Ableitung (Sortiertitel aus Titel). Ohne Feld-Governance überschreibt der nächtliche Refresh die Benutzerkorrektur — der klassische Metadaten-Krieg, den `metadata_locked_fields` im Kernschema bereits andeutet. Es fehlt die vollständige Regelmenge: Präzedenz je Feldklasse, Konfliktverhalten, Provenienz-Nachweis.

**Provider-Drift.** Provider-Daten ändern sich: Korrekturen (Laufzeit präzisiert), Umbenennungen (Serientitel-Rebranding), Strukturänderungen (Episode verschoben, Staffel neu geschnitten — der gefürchtete TVDB-Reorder). Manche Änderungen sind erwünschte Verbesserungen, manche zerstören lokale Bezüge (Episoden-Mappings der Disc-Engine hängen an `sort_index`!). Refresh darf deshalb nie blind übernehmen.

**Kosten und Höflichkeit.** Provider-APIs haben Rate-Limits, Auth-Anforderungen und Lizenzbedingungen. Tausende Items naiv zu refreshen ist technisch trivial und operativ verboten. Nötig: Caching, Delta-Verfahren wo verfügbar, Prioritäts-Steuerung (frisch importierte Items vor Bestands-Refresh) und ein globaler Egress-Schalter (Datenschutz: welche Kennungen verlassen das Haus?).

**Mehrquellen-Zusammenführung.** Für ein Hörbuch liefert Audible Sprecher und Kapitel-Metadaten, MusicBrainz die Release-Struktur, OpenLibrary die ISBN-Familie. Felder desselben Items kommen legitim aus verschiedenen Providern; die Zusammenführung braucht deterministische Regeln statt „letzter gewinnt".

## Analyse bestehender Lösungen

**Jellyfin** zeigt das verbreitete Plugin-Provider-Modell (Reihenfolge pro Bibliothek konfigurierbar, Felder „first wins") und dessen Schwäche: Feldhoheit ist implizit, Benutzer-Edits werden per „Lock"-Checkbox pro Item geschützt — feldgranular nur rudimentär, Provenienz fehlt völlig. Übernommen: die Provider-Reihenfolge als konfigurierbares Konzept; ersetzt: Item-Locks durch Feld-Locks mit Provenienz. **Sonarr/Radarr** sind das Vorbild für Drift-Umgang: Sie spiegeln den Provider-Stand lokal (eigene Metadaten-Server als Cache-Schicht), erkennen Episoden-Verschiebungen und lösen Referenzen über stabile Provider-IDs statt Positionen. Übernommen: der lokale Spiegel-Gedanke (`provider_payloads`) und die ID-Stabilität; nicht übernommen: deren Automatik bei Strukturänderungen (Arr-Systeme benennen dann Dateien um — MediaForge fasst Dateien nie an, [ADR-0005](../adr/0005-immutable-originals.md), und erzeugt stattdessen Reviews). **Kodi** (NFO-Sidecar-Ökosystem) begründet den Import-Pfad: vorhandene NFOs werden als Ingest-Quelle gelesen (einmalig, Quelle `import`), nie geschrieben (Export wäre ein Artefakt-Thema). **Plex** demonstriert das Gegenmodell — zentraler proprietärer Metadaten-Dienst — und damit genau die Abhängigkeit, die ein Self-Hosted-System nicht haben darf: Jeder Provider ist optional, der Katalog funktioniert offline (dann eben ohne Anreicherung).

## Architekturentscheidung

Enrichment ist ein **feld-governter Zusammenführungs-Prozess über einem lokalen Provider-Spiegel**:

1. **Provider-Spiegel** (`provider_payloads`): Jeder Lookup persistiert die Rohantwort versioniert (Werkzeug-JSONB, Regel 8). Alle Feld-Entscheidungen arbeiten auf dem Spiegel, nie live gegen die API — Wiederholbarkeit, Offline-Erklärbarkeit („warum steht da Titel X? → Payload vom 12.05."), Rate-Schonung.
2. **Feld-Mapper je Provider** (deklarative Mapping-Tabellen, [Provider-Referenz](enrichment/provider-reference.md)): Payload → normalisierte Feldkandidaten `(feld, wert, provider, qualität)`.
3. **Merge-Engine** (pure Funktion): Feldkandidaten aller Provider + aktueller Katalogstand + Locks + Feldklassen-Regeln → Feldentscheidungen mit Provenienz. Keine I/O, vollständig fixture-testbar (dasselbe Muster wie Disc-Klassifikator und Track-Sequencer).
4. **Apply-Action** (`ApplyEnrichment`): wendet Entscheidungen atomar an, schreibt Provenienz, erzeugt bei Konflikt-Regeln Review-Tasks, auditiert vollständig (wer: System-Actor mit Run-Kontext).
5. **Asset-Pipeline**: Cover/Backdrops/Logos als heruntergeladene **Artefakte** mit Kandidaten-Verwaltung (mehrere Quellen, eine aktive Wahl pro Slot) — Bilder folgen demselben Kandidaten/Aktiv-Muster wie Chapter Sets im Assembler.

Die **Feldklassen** sind die zentrale Governance-Festlegung:

| Klasse | Beispiele | Regel |
|---|---|---|
| `descriptive` | Titel, Originaltitel, Beschreibung, Genres, Tags, Zertifizierung | Provider-Präzedenz; Lock schützt; Drift wird still übernommen, wenn ungelockt |
| `structural` | Episodenzuordnung (season/episode/sort_index), Serienstruktur, Editionszuordnung | **nie still**: Drift erzeugt immer Review `metadata_conflict` — an Struktur hängen Mappings und Watch-States |
| `factual` | Laufzeit, Erscheinungsdatum, ISBN/ASIN-Familien | Provider-Präzedenz, still, aber mit Plausibilitätsfenster (Laufzeitänderung > 10 % ⇒ Review) |
| `derived` | Sortiertitel, normalisierte Namen | nie von Providern; Ableitungsregeln des Fundaments |
| `local` | Bewertungen der Benutzer, Notizen, Bibliothekszuordnung | Enrichment tabu |

**KI-Grenze** (Architekturregel 5): KI-generierte Zusammenfassungen/Übersetzungen sind als Feldquelle zulässig, aber nur in explizit dafür freigegebene Felder (`summary_ai`), nie in Provider-Felder — der Katalog unterscheidet strukturell zwischen „TMDB sagt" und „Modell formulierte". Kein KI-Wert erreicht je ein `descriptive`-Kernfeld.

## Alternativen

**Live-Lookups ohne Spiegel**: verworfen — Rate-Limits, Nicht-Reproduzierbarkeit, Offline-Bruch. **Feld-Provenienz als eigene Tabelle je Feldwert** (voll versionierte Feld-Historie): verworfen als Standardweg — das Audit-System hält die Änderungshistorie bereits action-genau; eine zweite Historie wäre Redundanz. Die Provenienz des *aktuellen* Werts (`field_provenance`-JSONB am Item, Regel-8-konform: reine Anzeige/Diagnose) genügt. **Ein Meta-Provider-Aggregat** (erst alle Quellen zu einem „besten Datensatz" fusionieren, dann anwenden): verworfen — verschleiert Herkunft; die Merge-Engine entscheidet pro Feld sichtbar. **NFO-Schreiben** (Kodi-kompatible Sidecars pflegen): abgelehnt als Kernfunktion (Schreiben neben Originale verletzt die Trennung Bibliothek/Artefakt); als Export-Artefakt später denkbar (offener Punkt).

## Datenmodell und SQL-Schema

```sql
CREATE TABLE provider_payloads (
    id            CHAR(26) PRIMARY KEY,
    provider      TEXT        NOT NULL,             -- Namensraum wie provider_ids
    external_id   TEXT        NOT NULL,
    endpoint      TEXT        NOT NULL,             -- 'movie','tv_season','book_chapters',…
    payload       JSONB       NOT NULL,             -- Rohantwort (Werkzeug-JSONB)
    payload_hash  TEXT        NOT NULL,             -- BLAKE3, Drift-Erkennung
    etag          TEXT,                             -- HTTP-Cache-Validatoren, falls geliefert
    fetched_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (provider, external_id, endpoint)
);

CREATE TABLE enrichment_runs (
    id            CHAR(26) PRIMARY KEY,
    entity_type   TEXT        NOT NULL,
    entity_id     CHAR(26)    NOT NULL,
    trigger       TEXT        NOT NULL
        CHECK (trigger IN ('post_match','manual','scheduled_refresh','drift_check',
                           'provider_added')),
    status        TEXT        NOT NULL DEFAULT 'running'
        CHECK (status IN ('running','applied','no_change','conflict_review','failed')),
    decisions     JSONB       NOT NULL DEFAULT '{}',  -- Merge-Engine-Output (Diagnose)
    error_detail  TEXT,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    finished_at   TIMESTAMPTZ
);
CREATE INDEX enrichment_runs_entity_idx ON enrichment_runs (entity_type, entity_id, created_at DESC);

CREATE TABLE asset_candidates (
    id            CHAR(26) PRIMARY KEY,
    entity_type   TEXT        NOT NULL,
    entity_id     CHAR(26)    NOT NULL,
    slot          TEXT        NOT NULL
        CHECK (slot IN ('poster','backdrop','logo','banner','disc_art','person_photo')),
    provider      TEXT        NOT NULL,             -- inkl. 'local' (Datei-eingebettet/Ordnerbild) und 'manual'
    source_url    TEXT,                             -- Original-URL (nur Referenz; Download s. Asset-Pipeline)
    artifact_id   CHAR(26)    REFERENCES artifacts(id) ON DELETE CASCADE,
    width         INTEGER, height INTEGER, lang TEXT,
    vote_metric   NUMERIC,                          -- Provider-Bewertung, roh
    is_active     BOOLEAN     NOT NULL DEFAULT false,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX asset_one_active_per_slot
    ON asset_candidates (entity_type, entity_id, slot) WHERE is_active;
```

Feld-Provenienz lebt als `field_provenance`-JSONB auf `media_items`/`media_editions`/`people` (Kernschema-Erweiterung dieser Spezifikation): `{"title": {"source": "tmdb_movie", "run": "01J…", "at": "…"}, …}` — Anzeige und Diagnose, nie Abfragegrundlage (Regel 8; abfragbar sind die Felder selbst plus Locks).

Invarianten: (1) `provider_payloads` ist append-frei pro Schlüssel (Upsert; alte Payloads verschwinden — die Historie liegt im Audit der Apply-Actions, nicht im Spiegel). (2) Höchstens ein aktives Asset je Slot (partieller Unique-Index). (3) Kein Enrichment-Pfad schreibt gelockte Felder (Merge-Engine filtert, `ApplyEnrichment` validiert redundant — Defense in Depth gegen Engine-Bugs). (4) `structural`-Änderungen erreichen den Katalog nur über Review-Auflösung, nie direkt aus einem Run.

## Abläufe

**Post-Match** (`trigger='post_match'`): Nach Matcher-Bestätigung (Provider-ID neu) läuft der volle Zyklus: Lookup aller registrierten Endpunkte des Providers → Spiegel → Mapper → Merge (gegen leere/spärliche Felder gewinnt fast alles) → Apply → Assets nachladen → `ImportProviderRelationsJob` (Knowledge Graph) und Folge-Events.

**Scheduled Refresh**: Scheduler-Job wählt Kandidaten nach Alter und Priorität (Aktivität: kürzlich gesehene/bearbeitete Items zuerst; Serien mit `status='continuing'` häufiger als beendete — Frequenzen als Settings, Default 7/30/90 Tage für laufende Serien/aktive Items/Bestand). Delta-Verfahren, wo der Provider es bietet (TMDB-Changes-API, [Provider-Referenz](enrichment/provider-reference.md)); sonst ETag-bedingte Fetches. Payload-Hash unverändert ⇒ `no_change`, kein Merge-Lauf.

**Drift-Behandlung**: Hash verändert ⇒ Merge-Engine läuft; Entscheidungen nach Feldklasse (Tabelle oben). `structural`-Drift (Episodenliste anders) produziert den Review mit präziser Differenz („E07–E12 um eins verschoben; 3 bestätigte Disc-Mappings betroffen") — die Betroffenheits-Analyse fragt die abhängigen Module über deren Verträge (Disc-Mappings via `disc_episode_mappings`-Zählung, Watch-States via Fundament). Auflösungsoptionen der Review-UI: „Provider-Struktur übernehmen und Bezüge migrieren" (die Migration nutzt Provider-Episoden-IDs als Anker, nicht Positionen — deshalb übersteht ein Reorder die Übernahme verlustfrei, sofern der Provider stabile Episoden-IDs führt), „lokalen Stand behalten (Lock)", „einzeln entscheiden".

**Provider hinzugefügt** (`provider_added`): Neues Mapping (z. B. ASIN ergänzt zu bestehendem Buch) triggert nur die Endpunkte des neuen Providers; die Merge-Engine läuft über den Gesamtbestand der Kandidaten (Reihenfolgen-Regeln entscheiden, ob neue Quelle bestehende Felder verdrängt).

## Merge-Engine (normativer Kern)

Input: Feldkandidaten `(feld, wert, provider, qualität)` aller gemappten Provider (aus den Spiegeln), Katalogstand, `metadata_locked_fields`, Provider-Reihenfolge der Bibliothek (Setting `enrichment.provider_order` je Medientyp; Default in der Provider-Referenz). Entscheidung pro Feld:

1. Feld gelockt ⇒ `kept_locked` (Kandidaten werden verworfen, aber im Run-Decision-Log als „hätte geändert" sichtbar — Transparenz ohne Wirkung).
2. Feldklasse `local`/`derived` ⇒ nicht Enrichment-Territorium.
3. Kandidaten nach Provider-Reihenfolge; erster nicht-leerer Wert gewinnt (`first_wins`), **außer** Feld-Sonderregeln: Genres/Tags werden vereinigt statt ersetzt (mengenwertig, mit Herkunfts-Tracking je Element); Beschreibungen bevorzugen die konfigurierte Sprache über die Reihenfolge (`lang_priority` schlägt `provider_order` bei sprachbehafteten Feldern); numerische `factual`-Felder mit Plausibilitätsfenster (oben).
4. Gewinner ≠ Katalogstand ⇒ Änderungs-Entscheidung mit Klassen-Verhalten (still/Review).
5. Alle Entscheidungen inkl. Begründung in `decisions` (Run) und Provenienz-Update.

Die Engine ist deterministisch und ordnungs-stabil; Eigenschaften (testverankert): gleiche Inputs ⇒ gleiche Entscheidungen; Locks sind unverletzlich (Property-Test: kein Input-Vektor erzeugt Schreibentscheidung auf gelocktes Feld); Vereinigungsfelder sind idempotent (zweiter Lauf ⇒ `no_change`).

## Asset-Pipeline

Kandidaten aus Providern (URLs + Metadaten) und lokalen Quellen (eingebettete Cover, Ordnerbilder — als `provider='local'`). Download als `DownloadAssetJob` (Queue `connector`, Rate-Limiter des Ziels): Größenlimit (20 MB), Content-Type-Prüfung, Bildvalidierung (dekodierbar, Mindestmaße je Slot), Speicherung als Artefakt unter `/artifacts/assets/…` mit Content-Hash-Dedup (dasselbe Bild von zwei Providern ⇒ ein Blob, zwei Kandidaten). **Aktiv-Wahl**: automatisch beim ersten Kandidaten eines Slots nach Rangfolge (Sprache passend > Auflösung > `vote_metric`); manuelle Wahl (`SelectAsset`-Action) lockt den Slot (`asset:poster` in den Lock-Feldern) gegen automatische Umwahl. Kein Hotlinking: Das UI liefert ausschließlich lokale Artefakt-URLs aus — Provider-Bild-CDNs sehen keine Endnutzer-Requests (Datenschutz + Offline-Fähigkeit).

## Laravel-Klassen

Namespace `App\Modules\Enrichment`:

| Klasse | Typ | Vertrag |
|---|---|---|
| `ProviderPayload`, `EnrichmentRun`, `AssetCandidate` | Model | Runs/Assets guarded außerhalb der Actions |
| `ProviderClientInterface` | Interface | `fetch(endpoint, externalId): ProviderPayloadData` — je Provider implementiert; Rate-Limiter + ETag im Basis-Client |
| `FieldMapperInterface` | Interface | `map(ProviderPayloadData): list<FieldCandidate>` — deklarative Mapping-Tabellen, Provider-Referenz |
| `MergeEngine` | Service (pure) | `merge(candidates, current, locks, order): MergeDecisions` |
| `EnrichEntityJob` | ResumableJob (`connector`) | Schritte `fetch`, `merge`, `apply`, `assets`, `relations` |
| `ScheduledRefreshJob` | Job (Scheduler) | Kandidatenwahl + Dispatch |
| `ApplyEnrichment` | Action | atomare Anwendung + Provenienz + Reviews; Audit (System-Actor, Run-Kontext) |
| `LockMetadataField`, `UnlockMetadataField` | Action | Feld-Locks explizit (zusätzlich zum impliziten Lock bei manueller Feldänderung, Kernschema); Audit |
| `SelectAsset` | Action | Slot-Wahl + Slot-Lock; Audit |
| `RequestAiSummary` | Action | KI-Feld-Befüllung nur in `summary_ai`; kennzeichnungspflichtig (Regel 5); Audit |
| `EnrichmentApplied`, `MetadataDriftDetected` | Event | Fundament-Konvention |

## API-Endpunkte

| Route | Zweck | Rolle |
|---|---|---|
| `GET /api/v1/items/{ulid}/enrichment` | Provenienz, Locks, letzte Runs, Kandidatenlage | member |
| `POST /api/v1/items/{ulid}/enrichment/refresh` | Refresh anstoßen (202) | manager |
| `POST /api/v1/items/{ulid}/fields/{field}/lock` · `…/unlock` | Feld-Governance | manager |
| `GET /api/v1/items/{ulid}/assets?slot=` | Kandidaten je Slot | member |
| `POST /api/v1/assets/{ulid}/select` | Aktiv-Wahl | manager |
| `GET /api/v1/enrichment/providers` | konfigurierte Provider, Reihenfolgen, Limits, Egress-Status | admin |

## UI

**Item-Detail, Metadaten-Panel**: Jedes Feld zeigt auf Hover/Fokus seine Provenienz (Quelle, Zeitpunkt) und den Lock-Zustand (Schloss-Toggle; manuelles Editieren lockt implizit mit Hinweis-Toast — Kernschema-Verhalten sichtbar gemacht). „Aktualisieren"-Aktion mit Ergebnis-Zusammenfassung („3 Felder geändert, 1 Review erzeugt"). **Asset-Galerie** je Slot: Kandidaten-Raster mit Herkunft/Auflösung/Sprache, Aktiv-Markierung, manuelle Wahl. **Drift-Review** (Review-Inbox-Typ `metadata_conflict`): Differenz-Ansicht alt/neu je Feld, bei `structural` die Betroffenheits-Liste (Mappings, Watch-States) und die drei Auflösungswege. **Admin: Provider-Seite**: Reihenfolge je Medientyp (Drag), API-Schlüssel-Status (gesetzt/fehlt, nie Klartext), Rate-Limit-Auslastung, Egress-Schalter mit Klartext-Erklärung, was gesendet wird (Provider-Referenz, Datenabfluss-Tabellen).

## Edge Cases

* **Provider-ID entfernt/invalidiert** (404 vom Provider auf bekannte ID): Payload bleibt (letzter guter Stand), `provider_ids.last_seen_at` friert ein, nach 3 Fehlläufen Review „Provider-Mapping prüfen" — nie automatisches Unmapping (die ID war einmal menschlich bestätigt worden, vielleicht).
* **Widersprüchliche Provider bei Vereinigungsfeldern** (TMDB-Genre „Sci-Fi", TVDB „Science-Fiction"): Genre-Normalisierungstabelle (Provider-Referenz) faltet Synonyme vor der Vereinigung; Unbekanntes bleibt getrennt (sichtbar im Datenqualitätsmodul als Normalisierungs-Kandidat).
* **Sprachwechsel der Instanz** (`lang_priority` geändert): kein automatischer Massen-Refresh (Kosten!); die Änderung wirkt bei ohnehin fälligen Refreshes; ein manueller Massen-Lauf ist als Rule-Engine-/CLI-Aktion möglich (`mediaforge:enrichment-refresh --lang-changed`).
* **Item ohne jeden Provider** (privates Video): Enrichment ist vollständig inaktiv; alle Felder manuell/Import — der Katalog funktioniert, nichts nörgelt.
* **Zirkulärer Ingest** (Jellyfin-Connector liefert Metadaten, die ursprünglich aus MediaForge-Export stammen): Connector-Ingest-Felder haben die niedrigste Präzedenz und überschreiben nie Provider-/manuelle Werte; die Governance-Tabelle der Connector-SDK verweist hierher.
* **Payload-Schema-Bruch des Providers** (API-v4-Umstellung): Mapper sind versioniert; unbekannte Payload-Strukturen ⇒ Run `failed` mit `mapper_schema_mismatch`, Health-Alarm — nie Halb-Mappings aus geratenen Pfaden.

## Performance

Mengengerüst: 50k Items × 2–3 Provider-Endpunkte ⇒ ~150k Spiegel-Zeilen, ⌀ 20 KB Payload ⇒ ~3 GB TOAST-komprimiert — akzeptiert (der Spiegel ist der Preis der Reproduzierbarkeit; Housekeeping kann Payloads item-los gewordener IDs räumen). Refresh-Durchsatz wird vom Rate-Limiter bestimmt, nie von MediaForge-Kapazität (TMDB ~40 req/10 s ⇒ Bestands-Refresh von 10k Items dauert Stunden und darf das — Queue `connector`, niedrige Priorität, Delta-Verfahren drücken die Realität weit darunter). Merge-Läufe sind Mikrosekunden (pure Funktion über Dutzende Felder).

## Security

API-Schlüssel der Provider liegen in der Settings-Verschlüsselung (nie in Payloads, nie in Logs; die Client-Schicht redigiert sie aus Fehlermeldungen). **Egress-Minimierung**: Lookups senden ausschließlich externe IDs und Endpunkt-Parameter — nie Dateinamen, Pfade, Bibliotheksstruktur oder Watch-Daten; der globale Egress-Schalter (`enrichment.egress_enabled`, Default true) und Provider-Einzelschalter machen den Datenabfluss zur bewussten Entscheidung; die [Provider-Referenz](enrichment/provider-reference.md) dokumentiert je Provider tabellarisch, was gesendet wird. Payload-Verarbeitung behandelt Provider-Antworten als feindlich (Größenlimits 5 MB, Tiefenlimits beim Parsen, keine URL-Auflösung aus Payloads außer über die Asset-Pipeline mit deren Prüfungen). Asset-Downloads validieren Content-Types und Bildinhalte (ein „Poster" mit HTML-Inhalt wird verworfen); SVG ist als Asset-Format ausgeschlossen (Skript-Träger).

## Tests

Merge-Engine-Suite (pure): Feldklassen-Matrix (jede Klasse × jeder Änderungstyp ⇒ erwartetes Verhalten), Lock-Unverletzlichkeit (Property), Vereinigungs-Idempotenz, Sprachprioritäts-Vorrang, Reihenfolgen-Semantik. Mapper-Golden-Tests je Provider gegen Fixture-Payloads (echte, anonymisierte API-Antworten; Schema-Bruch-Fixtures für den `mapper_schema_mismatch`-Pfad). Drift-Szenarien (Integration): descriptive still, factual mit Fenster, structural ⇒ Review mit korrekter Betroffenheits-Zählung; Reorder-Migration über Episoden-IDs verlustfrei (das Disc-Mapping-Szenario als Regressionsanker). Asset-Pipeline: Dedup über Content-Hash, Slot-Locks, Validierungs-Ablehnungen. Egress-Contract-Test: HTTP-Fake protokolliert jede ausgehende URL; die Suite beweist, dass nur dokumentierte Parameter das Haus verlassen (der Test zur Datenabfluss-Tabelle).

## ADR-Verweise

[ADR-0003](../adr/0003-provider-id-mapping.md) (Provider-IDs als Mapping, nie Identität — die Grundlage jedes Lookups), [ADR-0005](../adr/0005-immutable-originals.md) (keine NFO-/Datei-Schreibungen), [ADR-0006](../adr/0006-action-level-audit.md) (Apply als auditierte Action). Setzt Architekturregeln 5, 7, 8 um; die Feldklassen-Governance ist die hier normierte Erweiterung.

## Offene Punkte

* **Mehrsprachige Metadaten** (`media_item_translations`, Kernschema-Vermerk): Bedarf bestätigt sich mit realem Betrieb; die Merge-Engine ist über `lang_priority` vorbereitet.
* **Egress von Korrekturen** (MediaForge-Wissen zurück zu Gegenstellen; Connector-SDK-Vermerk): Feld-Governance liefert jetzt die Basis (nur `manual`-Provenienz-Felder wären Egress-Kandidaten); die Capability bleibt bis zur Einzelspezifikation deaktiviert.
* **NFO-Export als Artefakt**: nachgelagert, unspezifiziert.
* **Community-Provider** (Fanart.tv u. a.) und **deutsche Verlags-Hörbuchquellen** (Assembler-Vermerk): Kandidatenliste in der Provider-Referenz, Aufnahme nach Lizenzprüfung.
