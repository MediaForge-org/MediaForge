# P0485 — Audio restoration, upscaler and reconstructed editions: application

**Track:** 25-audio-enhancement  
**Priority:** P2  
**Prompt position in track:** 5/20  
**Depends on:** P0484, P0240, P0580

## Objective

Implement application services, commands/queries or orchestration logic.

This is a deliberately narrow step inside **Audio restoration, upscaler and reconstructed editions**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/player-audio-loudness-and-device-policy.md`
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

## Mandatory target additions — 2026-08-17 — player audio

- Keep temporary playback gain/normalization/EQ/DRC strictly separate from restoration/reconstruction jobs. A user selecting 200% volume or +dB playback gain must never create or relabel a reconstructed edition.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Audio restoration, upscaler and reconstructed editions** and the current focus **application**.
2. Keep these subsystem deliverables in view: analysis profile, restoration job, reconstructed edition, A/B comparison.
3. Implement application services, commands/queries or orchestration logic.
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

- [ ] Business behavior is implemented outside controllers/UI components.
- [ ] Operations are idempotent where retries are possible.
- [ ] Errors are domain-specific and deterministic.
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
- **Behavior guaranteed after P0485**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
