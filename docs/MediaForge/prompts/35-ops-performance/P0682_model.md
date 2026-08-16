# P0682 — Observability, performance, backup, restore and resilience: model

**Track:** 35-ops-performance  
**Priority:** P1  
**Prompt position in track:** 2/20  
**Depends on:** P0681

## Objective

Define or refine the domain model and invariants for this subsystem.

This is a deliberately narrow step inside **Observability, performance, backup, restore and resilience**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

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
- `docs/MediaForge/architecture/postgresql-source-of-truth.md`
- `docs/MediaForge/DEVELOPMENT_PHASES_DETAILED.md`
- `docs/MediaForge/architecture/ai-capabilities-model-registry.md`
- `docs/MediaForge/architecture/artifact-store-and-derived-assets.md`
- `docs/MediaForge/modules/derived-assets-and-storage-manager.md`

Inspect these source paths/symbol neighborhoods first:
- `platform/observability`
- `platform/database`
- `apps/server`
- `services`
- `platform/storage`
- `services/ai/models`
- `services/ai/evaluation`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/68_backend_capabilities_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/69_localization_translation_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/17_analytics_and_reports.png`
- `docs/MediaForge/ui-ux/reference-expanded/58_docker_deployment.png`
- `docs/MediaForge/ui-ux/reference-expanded/66_3d_analysis_settings_overview.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

**2026-08-16 architecture rule:** Operate derived/model stores with quotas/GC and resource scheduling. Playback has priority over background AI.


Measure before optimizing. Backups must be restorable, not merely created. Observability must cover the full multi-process system.


## Mandatory target additions — 2026-08-17

- Managed-upstream updates are pinned, compatibility-tested and rollback-capable; do not auto-promote an unverified upstream release.
- Coordinate downloader bandwidth, torrent upload, playback, remux/transcode, AI and translation jobs through resource/storage budgets.
- Expose peak temporary storage forecasts and translation/provider cost/queue controls.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Observability, performance, backup, restore and resilience** and the current focus **model**.
2. Keep these subsystem deliverables in view: health model, metrics/logging, backup/restore, load/performance baselines.
3. Define or refine the domain model and invariants for this subsystem.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- health model
- metrics/logging
- backup/restore
- load/performance baselines

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] All new concepts have explicit ownership and invariants.
- [ ] Names/types avoid coupling canonical identity to provider/engine IDs.
- [ ] Schema/API implications are documented before implementation proceeds.
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
- **Behavior guaranteed after P0682**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.