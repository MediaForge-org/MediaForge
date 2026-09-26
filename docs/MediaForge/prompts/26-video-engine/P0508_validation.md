# P0508 — Jellyfin external video/playback adapter and compatibility: validation

**Track:** 26-video-engine  
**Priority:** P2  
**Prompt position in track:** 8/20  
**Depends on:** P0507, P0140, P0120

## Objective

Add validation, conflict handling and deterministic failure semantics.

This is a deliberately narrow step inside **Jellyfin external video/playback adapter and compatibility**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/external-specialist-services-and-adapters.md`
- `docs/MediaForge/adr/0028-external-specialist-services-via-adapters.md`
- `docs/MediaForge/architecture/engine-contracts.md`
- `docs/MediaForge/architecture/postgresql-source-of-truth.md`
- `docs/MediaForge/architecture/unified-application.md`
- `docs/MediaForge/architecture/player-audio-loudness-and-device-policy.md`
- `docs/MediaForge/architecture/managed-upstreams-and-product-surface.md`

Inspect these source paths/symbol neighborhoods first:
- `app/Connectors/Jellyfin`
- `app/Connectors/Sdk`
- `packages/contracts`
- `platform/integrations`
- `platform/gateway`
- `deploy/dev/docker-compose.yml`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/68_backend_capabilities_acquisition_overview.png`
- None for this prompt unless a changed screen directly requires an existing design-system reference.

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Treat Jellyfin as an independently updateable external specialist service. Integrate only through supported/versioned APIs behind MediaForge adapter contracts; never read/write Jellyfin's internal database and do not copy its source tree by default.


## Mandatory target architecture — 2026-09-27

- Detect Jellyfin version/capabilities and maintain an explicit supported-version compatibility range.
- Expand the existing Jellyfin adapter rather than creating a second integration stack.
- Keep MediaForge PostgreSQL as canonical MediaForge identity/catalog state; Jellyfin IDs remain mappings.
- Support richer catalog/technical-media synchronization and, when the prompt focus reaches it, playback/session/track/subtitle/device-profile capabilities through Jellyfin's supported API.
- Normal browsing must use synchronized MediaForge state and remain usable during temporary Jellyfin outage; only Jellyfin-dependent live capabilities degrade.
- No Jellyfin source import, fork cutover or direct Jellyfin DB access is required.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Jellyfin external video/playback adapter and compatibility** and the current focus **validation**.
2. Keep these subsystem deliverables in view: Jellyfin API adapter boundary, capability/version surface, catalog/playback integration, compatibility/sync workflow.
3. Add validation, conflict handling and deterministic failure semantics.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- Jellyfin API adapter boundary
- MediaForge adapter/canonical mapping
- capability/version surface
- compatibility/sync/offline workflow

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.
- Do not pull this advanced feature ahead of the usable-core/roadmap gates merely because the code is interesting.

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
- **Behavior guaranteed after P0508**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
