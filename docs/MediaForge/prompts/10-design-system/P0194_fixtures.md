# P0194 — MediaForge design system and UI primitives: fixtures

**Track:** 10-design-system  
**Priority:** P0  
**Prompt position in track:** 14/20  
**Depends on:** P0193

## Objective

Add representative fixtures, factories and deterministic development data.

This is a deliberately narrow step inside **MediaForge design system and UI primitives**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/ui-ux/UI_IMPLEMENTATION_PROMPT.md`
- `docs/MediaForge/ui-ux/design-system.md`
- `docs/MediaForge/ui-ux/SCREEN_REFERENCE_INDEX.md`

Inspect these source paths/symbol neighborhoods first:
- `apps/web/src`
- `packages/design-tokens`
- `packages/ui-web`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/01_core_home_dashboard.png`
- `docs/MediaForge/ui-ux/reference-expanded/10_unified_home_dashboard.png`
- `docs/MediaForge/ui-ux/reference-expanded/11_movies_tv_library.png`
- `docs/MediaForge/ui-ux/reference-expanded/12_audiobooks_dashboard.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Use the reference images as visual specifications, not as pixel-perfect screenshots. Build reusable primitives and tokens rather than page-specific one-off CSS.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **MediaForge design system and UI primitives** and the current focus **fixtures**.
2. Keep these subsystem deliverables in view: tokens, primitives, layout components, visual regression harness.
3. Add representative fixtures, factories and deterministic development data.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- tokens
- primitives
- layout components
- visual regression harness

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
- **Behavior guaranteed after P0194**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
