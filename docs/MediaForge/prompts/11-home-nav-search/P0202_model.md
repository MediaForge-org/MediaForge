# P0202 — Unified home, navigation and global search shell: model

**Track:** 11-home-nav-search  
**Priority:** P0  
**Prompt position in track:** 2/20  
**Depends on:** P0201

## Objective

Define or refine the domain model and invariants for this subsystem.

This is a deliberately narrow step inside **Unified home, navigation and global search shell**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/localization-and-professional-translation.md`
- `docs/MediaForge/adr/0026-localization-and-translation.md`
- `docs/MediaForge/modules/acquisition-automation-and-postprocessing.md`
- `docs/MediaForge/ui-ux/unified-interface.md`
- `docs/MediaForge/ui-ux/UI_IMPLEMENTATION_PROMPT.md`
- `docs/MediaForge/architecture/routing-and-public-urls.md`

Inspect these source paths/symbol neighborhoods first:
- `apps/web/src/features/home`
- `apps/web/src/app`
- `apps/server/app/Domain/Search`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/69_localization_translation_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/10_unified_home_dashboard.png`
- `docs/MediaForge/ui-ux/reference-expanded/11_movies_tv_library.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Adult content must not appear in the normal home/search/navigation state while locked. The home must still feel like one product across multiple engines.


## Mandatory target additions — 2026-08-17

- Search must consider localised/alternate titles and locale-aware display/sorting without changing canonical identity.
- Global search may surface Acquisition candidates through the unified MediaForge product surface rather than linking users to separate upstream apps.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Unified home, navigation and global search shell** and the current focus **model**.
2. Keep these subsystem deliverables in view: app shell, navigation model, home row contracts, global search route/state.
3. Define or refine the domain model and invariants for this subsystem.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- app shell
- navigation model
- home row contracts
- global search route/state

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] All new concepts have explicit ownership and invariants.
- [ ] Names/types avoid coupling canonical identity to provider/engine IDs.
- [ ] Schema/API implications are documented before implementation proceeds.
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
- **Behavior guaranteed after P0202**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
