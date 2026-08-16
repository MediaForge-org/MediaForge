# P0032 — Target monorepo root structure and migration scaffolding: observability

**Track:** 02-monorepo-root  
**Priority:** P0  
**Prompt position in track:** 12/20  
**Depends on:** P0031

## Objective

Add logs, metrics, audit events and health visibility for the subsystem.

This is a deliberately narrow step inside **Target monorepo root structure and migration scaffolding**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/managed-upstreams-and-product-surface.md`
- `docs/MediaForge/adr/0025-managed-upstream-backends.md`
- `docs/MediaForge/architecture/target-monorepo.md`
- `docs/MediaForge/adr/0014-target-polyglot-monorepo.md`
- `docs/MediaForge/architecture/unified-application.md`
- `docs/MediaForge/architecture/artifact-store-and-derived-assets.md`
- `docs/MediaForge/modules/plugin-theme-sdk.md`

Inspect these source paths/symbol neighborhoods first:
- `repository root`
- `composer.json`
- `package.json`
- `docker-compose.yml`
- `packages/plugin-sdk`
- `packages/theme-sdk`
- `packages/contracts/domains`
- `services/ai/reconstruction`
- `platform/storage`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/68_backend_capabilities_acquisition_overview.png`
- None for this prompt unless a changed screen directly requires an existing design-system reference.

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Preserve Git history and working behavior while introducing the target top-level layout. Avoid a big-bang move that makes the application unbuildable for multiple prompts.


## Mandatory target additions — 2026-08-17

- Import/pin buildable Jellyfin, Stash and Audiobookshelf upstream baselines early in the monorepo; record exact release/tag, commit SHA and licence provenance at the time of import.
- Prepare `platform/managed-upstreams/` and `tools/upstream-sync/` seams without prematurely performing the later engine cutover.
- Keep normal product UX owned by MediaForge; source import is not permission to expose upstream UIs as the product.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Target monorepo root structure and migration scaffolding** and the current focus **observability**.
2. Keep these subsystem deliverables in view: root workspace layout, path compatibility strategy, developer commands, temporary compatibility shims.
3. Add logs, metrics, audit events and health visibility for the subsystem.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- root workspace layout
- path compatibility strategy
- developer commands
- temporary compatibility shims

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] Important state transitions are logged/audited without secrets.
- [ ] Health distinguishes degraded vs unavailable.
- [ ] Metrics/log labels avoid unbounded cardinality.
- [ ] Existing relevant behavior outside this prompt remains working.
- [ ] New code follows the target responsibility boundaries rather than adding another temporary permanent architecture.
- [ ] No secrets/private user data are added to the repository.

## Testing / validation

Use the smallest relevant commands for the files changed. At minimum:
1. run focused unit/integration tests for the modified subsystem;
2. run static/type/format checks appropriate to the changed language(s);
3. run a broader build/test gate if the change affects startup, routing, contracts, database migrations or shared packages;
4. if UI behavior changed, validate loading, empty, error and responsive states and run the applicable browser/E2E check;
5. if a migration changed, prove both fresh setup and upgrade-path behavior where practical.

Do not claim a test passed unless you actually ran it. Record exact commands and results.

## Completion response

End your response with:
- **Changed files**
- **Data/schema changes**
- **Contracts/API changes**
- **Tests run + results**
- **Behavior guaranteed after P0032**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.