# P0278 — Acquisition Center domain and source abstraction: performance

**Track:** 14-acquisition-center  
**Priority:** P1  
**Prompt position in track:** 18/20  
**Depends on:** P0277

## Objective

Profile and harden the subsystem for realistic library sizes and failure conditions.

This is a deliberately narrow step inside **Acquisition Center domain and source abstraction**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/managed-upstreams-and-product-surface.md`
- `docs/MediaForge/modules/acquisition-automation-and-postprocessing.md`
- `docs/MediaForge/adr/0025-managed-upstream-backends.md`
- `docs/MediaForge/adr/0027-acquisition-blueprint-processing-dag.md`
- `docs/MediaForge/modules/acquisition-center.md`
- `docs/MediaForge/adr/0017-acquisition-and-staging.md`
- `docs/MediaForge/ui-ux/FEATURE_SCREEN_SPECIFICATIONS.md`

Inspect these source paths/symbol neighborhoods first:
- `apps/server/app/Domain/Acquisition`
- `apps/web/src/features/acquisition`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/68_backend_capabilities_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/69_localization_translation_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/30_acquisition_center.png`
- `docs/MediaForge/ui-ux/reference-expanded/31_manual_download_intake.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

MediaForge may accept user-supplied NZB/torrent/magnet inputs and permitted custom sources, but it must not become a piracy search engine or bypass access controls.


## Mandatory target additions — 2026-08-17

- Acquisition is a MediaForge product surface: Search, Wanted, Releases, Downloads, Queue, History, Upgrades, Import and Sources use MediaForge UI.
- Support broad provider capability adapters (Newznab/Torznab/Prowlarr/Jackett-compatible/native/RSS/Browser Companion/manual) instead of a hard-coded site list.
- Sonarr/Radarr/Whisparr are transitional automation backends; Prowlarr/SAB/qBittorrent may remain specialised managed backends.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Acquisition Center domain and source abstraction** and the current focus **performance**.
2. Keep these subsystem deliverables in view: AcquisitionRequest, source/custom-link model, manual intake, acquisition history.
3. Profile and harden the subsystem for realistic library sizes and failure conditions.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- AcquisitionRequest
- source/custom-link model
- manual intake
- acquisition history

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] A representative benchmark/load fixture exists.
- [ ] No optimization is accepted without before/after evidence.
- [ ] Memory, I/O and query behavior are considered, not only wall-clock time.
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
- **Behavior guaranteed after P0278**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
