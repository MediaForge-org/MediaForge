# Adult Source Vault and local provenance

**Status:** binding target architecture

## Scope

Adult scenes must remain catalogued even when TPDB, StashDB, a studio site, creator site, brand/channel or individual page disappears. MediaForge keeps canonical local scene identity plus historical source observations and local evidence.

External absence is not canonical deletion.

## Canonical scene metadata quality

A CanonicalScene may be:

```text
VERIFIED_EXTERNAL
HISTORICAL_EXTERNAL
LOCAL_CURATED
LOCAL_FILENAME
PARTIAL
UNIDENTIFIED
```

Missing TPDB/StashDB/official metadata never proves that the scene did not exist.

## Source Vault

For relevant source observations MediaForge may retain:

- provider/source identity;
- URL and historical URL aliases;
- external/source id;
- first_seen and last_seen;
- observation timestamp;
- title/description;
- studio/brand;
- performers;
- release date;
- tags/categories;
- cover/thumbnail references;
- structured source facts;
- availability state;
- metadata hash/version;
- references to optional small lawful/public snapshots or screenshots where policy permits.

Large media/captures do not belong in PostgreSQL.

## Availability history

Availability belongs to the source observation, not the canonical scene.

At minimum:

```text
ONLINE
TEMPORARILY_UNAVAILABLE
REMOVED
DELISTED
PRIVATE
LOGIN_REQUIRED
REGION_RESTRICTED
SOURCE_GONE
DOMAIN_GONE
UNKNOWN
```

REMOVED/SOURCE_GONE/DOMAIN_GONE never auto-delete the canonical scene or retained facts.

## Historical recovery

Provider plugins may search configured historical/public metadata archives and other metadata sources. Provenance and observation time must be retained.

Capability classes may include live official source, Stash-box provider, TPDB-like provider, public archive/history provider, browser-companion capture, local filename source and local-file evidence.

MediaForge must not bypass authentication, paywalls, DRM or access controls to obtain historical content.

## Local filename as first-class provenance

Default Adult filename profile:

```text
{studio} - {date} - {performers} - {title}
```

Example:

```text
Studio - 2024-06-18 - Performer A, Performer B - Scene Title.mp4
```

The parser extracts studio/brand, release date, performer names and title. Performer strings are treated as names; gender is not inferred from the filename.

Date values are normalised internally. Original filename plus parser profile/version are retained.

If deterministic parsing succeeds but external verification is unavailable, parsed metadata may drive the canonical display with quality `LOCAL_FILENAME`. After explicit confirmation/editing it may become `LOCAL_CURATED`.

Later external sources add facts and may propose changes, but do not silently overwrite locked/curated local values.

## Performer/studio resolution

Parsed names resolve against canonical entities and aliases/historical names. Strong unambiguous matches link to existing records. Ambiguous cases go to Review rather than creating duplicates or guessing.

## Bidirectional naming

```text
filename -> parsed metadata
metadata -> naming preview -> safe rename
```

MediaForge owns final naming policy. Rename/move retains canonical Scene/File identity, original-filename provenance and Source Vault history.

## Local-only scenes

If no usable external metadata exists, MediaForge can still create a canonical local scene from:

- local media file;
- filename/folder context;
- technical metadata;
- duration;
- exact hash;
- video/content fingerprint;
- frame/perceptual hashes;
- optional audio fingerprint;
- locally supplied title/studio/date/performers.

Missing fields remain unknown, not invented.

## Reupload/same-scene detection

Robust fingerprints may identify that a later reupload is probably the same scene despite changed URL, filename, title, container, encode or source site.

Evidence can include exact hash, video fingerprint, multiple frame pHashes, audio fingerprint, duration alignment, technical properties and source identifiers.

High-confidence matches may be suggested. Ambiguous identity always goes to Review and is never silently merged.

## PostgreSQL and artifacts

PostgreSQL owns canonical identities, source facts, mappings, source observations, URL/availability history, parser provenance, review decisions and artifact references.

Large media, captures, screenshots and analysis artifacts stay in the filesystem/artifact store.

## Backup/resilience

Backups preserve canonical scenes, local filename/curated facts, source facts, external mappings, URL and availability history, parser version, review decisions and evidence references.

Provider outages/deleted domains must not make previously catalogued scenes lose known metadata.

## Required verification

Later implementation must cover:

- filename-only local scene;
- multiple performers in filename;
- alias matching and ambiguity Review;
- metadata -> safe rename roundtrip;
- source removed after prior observation;
- entire domain/source disappearance;
- empty provider refresh after previous metadata;
- historical fact retention;
- local curated lock surviving refresh;
- exact-file reappearance;
- re-encode/reupload candidate via fingerprints;
- no auto-merge at insufficient confidence;
- Source Vault backup/restore.
