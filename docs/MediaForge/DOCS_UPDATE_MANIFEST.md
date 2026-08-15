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
