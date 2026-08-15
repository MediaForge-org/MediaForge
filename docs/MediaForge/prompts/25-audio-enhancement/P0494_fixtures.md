# P0494 — Audio restoration, upscaler and reconstructed editions: fixtures

**Track:** 25-audio-enhancement  
**Priority:** P2  
**Prompt position in track:** 14/20  
**Depends on:** P0493, P0240, P0580

## Objective

Add representative fixtures, factories and deterministic development data.

This is a deliberately narrow step inside **Audio restoration, upscaler and reconstructed editions**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/modules/audio-upscaler.md`
- `docs/MediaForge/modules/media-editions-and-lineage.md`
- `docs/MediaForge/architecture/polyglot-runtime-and-contracts.md`

Inspect these source paths/symbol neighborhoods first:
- `services/ai`
- `services/media-tools`
- `apps/server/app/Domain/Enhancement`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/18_audio_enhancement_upscaler.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Never overwrite originals by default. Reconstructed audio is a new edition/artifact and must be labeled reconstructed rather than falsely lossless-original.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Audio restoration, upscaler and reconstructed editions** and the current focus **fixtures**.
2. Keep these subsystem deliverables in view: analysis profile, restoration job, reconstructed edition, A/B comparison.
3. Add representative fixtures, factories and deterministic development data.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- analysis profile
- restoration job
- reconstructed edition
- A/B comparison

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.
- Do not pull this advanced feature ahead of the usable-core/roadmap gates merely because the code is interesting.

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
- **Behavior guaranteed after P0494**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
