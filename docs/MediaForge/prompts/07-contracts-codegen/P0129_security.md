# P0129 — OpenAPI, JSON Schema, events and generated clients: security

**Track:** 07-contracts-codegen  
**Priority:** P0  
**Prompt position in track:** 9/20  
**Depends on:** P0128

## Objective

Apply authorization, privacy and security rules to the subsystem.

This is a deliberately narrow step inside **OpenAPI, JSON Schema, events and generated clients**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/engine-contracts.md`
- `docs/MediaForge/architecture/polyglot-runtime-and-contracts.md`
- `docs/MediaForge/architecture/target-monorepo.md`
- `docs/MediaForge/modules/adult-3d-reconstruction-and-tattoo-coverage.md`
- `docs/MediaForge/modules/plugin-theme-sdk.md`
- `docs/MediaForge/architecture/ai-capabilities-model-registry.md`

Inspect these source paths/symbol neighborhoods first:
- `packages/contracts`
- `packages/sdk`
- `tools`
- `packages/contracts/domains/anatomy`
- `packages/contracts/domains/reconstruction`
- `packages/contracts/domains/plugins`

### UI references for this prompt
- None for this prompt unless a changed screen directly requires an existing design-system reference.

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

**2026-08-16 architecture rule:** Contracts must cover optional capabilities and stable IDs for anatomy/reconstruction/plugins without coupling to a specific AI/body-model provider.


Contracts are language-neutral. Generated code must be reproducible and committed/ignored according to the documented policy, with no hand-edited generated clients.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **OpenAPI, JSON Schema, events and generated clients** and the current focus **security**.
2. Keep these subsystem deliverables in view: OpenAPI schema, event schemas, generated SDK policy, compatibility tests.
3. Apply authorization, privacy and security rules to the subsystem.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- OpenAPI schema
- event schemas
- generated SDK policy
- compatibility tests

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] Authorization happens server-side.
- [ ] Sensitive existence/data does not leak in locked/unauthorized contexts.
- [ ] Security regression tests cover direct API access, not just UI navigation.
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
- **Behavior guaranteed after P0129**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.