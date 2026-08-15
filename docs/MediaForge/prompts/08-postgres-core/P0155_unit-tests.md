# P0155 — PostgreSQL canonical model and database boundaries: unit-tests

**Track:** 08-postgres-core  
**Priority:** P0  
**Prompt position in track:** 15/20  
**Depends on:** P0154

## Objective

Add focused unit tests for the subsystem invariants and edge cases.

This is a deliberately narrow step inside **PostgreSQL canonical model and database boundaries**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/postgresql-source-of-truth.md`
- `docs/MediaForge/modules/media-editions-and-lineage.md`
- `docs/MediaForge/MediaForge_Master_Engineering.md`

Inspect these source paths/symbol neighborhoods first:
- `apps/server/database`
- `apps/server/app/Domain`
- `database`

### UI references for this prompt
- None for this prompt unless a changed screen directly requires an existing design-system reference.

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

PostgreSQL is the canonical MediaForge source of truth. Engine-local stores may exist during transitions but never become canonical cross-product identity stores.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **PostgreSQL canonical model and database boundaries** and the current focus **unit-tests**.
2. Keep these subsystem deliverables in view: canonical identifiers, constraints/indexes, provider/engine mappings, migration policy.
3. Add focused unit tests for the subsystem invariants and edge cases.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- canonical identifiers
- constraints/indexes
- provider/engine mappings
- migration policy

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] Core invariants have deterministic unit coverage.
- [ ] Tests do not depend on external networks.
- [ ] Regression cases from prior bugs are included where relevant.
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
- **Behavior guaranteed after P0155**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
