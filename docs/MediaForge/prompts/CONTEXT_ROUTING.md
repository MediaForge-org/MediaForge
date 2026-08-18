# Context routing for Claude

Use this before reading extra docs.

## Always read
- the current numbered prompt
- `GLOBAL_RULES_SHORT.md`

## Then read only the current prompt's `Required reads`
Do not automatically follow every link inside those documents.

## UI prompts
Inspect only the exact PNG filenames listed by the prompt. If a referenced PNG is absent, report it; do not substitute random images.

## Code inspection
Inspect only source paths listed by the prompt plus directly imported/called dependencies necessary to understand the change. Use repository search for exact symbols rather than opening entire trees.

## When more context is genuinely needed
Choose the smallest matching authority:
- target filesystem layout → `architecture/target-monorepo.md`
- runtime/language boundaries → `architecture/polyglot-runtime-and-contracts.md`
- API/engine boundary → `architecture/engine-contracts.md`
- PostgreSQL ownership → `architecture/postgresql-source-of-truth.md`
- public URLs → `architecture/routing-and-public-urls.md`
- Adult catalog → `modules/adult-lineage-and-catalog.md`
- Adult events/tags/analysis → `modules/adult-analysis-and-taxonomy.md`
- Acquisition/downloads → `modules/acquisition-center.md`
- Series → `modules/series-advanced-model.md`
- Movies → `modules/movies-advanced-model.md`
- Audiobooks → `modules/audiobook-chapters-and-storage.md`
- Disc → `modules/disc-engine.md` + `modules/disc-verification-policy.md`
- Audio enhancement → `modules/audio-upscaler.md`
- UI visual system → `ui-ux/UI_IMPLEMENTATION_PROMPT.md` + relevant image(s)

- frontend framework choice → `architecture/frontend-framework.md`
- AI capabilities/model registry → `architecture/ai-capabilities-model-registry.md`
- derived/artifact storage → `architecture/artifact-store-and-derived-assets.md` + `modules/derived-assets-and-storage-manager.md`
- Adult 3D/tattoo coverage/anatomy → `modules/adult-3d-reconstruction-and-tattoo-coverage.md`
- plugins/themes/custom CSS → `modules/plugin-theme-sdk.md`
- Git/rollback workflow → `architecture/green-commit-workflow.md`

Do not reread `MediaForge_Master_Engineering.md` unless the prompt lists it or a cross-domain ambiguity cannot be resolved by a smaller authority.

If present in the live checkout, Track-01 generated governance artifacts (`GOVERNANCE_DOMAIN_MODEL.md`, `GOVERNANCE_BOUNDARIES.md`, `RISK_REGISTER.json`, `GOVERNANCE_API_CONTRACT.md`, `GOVERNANCE_FRONTEND_SCOPE.md`) are authoritative for the execution-audit subsystem and must not be overwritten by prepared docs.

## 2026-08-17 routing additions

Read only when the current prompt touches the matching concern:

- managed upstream lifecycle, early Jellyfin/Stash/ABS import, SAB/qBit/Prowlarr/*Arr product boundaries -> `docs/MediaForge/architecture/managed-upstreams-and-product-surface.md` + ADR-0025.
- UI localisation, canonical status localisation, metadata translation, glossary/translation memory/provider policy -> `docs/MediaForge/architecture/localization-and-professional-translation.md` + ADR-0026.
- source search, Wanted/release scoring, naming, torrent hardlinks/seeding, post-processing DAG, ISO remux/transcodes -> `docs/MediaForge/modules/acquisition-automation-and-postprocessing.md` + ADR-0027.
- player volume/gain (including 100/150/200%), loudness/LUFS, limiter, dialogue/DRC/EQ/downmix, device audio profiles and requested/effective audio state -> `docs/MediaForge/architecture/player-audio-loudness-and-device-policy.md`.

## 2026-08-18 routing additions — source preservation and books

- books/ebooks, path-independent literary identity, retained Audiobookshelf metadata and reading state -> `docs/MediaForge/modules/books-ebooks-and-persistent-metadata.md`.
- disappeared/delisted adult sources, Local Filename/Local Curated scenes, Source Vault, historical recovery and reupload identity -> `docs/MediaForge/modules/adult-source-vault-and-local-provenance.md`.
