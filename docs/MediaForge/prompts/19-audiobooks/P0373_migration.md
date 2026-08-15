# P0373 — Audiobook works, editions, chapters and storage choices: migration

**Track:** 19-audiobooks  
**Priority:** P1  
**Prompt position in track:** 13/20  
**Depends on:** P0372

## Objective

Add migration/backfill/import compatibility for pre-existing data or layout.

This is a deliberately narrow step inside **Audiobook works, editions, chapters and storage choices**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/modules/audiobook-chapters-and-storage.md`
- `docs/MediaForge/adr/0018-audiobook-chapter-storage.md`
- `docs/MediaForge/ui-ux/FEATURE_SCREEN_SPECIFICATIONS.md`

Inspect these source paths/symbol neighborhoods first:
- `apps/server/app/Domain/Audiobooks`
- `apps/web/src/features/audiobooks`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/12_audiobooks_dashboard.png`
- `docs/MediaForge/ui-ux/reference-expanded/37_audiobook_single_file_chapter_verification.png`
- `docs/MediaForge/ui-ux/reference-expanded/38_audiobook_storage_strategy.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Model Work, Edition, Chapter and AudioFile separately. Verified official chapters may be stored logically, exported to CUE/JSON, or physically split only after explicit user choice.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Audiobook works, editions, chapters and storage choices** and the current focus **migration**.
2. Keep these subsystem deliverables in view: work/edition/chapter model, official chapter verification, CUE/JSON sidecars, split/keep/archive choices.
3. Add migration/backfill/import compatibility for pre-existing data or layout.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.
6. For any schema/layout migration, make the operation restart-safe and prove that existing data/files are not silently lost.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- work/edition/chapter model
- official chapter verification
- CUE/JSON sidecars
- split/keep/archive choices

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] Old layout/data can be migrated or read during transition.
- [ ] Migration is restartable and failure-safe.
- [ ] No user files are deleted or renamed implicitly.
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
- **Behavior guaranteed after P0373**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
