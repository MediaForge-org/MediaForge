# P0007 — Governance, repository audit and execution discipline: frontend

**Track:** 01-governance-audit  
**Priority:** P0  
**Prompt position in track:** 7/20  
**Depends on:** P0006

## Objective

Implement the first frontend integration surface without redesigning unrelated screens.

This is a deliberately narrow step inside **Governance, repository audit and execution discipline**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/CURRENT_PHASE.md`
- `docs/MediaForge/PRODUCT_DECISIONS_2026-08.md`
- `docs/MediaForge/CLAUDE_IMPLEMENTATION_DIRECTIVE.md`

Inspect these source paths/symbol neighborhoods first:
- `repository root`
- `git history`
- `tests`
- `docs/MediaForge`

### UI references for this prompt
- None for this prompt unless a changed screen directly requires an existing design-system reference.

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Do not implement product features. This track exists to establish a trustworthy baseline, execution rules, dependency graph and rollback discipline.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Governance, repository audit and execution discipline** and the current focus **frontend**.
2. Keep these subsystem deliverables in view: baseline inventory, dependency/phase map, risk register, execution guardrail.
3. Implement the first frontend integration surface without redesigning unrelated screens.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- baseline inventory
- dependency/phase map
- risk register
- execution guardrail

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] UI uses shared design primitives and current route/API contracts.
- [ ] Loading/empty/error/disabled states are implemented.
- [ ] Unrelated pages are not restyled in this prompt.
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
- **Behavior guaranteed after P0007**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
