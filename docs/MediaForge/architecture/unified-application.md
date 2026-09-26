# Unified Application Architecture

Status: **binding target architecture**
Updated: **2026-09-27**

## Product boundary

MediaForge is one visible product with one domain model and canonical catalog.

Specialist servers remain external implementation details behind MediaForge adapters.

```text
Browser / PWA / TV / desktop / mobile
                 |
                 v
             MediaForge
                 |
          MediaForge API
                 |
      +----------+-----------+
      |          |           |
      v          v           v
 PostgreSQL   Jellyfin   Audiobookshelf
 canonical     adapter       adapter
 catalog         |             |
                 v             v
              Jellyfin       ABS

Scene metadata:
MediaForge -> SceneTrackerAdapter -> Scene Tracker
```

## Visible UI

Normal navigation is MediaForge-owned:

- Home;
- Movies;
- TV Shows;
- Scenes;
- Performers;
- Studios;
- Music;
- Audiobooks;
- Books;
- Podcasts;
- Collections;
- Search;
- Acquisition;
- Settings.

Backend names appear only when relevant for integration settings, provenance or diagnostics.

## API/frontend

React Router is the target visible routing layer.
Inertia is transitional.

The frontend calls MediaForge API, never provider APIs directly.

## Catalog resilience

Normal browsing reads MediaForge PostgreSQL.

When a specialist service is offline, stored catalog/metadata remains available while live
capabilities such as playback are degraded.

## Playback

Jellyfin/ABS remain specialist playback runtimes.
MediaForge prepares/controls sessions through adapters without turning PHP into a media-byte proxy.

## Native upstream UIs

Optional admin/debug fallback only.
No iframe-as-product architecture.

## Monorepo

The MediaForge monorepo contains MediaForge-owned code and integration manifests/contracts.
It does not need copied upstream source trees merely to provide a unified product.
