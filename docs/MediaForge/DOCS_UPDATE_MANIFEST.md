# Documentation Overlay Manifest

Dieses Paket ist zum Kopieren über das bestehende `MediaForge`-Repository gedacht.

## Ersetzt/aktualisiert

- `README.md`
- `docs/MediaForge/MediaForge_Master_Engineering.md`
- `docs/MediaForge/roadmap.md`
- `docs/MediaForge/modules/adult-enhancement.md`
- `docs/MediaForge/modules/disc-engine.md`
- `docs/MediaForge/modules/audio-upscaler.md`
- `docs/MediaForge/ui-ux/design-system.md`
- `docs/MediaForge/ui-ux/adult-ui-enhancement.md`

## Neu

- `docs/MediaForge/PRODUCT_DECISIONS_2026-08.md`
- `docs/MediaForge/CLAUDE_IMPLEMENTATION_DIRECTIVE.md`
- `docs/MediaForge/architecture/unified-application.md`
- `docs/MediaForge/architecture/engine-contracts.md`
- `docs/MediaForge/architecture/postgresql-source-of-truth.md`
- `docs/MediaForge/modules/adult-engine-target.md`
- `docs/MediaForge/modules/disc-verification-policy.md`
- `docs/MediaForge/ui-ux/unified-interface.md`
- `docs/MediaForge/ui-ux/reference/*`

## Absichtlich NICHT enthalten/überschrieben

- `docs/MediaForge/CURRENT_PHASE.md`
- `docs/MediaForge/V1_READINESS.md`
- Source Code
- `.env`
- Secrets

`CURRENT_PHASE.md` bleibt die Wahrheit über den tatsächlichen Implementierungsstand.

## Detailed expansion – added

### Architecture
- `architecture/target-monorepo.md`
- `architecture/polyglot-runtime-and-contracts.md`
- `architecture/routing-and-public-urls.md`
- `architecture/docker-release-distribution.md`

### Modules
- `modules/acquisition-center.md`
- `modules/adult-analysis-and-taxonomy.md`
- `modules/adult-lineage-and-catalog.md`
- `modules/media-editions-and-lineage.md`
- `modules/series-advanced-model.md`
- `modules/movies-advanced-model.md`
- `modules/audiobook-chapters-and-storage.md`
- `modules/work-graph-and-cross-media.md`

### UI / Planning
- `ui-ux/FEATURE_SCREEN_SPECIFICATIONS.md`
- `DEVELOPMENT_PHASES_DETAILED.md`
- reference images `30`–`41`

### ADRs
- `adr/0014-target-polyglot-monorepo.md`
- `adr/0015-human-readable-routing.md`
- `adr/0016-event-taxonomy-and-analysis.md`
- `adr/0017-acquisition-and-staging.md`
- `adr/0018-audiobook-chapter-storage.md`

## Granular Claude prompt system

This package also adds `docs/MediaForge/prompts/` with 720 detailed, numbered prompts (36 tracks × 20 prompts), plus `PASTE_TO_CLAUDE_PROMPT_SYSTEM.md` and `tools/prompts/show_prompt.py`. The prompt system is an execution layer; the normal MediaForge docs remain the authoritative product specification.

## Update 2026-08-16 — AI/3D/Plugins/Frontend

Added/updated:
- React Router Framework Mode decision; no Next.js second full-stack layer;
- optional AI/3D capabilities and model registry;
- content-addressed artifact store + derived storage/GC;
- versioned anatomy and performer reconstruction revisions;
- detailed female-performer tattoo coverage over total body surface;
- plugin/theme SDK + Custom CSS;
- green-commit main workflow;
- UI references 42–67;
- affected existing Claude prompts updated while keeping exactly 720 IDs;
- dependency cycle P0441–P0580 removed by eliminating the backwards P0580 hard dependency from Tracks 23–25.
