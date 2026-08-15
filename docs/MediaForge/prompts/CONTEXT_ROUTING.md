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
- Governance/execution vocabulary and module boundaries → `01-governance-audit/GOVERNANCE_DOMAIN_MODEL.md` + `01-governance-audit/GOVERNANCE_BOUNDARIES.md`

Do not reread `MediaForge_Master_Engineering.md` unless the prompt lists it or a cross-domain ambiguity cannot be resolved by a smaller authority.
