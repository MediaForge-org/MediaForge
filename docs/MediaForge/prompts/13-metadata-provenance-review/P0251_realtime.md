# P0251 — Metadata vault, provenance, matching and review center: realtime

**Track:** 13-metadata-provenance-review  
**Priority:** P0  
**Prompt position in track:** 11/20  
**Depends on:** P0250

## Objective

Add progress/event/realtime behavior where applicable; otherwise explicitly document why it is not needed.

This is a deliberately narrow step inside **Metadata vault, provenance, matching and review center**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/MediaForge_Master_Engineering.md`
- `docs/MediaForge/modules/adult-lineage-and-catalog.md`
- `docs/MediaForge/ui-ux/FEATURE_SCREEN_SPECIFICATIONS.md`

Inspect these source paths/symbol neighborhoods first:
- `apps/server/app/Domain/Metadata`
- `apps/server/app/Domain/Matching`
- `apps/server/app/Domain/Reviews`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/15_metadata_matching_workbench.png`
- `docs/MediaForge/ui-ux/reference-expanded/20_provenance_inspector.png`
- `docs/MediaForge/ui-ux/reference-expanded/35_adult_metadata_provenance_date_conflict.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Every important field should be traceable to evidence/source/observation where practical. Manual overrides must survive automated syncs.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Metadata vault, provenance, matching and review center** and the current focus **realtime**.
2. Keep these subsystem deliverables in view: field observations, source authority, manual overrides, review queue.
3. Add progress/event/realtime behavior where applicable; otherwise explicitly document why it is not needed.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.
6. Assume duplicate delivery/retry/restart can occur; design idempotency and state transitions accordingly.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- field observations
- source authority
- manual overrides
- review queue

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] Event schema is versioned or tied to an existing contract.
- [ ] Reconnect/duplicate delivery behavior is safe.
- [ ] The UI remains correct when realtime transport is unavailable.
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
- **Behavior guaranteed after P0251**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
