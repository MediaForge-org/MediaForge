# P0421 — Hierarchical adult tags, attributes, evidence and event model: audit

**Track:** 22-adult-taxonomy  
**Priority:** P0  
**Prompt position in track:** 1/20  
**Depends on:** P0420, P0160, P0400

## Objective

Audit the current implementation and establish the exact baseline for this subsystem.

This is a deliberately narrow step inside **Hierarchical adult tags, attributes, evidence and event model**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/modules/adult-analysis-and-taxonomy.md`
- `docs/MediaForge/adr/0016-event-taxonomy-and-analysis.md`
- `docs/MediaForge/ui-ux/FEATURE_SCREEN_SPECIFICATIONS.md`

Inspect these source paths/symbol neighborhoods first:
- `apps/server/app/Domain/Adult/Taxonomy`
- `apps/server/app/Domain/Adult/Events`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/34_adult_tag_taxonomy_event_inspector.png`
- `docs/MediaForge/ui-ux/reference-expanded/41_feature_matrix_detailed.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Tags are hierarchical events/attributes rather than a flat vocabulary. Example: `puke` can carry attributes such as watery, milky or chunky on each occurrence.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Hierarchical adult tags, attributes, evidence and event model** and the current focus **audit**.
2. Keep these subsystem deliverables in view: taxonomy tree, event attributes, evidence model, checked-absent semantics.
3. Audit the current implementation and establish the exact baseline for this subsystem.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- taxonomy tree
- event attributes
- evidence model
- checked-absent semantics

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] Produce a concise baseline report grounded in code/tests, not assumptions.
- [ ] Identify blockers, unknowns and exact files affected by the next prompt.
- [ ] Do not change production behavior unless the prompt explicitly needs a tiny diagnostic fixture.
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
- **Behavior guaranteed after P0421**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
