# P0314 — Unified playback sessions, gateway and stream routing: fixtures

**Track:** 16-playback-gateway  
**Priority:** P1  
**Prompt position in track:** 14/20  
**Depends on:** P0313

## Objective

Add representative fixtures, factories and deterministic development data.

This is a deliberately narrow step inside **Unified playback sessions, gateway and stream routing**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/unified-application.md`
- `docs/MediaForge/architecture/engine-contracts.md`
- `docs/MediaForge/architecture/routing-and-public-urls.md`

Inspect these source paths/symbol neighborhoods first:
- `platform/gateway`
- `apps/server/app/Domain/Playback`
- `apps/web/src/features/playback`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/03_core_scene_detail.png`
- `docs/MediaForge/ui-ux/reference-expanded/11_movies_tv_library.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Large media bytes must not proxy through Laravel unnecessarily. Control/session APIs may pass through the server; streams route efficiently through the gateway to the responsible engine.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Unified playback sessions, gateway and stream routing** and the current focus **fixtures**.
2. Keep these subsystem deliverables in view: PlaybackSession, gateway routes, engine handoff, progress reporting.
3. Add representative fixtures, factories and deterministic development data.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- PlaybackSession
- gateway routes
- engine handoff
- progress reporting

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] Fixtures are synthetic and safe to commit.
- [ ] They cover success, ambiguity, missing-data and conflict cases.
- [ ] No private library data or secrets enter the repository.
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
- **Behavior guaranteed after P0314**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
