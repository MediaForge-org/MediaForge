# P0603 — Music, podcasts and general audio media support: boundaries

**Track:** 31-music-podcasts  
**Priority:** P2  
**Prompt position in track:** 3/20  
**Depends on:** P0602

## Objective

Define module boundaries, interfaces and ownership rules.

This is a deliberately narrow step inside **Music, podcasts and general audio media support**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/MediaForge_Master_Engineering.md`
- `docs/MediaForge/modules/media-editions-and-lineage.md`
- `docs/MediaForge/modules/work-graph-and-cross-media.md`

Inspect these source paths/symbol neighborhoods first:
- `apps/server/app/Domain/Catalog`
- `apps/web/src/features/music`
- `apps/web/src/features/podcasts`

### UI references for this prompt
- None for this prompt unless a changed screen directly requires an existing design-system reference.

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Reuse the canonical Work/Edition/File model where it fits, but preserve audio-domain specifics such as tracks, albums, feeds, episodes and chapters.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Music, podcasts and general audio media support** and the current focus **boundaries**.
2. Keep these subsystem deliverables in view: album/track/feed models, progress/chapter semantics, artwork/metadata, library views.
3. Define module boundaries, interfaces and ownership rules.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.
6. Keep canonical MediaForge contracts free of engine-specific identifiers and implementation details.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- album/track/feed models
- progress/chapter semantics
- artwork/metadata
- library views

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.
- Do not pull this advanced feature ahead of the usable-core/roadmap gates merely because the code is interesting.

## Acceptance criteria

- [ ] Dependency direction is explicit and testable.
- [ ] No direct cross-engine database coupling is introduced.
- [ ] Interfaces are minimal and capability-oriented.
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
- **Behavior guaranteed after P0603**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
