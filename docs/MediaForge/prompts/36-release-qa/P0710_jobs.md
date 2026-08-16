# P0710 — Docker images, CI, releases, security QA and final integration: jobs

**Track:** 36-release-qa  
**Priority:** P1  
**Prompt position in track:** 10/20  
**Depends on:** P0709

## Objective

Add background-job behavior, idempotency and retry/checkpoint semantics where applicable.

This is a deliberately narrow step inside **Docker images, CI, releases, security QA and final integration**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/docker-release-distribution.md`
- `docs/MediaForge/DEVELOPMENT_PHASES_DETAILED.md`
- `docs/MediaForge/CLAUDE_IMPLEMENTATION_DIRECTIVE.md`
- `docs/MediaForge/architecture/ai-capabilities-model-registry.md`
- `docs/MediaForge/architecture/green-commit-workflow.md`

Inspect these source paths/symbol neighborhoods first:
- `platform/docker`
- `platform/compose`
- `platform/releases`
- `.github`
- `tests`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/58_docker_deployment.png`
- `docs/MediaForge/ui-ux/reference-expanded/60_implementation_roadmap_36_tracks.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

**2026-08-16 architecture rule:** AI images/models are optional release profiles/downloads; Core images must stay usable without heavy model weights. CI remains green before advancing.


Official images/releases require reproducibility, SBOM/provenance/signing policy, multi-arch strategy, migration gates and documented rollback paths.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Docker images, CI, releases, security QA and final integration** and the current focus **jobs**.
2. Keep these subsystem deliverables in view: container build, CI matrix, security/supply-chain checks, release/rollback workflow.
3. Add background-job behavior, idempotency and retry/checkpoint semantics where applicable.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.
6. Assume duplicate delivery/retry/restart can occur; design idempotency and state transitions accordingly.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- container build
- CI matrix
- security/supply-chain checks
- release/rollback workflow

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] Retries do not duplicate irreversible effects.
- [ ] Progress/checkpoint semantics survive worker restart where needed.
- [ ] Failed jobs surface actionable state.
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
- **Behavior guaranteed after P0710**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.