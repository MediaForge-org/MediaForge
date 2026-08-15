# P0580 — Rust MediaTools service and native media plumbing: gate

**Track:** 29-rust-media-tools  
**Priority:** P1  
**Prompt position in track:** 20/20  
**Depends on:** P0579

## Objective

Run the subsystem gate: verify architecture, tests, migrations, UX and no regression before moving on.

This is a deliberately narrow step inside **Rust MediaTools service and native media plumbing**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/polyglot-runtime-and-contracts.md`
- `docs/MediaForge/architecture/target-monorepo.md`
- `docs/MediaForge/modules/disc-engine.md`

Inspect these source paths/symbol neighborhoods first:
- `services/media-tools`
- `packages/contracts`

### UI references for this prompt
- None for this prompt unless a changed screen directly requires an existing design-system reference.

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Use Rust for new native MediaForge media tooling while reusing mature native libraries such as FFmpeg/libbluray through processes or safe bindings where appropriate.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Rust MediaTools service and native media plumbing** and the current focus **gate**.
2. Keep these subsystem deliverables in view: service protocol, ffprobe/ffmpeg wrapper, hash/fingerprint pipeline, disc/media utilities.
3. Run the subsystem gate: verify architecture, tests, migrations, UX and no regression before moving on.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- service protocol
- ffprobe/ffmpeg wrapper
- hash/fingerprint pipeline
- disc/media utilities

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

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
- **Behavior guaranteed after P0580**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
