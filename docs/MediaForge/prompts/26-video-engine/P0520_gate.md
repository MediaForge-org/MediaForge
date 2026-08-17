# P0520 — Jellyfin-derived video engine integration and fork boundary: gate

**Track:** 26-video-engine  
**Priority:** P2  
**Prompt position in track:** 20/20  
**Depends on:** P0519, P0140, P0120

## Objective

Run the subsystem gate: verify architecture, tests, migrations, UX and no regression before moving on.

This is a deliberately narrow step inside **Jellyfin-derived video engine integration and fork boundary**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/player-audio-loudness-and-device-policy.md`
- `docs/MediaForge/architecture/managed-upstreams-and-product-surface.md`
- `docs/MediaForge/adr/0025-managed-upstream-backends.md`
- `docs/MediaForge/architecture/engine-contracts.md`
- `docs/MediaForge/architecture/target-monorepo.md`
- `docs/MediaForge/architecture/unified-application.md`

Inspect these source paths/symbol neighborhoods first:
- `engines/video`
- `packages/contracts`
- `platform/gateway`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/68_backend_capabilities_acquisition_overview.png`
- None for this prompt unless a changed screen directly requires an existing design-system reference.

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Keep upstream Jellyfin-derived code recognizable and syncable. MediaForge integration belongs behind explicit compatibility/integration boundaries.


## Mandatory target additions — 2026-08-17

- Jellyfin source should already be pinned/imported from Track 02; this track completes/adapts the internal Video Engine rather than first copying the upstream project.
- At actual import/update time verify the current official stable upstream; prefer Jellyfin 12.x stable as the initial long-lived baseline when available.

## Mandatory target additions — 2026-08-17 — player audio

- Gate the video-engine bridge against canonical MediaForge audio semantics and explicit direct-play/transcode/degraded capability behavior; native Jellyfin UI/settings must not become the product contract.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Jellyfin-derived video engine integration and fork boundary** and the current focus **gate**.
2. Keep these subsystem deliverables in view: upstream import boundary, MediaForge adapter, capability surface, upgrade/sync workflow.
3. Run the subsystem gate: verify architecture, tests, migrations, UX and no regression before moving on.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- upstream import boundary
- MediaForge adapter
- capability surface
- upgrade/sync workflow

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
- **Behavior guaranteed after P0520**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
