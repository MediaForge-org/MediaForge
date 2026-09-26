# Architecture Supersession — 2026-09-27

Status: **binding execution overlay until affected P0001–P0720 files are systematically rewritten**

## Decision

The old mandatory source-fork plan for Jellyfin, Stash and Audiobookshelf is superseded.

Do not execute numbered-prompt instructions that require:

- importing Jellyfin/Stash/Audiobookshelf source baselines merely for future integration;
- turning them into mandatory internal `engines/video`, `engines/adult`, `engines/audio` forks;
- migrating their internal databases into MediaForge PostgreSQL;
- treating source-level engine cutover as the required final architecture.

Use instead:

- versioned public API adapters;
- compatibility/version/capability contracts;
- local PostgreSQL snapshots/mappings/provenance;
- optional separate bundled containers for Jellyfin/Audiobookshelf;
- Scene Tracker as a separate metadata/community product;
- no direct cross-database access.

## Track reinterpretation

- Track 02 -> adapter/compatibility/platform preparation, not copied upstream source import.
- Track 07 -> adapter/capability contracts.
- Tracks 26–28 -> rich external integration/playback/compatibility, not fork cutover.
- Adult tracks -> Scene Tracker metadata + Jellyfin playback + optional external adapters; no mandatory Stash fork.
- Track 29 -> Rust MediaTools remains valid.
- Track 36 -> external-service compatibility/release E2E remains valid.

## What remains authoritative

- current numbered prompt's lifecycle/quality/testing scope;
- dependency ordering unless the fork assumption is the dependency itself;
- security/privacy;
- PostgreSQL MediaForge identity;
- Work/Edition/File;
- provenance/review;
- acquisition;
- advanced Disc/AI roadmap.

If a numbered prompt conflicts with ADR-0028 or
`architecture/external-specialist-services-and-adapters.md`, stop and apply the adapter-first
interpretation rather than implementing the obsolete fork assumption.

Prompt IDs remain exactly P0001–P0720.
