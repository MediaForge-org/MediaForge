# P0460 — Full video/audio analysis, timestamps and multimodal detection: gate

**Track:** 23-adult-analysis  
**Priority:** P2  
**Prompt position in track:** 20/20  
**Depends on:** P0459, P0440

## Objective

Run the subsystem gate: verify architecture, tests, migrations, UX and no regression before moving on.

This is a deliberately narrow step inside **Full video/audio analysis, timestamps and multimodal detection**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/modules/adult-analysis-and-taxonomy.md`
- `docs/MediaForge/adr/0016-event-taxonomy-and-analysis.md`
- `docs/MediaForge/architecture/polyglot-runtime-and-contracts.md`
- `docs/MediaForge/modules/adult-3d-reconstruction-and-tattoo-coverage.md`
- `docs/MediaForge/architecture/ai-capabilities-model-registry.md`
- `docs/MediaForge/architecture/artifact-store-and-derived-assets.md`

Inspect these source paths/symbol neighborhoods first:
- `services/media-tools`
- `services/ai`
- `apps/server/app/Domain/Adult/Events`
- `services/ai/reconstruction`
- `services/ai/evaluation`
- `services/media-tools/crates/mesh`
- `platform/storage`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/33_adult_scene_full_analysis_timeline.png`
- `docs/MediaForge/ui-ux/reference-expanded/34_adult_tag_taxonomy_event_inspector.png`
- `docs/MediaForge/ui-ux/reference-expanded/45_scene_analysis_timeline_v2.png`
- `docs/MediaForge/ui-ux/reference-expanded/48_tattoo_coverage_analysis.png`
- `docs/MediaForge/ui-ux/reference-expanded/50_tattoo_evidence_fusion.png`
- `docs/MediaForge/ui-ux/reference-expanded/53_scene_event_inspector.png`
- `docs/MediaForge/ui-ux/reference-expanded/54_full_analysis_report.png`
- `docs/MediaForge/ui-ux/reference-expanded/61_3d_reconstruction_workspace.png`
- `docs/MediaForge/ui-ux/reference-expanded/62_3d_performer_viewer.png`
- `docs/MediaForge/ui-ux/reference-expanded/63_3d_quality_missing_surface.png`
- `docs/MediaForge/ui-ux/reference-expanded/64_3d_tattoo_projection.png`
- `docs/MediaForge/ui-ux/reference-expanded/65_body_surface_calibration.png`
- `docs/MediaForge/ui-ux/reference-expanded/66_3d_analysis_settings_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/67_3d_analysis_reference_board.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

**2026-08-16 architecture rule:** Heavy analysis/3D is optional. Full decode coverage remains measurable. Reconstruction is multi-scene/revisioned and provider-abstract. Do not hard-depend on completion of Track 29; use versioned contracts and let later Rust work optimize hot paths.


Aim for 100% decode coverage. Expensive models may use temporal candidate refinement, but coverage must be measurable and event timestamps/evidence reproducible.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Full video/audio analysis, timestamps and multimodal detection** and the current focus **gate**.
2. Keep these subsystem deliverables in view: decode coverage, visual/audio detectors, temporal refinement, timeline/evidence storage.
3. Run the subsystem gate: verify architecture, tests, migrations, UX and no regression before moving on.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- decode coverage
- visual/audio detectors
- temporal refinement
- timeline/evidence storage

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.
- Do not pull this advanced feature ahead of the usable-core/roadmap gates merely because the code is interesting.

## Acceptance criteria

- [ ] Focused tests and broader relevant suites pass.
- [ ] No known architecture violation is left undocumented.
- [ ] The next track/prompt is explicitly declared ready or blocked with reasons.
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
- **Behavior guaranteed after P0460**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.