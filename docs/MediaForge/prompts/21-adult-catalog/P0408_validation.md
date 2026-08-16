# P0408 — Adult scene/performer/studio catalog, sources and coverage: validation

**Track:** 21-adult-catalog  
**Priority:** P1  
**Prompt position in track:** 8/20  
**Depends on:** P0407

## Objective

Add validation, conflict handling and deterministic failure semantics.

This is a deliberately narrow step inside **Adult scene/performer/studio catalog, sources and coverage**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/managed-upstreams-and-product-surface.md`
- `docs/MediaForge/modules/acquisition-automation-and-postprocessing.md`
- `docs/MediaForge/architecture/localization-and-professional-translation.md`
- `docs/MediaForge/adr/0025-managed-upstream-backends.md`
- `docs/MediaForge/adr/0027-acquisition-blueprint-processing-dag.md`
- `docs/MediaForge/modules/adult-lineage-and-catalog.md`
- `docs/MediaForge/modules/adult-enhancement.md`
- `docs/MediaForge/modules/adult-engine-target.md`
- `docs/MediaForge/modules/adult-3d-reconstruction-and-tattoo-coverage.md`

Inspect these source paths/symbol neighborhoods first:
- `apps/server/app/Domain/Adult`
- `apps/web/src/features/performers`
- `apps/web/src/features/scenes`
- `apps/web/src/features/studios`
- `apps/server/app/Domain/Adult/Analysis`
- `packages/contracts/domains/anatomy`
- `apps/web/src/features/adult`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/68_backend_capabilities_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/69_localization_translation_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/02_core_performer_detail.png`
- `docs/MediaForge/ui-ux/reference-expanded/04_core_coverage_library_management.png`
- `docs/MediaForge/ui-ux/reference-expanded/36_performer_catalog_completeness.png`
- `docs/MediaForge/ui-ux/reference-expanded/43_female_performer_tattoo_profile.png`
- `docs/MediaForge/ui-ux/reference-expanded/44_advanced_tattoo_region_filter.png`
- `docs/MediaForge/ui-ux/reference-expanded/48_tattoo_coverage_analysis.png`
- `docs/MediaForge/ui-ux/reference-expanded/49_tattoo_region_coverage_inspector.png`
- `docs/MediaForge/ui-ux/reference-expanded/50_tattoo_evidence_fusion.png`
- `docs/MediaForge/ui-ux/reference-expanded/51_tattoo_coverage_profile_dashboard.png`
- `docs/MediaForge/ui-ux/reference-expanded/61_3d_reconstruction_workspace.png`
- `docs/MediaForge/ui-ux/reference-expanded/62_3d_performer_viewer.png`
- `docs/MediaForge/ui-ux/reference-expanded/63_3d_quality_missing_surface.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

**2026-08-16 architecture rule:** Female performer tattoo coverage is a first-class catalog/filter dimension; surface percentage and fine anatomy regions matter more than tattoo count.


Scenes, performers, studios, brands, networks and historical sources need first-class lineage. Deep sync is library-driven rather than a global mirror.


## Mandatory target additions — 2026-08-17

- Adult acquisition results/automation map to MediaForge performer/scene/studio identities; backend-specific entities never become canonical IDs.
- Normal search/Wanted/download UI is MediaForge-owned; Whisparr/Prowlarr/qBittorrent/SAB remain implementation backends where configured.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Adult scene/performer/studio catalog, sources and coverage** and the current focus **validation**.
2. Keep these subsystem deliverables in view: Scene/Performer/Studio models, lineage/history, source coverage, library-driven activation.
3. Add validation, conflict handling and deterministic failure semantics.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- Scene/Performer/Studio models
- lineage/history
- source coverage
- library-driven activation

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] Ambiguous input fails safely rather than guessing.
- [ ] Conflict states are persisted/auditable where needed.
- [ ] Negative tests cover malformed and contradictory cases.
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
- **Behavior guaranteed after P0408**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.