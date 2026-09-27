# MediaForge Current Phase

## Active execution system

The active implementation plan is now `docs/MediaForge/phases/`.
The legacy `docs/MediaForge/prompts/P0001..P0720` tree remains only as historical requirements material.

## Current milestone

- Last completed: **M1 — Governance, architecture and execution foundation**
- Current: **M2 — Platform, monorepo, API and frontend transition**
- Next unit: **M2.1 — Target root layout and compatibility scaffolding**

Use:
```bash
python3 tools/phases/show_phase.py M2.1
python3 tools/phases/check_phase_plan.py
```

## Implemented baseline

Existing V1 A–H and V2 A–E remain real and must be preserved. The complete pre-migration `CURRENT_PHASE.md` is copied verbatim to `docs/MediaForge/phases/IMPLEMENTED_BASELINE.md` during migration.

Later milestones may already have partial foundations because V1/V2 implemented useful pieces ahead of the new order. Do not rebuild working code.

## Architecture summary

MediaForge is one product/UI; Laravel remains control plane/domain/BFF; React Router + API v1 is the web target; PostgreSQL owns MediaForge canonical state; Jellyfin/ABS/Scene Tracker remain external services behind adapters; no direct foreign DB access; no mandatory deep forks; Work/MediaItem != Edition != File/FileLocation; Adult Zero Leak mandatory; originals are not changed without explicit authorization.

Only advance `current_unit` after the prior unit is proven green. No commit/push/tag/release is implied by completion.
