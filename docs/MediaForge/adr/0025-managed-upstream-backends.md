# ADR-0025 — MediaForge owns the frontend; specialised programs are backend capabilities

**Status:** Accepted
**Date:** 2026-08-17

## Decision

Jellyfin/Stash/Audiobookshelf source baselines are imported early and progressively transformed into internal MediaForge engines. NZBGet, qBittorrent, Prowlarr, Sonarr, Radarr and Whisparr remain unmodified managed upstream services by default.

All normal workflows are presented in the MediaForge frontend/API. Native upstream UIs are optional administrative fallbacks only.

## Rationale

This preserves mature specialised backend functionality and upstream updateability while delivering one coherent product surface, identity model, search, queue, settings, notifications and provenance system.

## Consequences

- Track 02 prepares/imports pinned engine upstream baselines and managed-upstream manifests.
- Track 07 defines capability/managed-component contracts.
- Tracks 14/15 expose unified acquisition rather than a launcher.
- Tracks 26-28 complete engine cutovers; they do not first discover the upstream projects.
- Compatibility tests gate every managed-upstream update.

## Amendment — 2026-08-28

NZBGet replaces the previously planned Usenet downloader as the sole managed Usenet backend.

This does not make NZBGet's native web UI part of the normal product surface. MediaForge owns the complete normal queue/history/server/repair/unpack/download UX and consumes NZBGet as an upstream-managed capability through a normalized adapter/contract.

Maintaining a second Usenet production adapter is explicitly out of scope unless a later ADR demonstrates a hard missing capability or compatibility requirement.
