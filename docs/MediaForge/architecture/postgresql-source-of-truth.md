# PostgreSQL Source of Truth

Status: **binding target architecture**
Updated: **2026-09-27**

## Decision

PostgreSQL is the canonical database for **MediaForge-owned state**.

That does not make it the canonical internal database for Jellyfin, Audiobookshelf, Scene Tracker or
other specialist services.

## MediaForge PostgreSQL owns

- MediaForge canonical IDs;
- Work/MediaItem/Edition/File relationships;
- external/provider mappings;
- synchronized external snapshots/projections;
- canonical metadata chosen by MediaForge;
- source facts/provenance/history;
- manual locks/review decisions;
- collections/preferences/search;
- acquisition/import lineage;
- adapter sync cursors/state;
- compatibility/health snapshots;
- MediaForge audit;
- MediaForge-specific reading/progress state where explicitly defined.

## External services retain their databases

Jellyfin:
- keeps its internal DB;
- owns playback/transcoding/session technical state.

Audiobookshelf:
- keeps its internal DB;
- owns specialist audiobook/podcast runtime state.

Scene Tracker:
- keeps its own PostgreSQL DB;
- owns its community/source platform data.

MediaForge accesses these systems through APIs only.

## Mirroring

MediaForge may retain normalized external facts so browsing does not depend on live upstream calls.

A mirror is not permission to create multi-master behavior.

Every mirrored field should have enough provenance to identify:

- source system/instance;
- external ID;
- observed/source timestamp where available;
- last seen/sync time;
- availability;
- normalization/canonical decision when relevant.

Source disappearance must not silently delete good canonical metadata.

## Identity

Upstream IDs are mappings, never MediaForge primary identity.

The current `media_external_mappings` and `provider_ids` foundations remain useful.

## Work / Edition / File

Preserve the deep model.

Current schema should be evolved, not replaced blindly.

Before adding new Work tables, define exact semantics and migration/backfill rules against the current
`media_items`, `media_editions`, `files` and `edition_files`.

## File identity

Long-term File identity should survive rename/move.

The current path-bearing `files` model is a useful foundation, but File/FileLocation separation may
be introduced when the move/rename workflow is implemented.

## Progress

Avoid ambiguous multi-master behavior.

- upstream service owns its raw runtime progress/session state;
- MediaForge may own a normalized user-facing cross-service projection;
- write-back requires explicit conflict strategy and idempotency.

## Large data

Never store as normal PostgreSQL blobs:

- movies;
- audio files;
- ebooks;
- transcodes;
- HLS segments;
- large images;
- model weights;
- large AI/derived artifacts.

Store metadata/references in PostgreSQL and bytes in filesystem/object/artifact/upstream storage.

## Search

PostgreSQL remains the initial search/graph base.
Introduce extra search/vector systems only for measured requirements.

## Backup

MediaForge backup covers MediaForge PostgreSQL plus MediaForge configuration/secrets/artifact metadata.

Upstream services keep their own backup strategies; an all-in-one deployment may coordinate backup
health without merging databases.
