# ADR-0025 — MediaForge owns the frontend; specialised programs are backend capabilities

**Status:** Accepted
**Date:** 2026-08-17

## Decision

Jellyfin/Stash/Audiobookshelf source baselines are imported early and progressively transformed into internal MediaForge engines. SABnzbd, qBittorrent, Prowlarr, Sonarr, Radarr and Whisparr remain unmodified managed upstream services by default.

All normal workflows are presented in the MediaForge frontend/API. Native upstream UIs are optional administrative fallbacks only.

## Rationale

This preserves mature specialised backend functionality and upstream updateability while delivering one coherent product surface, identity model, search, queue, settings, notifications and provenance system.

## Consequences

- Track 02 prepares/imports pinned engine upstream baselines and managed-upstream manifests.
- Track 07 defines capability/managed-component contracts.
- Tracks 14/15 expose unified acquisition rather than a launcher.
- Tracks 26-28 complete engine cutovers; they do not first discover the upstream projects.
- Compatibility tests gate every managed-upstream update.
