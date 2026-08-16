# P0704 — Docker images, CI, releases, security QA and final integration: persistence

**Track:** 36-release-qa  
**Priority:** P1  
**Prompt position in track:** 4/20  
**Depends on:** P0703

## Objective

Implement or prepare persistence/schema changes needed by the subsystem.

This is a deliberately narrow step inside **Docker images, CI, releases, security QA and final integration**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/managed-upstreams-and-product-surface.md`
- `docs/MediaForge/modules/acquisition-automation-and-postprocessing.md`
- `docs/MediaForge/architecture/localization-and-professional-translation.md`
- `docs/MediaForge/adr/0025-managed-upstream-backends.md`
- `docs/MediaForge/adr/0026-localization-and-translation.md`
- `docs/MediaForge/adr/0027-acquisition-blueprint-processing-dag.md`
- `docs/MediaForge/architecture/docker-release-distribution.md`
- `docs/MediaForge/DEVELOPMENT_PHASES_DETAILED.md`
- `docs/MediaForge/CLAUDE_IMPLEMENTATION_DIRECTIVE.md`
- `docs/MediaForge/architecture/ai-capabilities-model-registry.md`
- `docs/MediaForge/architecture/green-commit-workflow.md`

Inspect these source paths/symbol neighborhoods first:
- `platform/docker`
- `platform/compose`
- `platform/releases`
- `.github`
- `tests`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/68_backend_capabilities_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/69_localization_translation_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/58_docker_deployment.png`
- `docs/MediaForge/ui-ux/reference-expanded/60_implementation_roadmap_36_tracks.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

**2026-08-16 architecture rule:** AI images/models are optional release profiles/downloads; Core images must stay usable without heavy model weights. CI remains green before advancing.


Official images/releases require reproducibility, SBOM/provenance/signing policy, multi-arch strategy, migration gates and documented rollback paths.


## Mandatory target additions — 2026-08-17

- Release gates include managed-upstream startup/API/compatibility/rollback tests and full acquisition-to-import/post-processing E2E coverage.
- First-class locales de/en-GB/it/es/fr require complete message-key coverage and representative layout/plural/date/number quality checks.
- UI reference artwork is illustrative; production must never ship a mismatched poster/cover for a canonical media item.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Docker images, CI, releases, security QA and final integration** and the current focus **persistence**.
2. Keep these subsystem deliverables in view: container build, CI matrix, security/supply-chain checks, release/rollback workflow.
3. Implement or prepare persistence/schema changes needed by the subsystem.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.
6. For any schema/layout migration, make the operation restart-safe and prove that existing data/files are not silently lost.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- container build
- CI matrix
- security/supply-chain checks
- release/rollback workflow

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] Migrations are reversible or have a documented safe rollback.
- [ ] Indexes/constraints enforce important invariants.
- [ ] Existing data remains readable or has an explicit migration path.
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
- **Behavior guaranteed after P0704**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.