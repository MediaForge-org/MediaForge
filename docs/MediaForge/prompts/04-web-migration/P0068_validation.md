# P0068 — React/TypeScript web app relocation into apps/web: validation

**Track:** 04-web-migration  
**Priority:** P0  
**Prompt position in track:** 8/20  
**Depends on:** P0067

## Objective

Add validation, conflict handling and deterministic failure semantics.

This is a deliberately narrow step inside **React/TypeScript web app relocation into apps/web**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/target-monorepo.md`
- `docs/MediaForge/ui-ux/design-system.md`
- `docs/MediaForge/ui-ux/UI_IMPLEMENTATION_PROMPT.md`

Inspect these source paths/symbol neighborhoods first:
- `resources/js`
- `resources/css`
- `package.json`
- `vite.config.ts`
- `tsconfig.json`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/01_core_home_dashboard.png`
- `docs/MediaForge/ui-ux/reference-expanded/10_unified_home_dashboard.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Move the React/TypeScript application without redesigning it yet. Preserve current user-visible behavior until the dedicated design-system prompts.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **React/TypeScript web app relocation into apps/web** and the current focus **validation**.
2. Keep these subsystem deliverables in view: React entrypoint, Vite/TS config, asset build, web-only dev server.
3. Add validation, conflict handling and deterministic failure semantics.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- React entrypoint
- Vite/TS config
- asset build
- web-only dev server

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

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
- **Behavior guaranteed after P0068**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
