# Adult / Scene Domain Target

Status: **binding target architecture**
Updated: **2026-09-27**

This document supersedes the previous mandatory Stash-derived-fork target.

## Goal

MediaForge provides a rich private scene/performer/studio product surface while keeping playback
and external metadata ownership explicit.

## Preferred split

### Jellyfin

For local scene media where appropriate:

- local file/library presence;
- playback/streaming/transcoding;
- technical media information;
- playback session/progress.

### Scene Tracker

Separate external metadata/community product:

- scenes;
- performers;
- studios;
- tags/taxonomy;
- source provenance;
- community/matching/discovery data.

### MediaForge PostgreSQL

Owns:

- canonical MediaForge scene/performer/studio IDs;
- mappings to Jellyfin and Scene Tracker;
- local canonical choices;
- field provenance/manual locks;
- collections/search;
- review decisions;
- private-domain policy;
- derived analysis/evidence references;
- local scene lineage/edition relationships where MediaForge owns them.

## Stash

Stash is not a mandatory internal engine or fork.

A future Stash adapter may be added if it provides useful capabilities, but its database remains its
own implementation detail and it is not a prerequisite for the MediaForge scene product.

## Matching

Filename and filesystem metadata remain valuable evidence.

Example convention:

```text
Studio - YYYY-MM-DD - Performer(s) - Title
```

Combine:

- studio;
- date;
- performer names;
- title;
- runtime;
- technical metadata;
- external IDs.

Ambiguity goes to Review.

## Privacy

Preserve Adult Zero Leak.

When locked, no adult/private existence leaks through normal:

- routes;
- API;
- search;
- artwork/preload;
- notifications;
- logs/activity surfaces.

## Optional analysis

Advanced taxonomy, audio/video event timelines, evidence, AI and 3D remain optional MediaForge
capabilities behind the core product.

Do not make them hard dependencies for browsing/playback.
