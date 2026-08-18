# Adult Scene Lineage, Studio-Historie und Catalog Completeness

Priorität: P0 Datenmodell / P1 UI+Sync

## 1. Scene Lineage

Eine Produktion kann mehrfach veröffentlicht werden, ohne eine neue kanonische Scene zu sein.

```text
Canonical Scene
├── Original Release
├── Re-release
├── Alternate Edit/Cut
├── Compilation Appearance
├── Distributor Listing
└── Local Editions/Encodes
```

MediaForge trennt daher:

- **Canonical Scene** – inhaltliche Produktion;
- **Release/Appearance** – Veröffentlichung derselben Produktion;
- **Edition/Cut** – inhaltlich relevante Variante;
- **MediaFile** – konkrete lokale Datei/Encode.

Das verhindert künstliche Dubletten.

## 2. Studio/Brand/Network-Historie

Studios und Brands ändern Namen, Besitzer, Domains oder Networks. Ein einzelnes aktuelles `studio_id` reicht nicht.

```text
organization_relationships
├── child_org_id
├── parent_org_id
├── relationship_type
├── valid_from
├── valid_to
├── source_fact_id
└── verification_state
```

Domain-Historie wird getrennt gespeichert.

## 3. Catalog Completeness

Performer-/Studio-Seiten zeigen:

```text
Known canonical scenes
Local in library
Missing locally
Historical only
Unresolved/conflicts
Coverage %
```

Breakdowns:

- nach Jahr;
- Studio/Brand;
- Source;
- local quality;
- unresolved identity;
- removed/historical pages.

Referenz: `36_performer_catalog_completeness.png`.

## 4. Historical Source Archive

Wenn eine Quelle verschwindet:

- Scene bleibt;
- Source Record bleibt;
- frühere URL bleibt;
- first_seen/last_seen_alive bleibt;
- erlaubte Metadaten-Snapshots bleiben;
- Status wechselt z. B. auf `source_dead`/`historical`.

Keine 404-Antwort darf kanonische Metadaten löschen.

## 5. Provenienz und Datum

Referenz: `35_adult_metadata_provenance_date_conflict.png`.

Datumsarten:

```text
production_date
release_date
studio_publish_date
first_seen_at
database_observed_date
local_filename_hint
```

Jeder Wert ist ein Fact mit Quelle. Eine Quelle kann später einen Wert ändern; alte Beobachtungen bleiben nachvollziehbar.

## 6. Authority

Studio-/Creator-Originalquelle ist für bestimmte Felder oft autoritativer als Aggregatoren, aber Authority ist feldbezogen.

Beispiel:

```text
release_date -> official studio high authority
duration     -> local ffprobe authoritative for local file
scene id     -> provider-specific, never canonical identity
```

## 7. Conflict Resolution

Konfliktzustände:

```text
agree
minor_difference
semantic_mismatch
source_conflict
unresolved
manual_override
```

Manuelle Overrides werden nicht vom nächsten Sync überschrieben.

## 2026-08-18 Source Vault and local-only scenes

Canonical scenes remain valid when TPDB/StashDB/official pages disappear. MediaForge retains historical source facts/availability observations and can use deterministic filename-derived or explicitly confirmed Local Curated metadata.

Default local naming profile is `{studio} - {date} - {performers} - {title}`; naming is bidirectional and preserves original-filename provenance.

See `docs/MediaForge/modules/adult-source-vault-and-local-provenance.md`.
