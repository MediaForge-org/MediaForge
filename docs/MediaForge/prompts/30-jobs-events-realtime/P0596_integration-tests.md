# P0596 — Background jobs, events, realtime progress and orchestration: integration-tests

**Track:** 30-jobs-events-realtime  
**Priority:** P0  
**Prompt position in track:** 16/20  
**Depends on:** P0595

## Objective

Add cross-module/engine integration tests for the subsystem.

This is a deliberately narrow step inside **Background jobs, events, realtime progress and orchestration**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/managed-upstreams-and-product-surface.md`
- `docs/MediaForge/modules/acquisition-automation-and-postprocessing.md`
- `docs/MediaForge/architecture/localization-and-professional-translation.md`
- `docs/MediaForge/adr/0025-managed-upstream-backends.md`
- `docs/MediaForge/adr/0026-localization-and-translation.md`
- `docs/MediaForge/adr/0027-acquisition-blueprint-processing-dag.md`
- `docs/MediaForge/architecture/polyglot-runtime-and-contracts.md`
- `docs/MediaForge/architecture/engine-contracts.md`
- `docs/MediaForge/MediaForge_Master_Engineering.md`

Inspect these source paths/symbol neighborhoods first:
- `apps/server/app/Application/Jobs`
- `packages/contracts/events`
- `apps/web/src/app/providers`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/68_backend_capabilities_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/69_localization_translation_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/16_tasks_queues_sync_operations.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Jobs must be idempotent, observable and restart-safe. Events are contracts, not ad-hoc websocket payloads.


## Mandatory target additions — 2026-08-17

- Normalise upstream events into canonical MediaForge event/status schemas before realtime/UI localisation.
- Translation jobs and post-processing DAG nodes are resumable/idempotent background work with observable progress and bounded retries.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Background jobs, events, realtime progress and orchestration** and the current focus **integration-tests**.
2. Keep these subsystem deliverables in view: queue conventions, job state model, event envelope, realtime transport.
3. Add cross-module/engine integration tests for the subsystem.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- queue conventions
- job state model
- event envelope
- realtime transport

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] Real module boundaries are exercised.
- [ ] External engines/providers are mocked/faked at the contract boundary unless a hermetic test container is explicitly required.
- [ ] Database transactions and cleanup are deterministic.
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
- **Behavior guaranteed after P0596**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
