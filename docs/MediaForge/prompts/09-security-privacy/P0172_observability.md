# P0172 — Authentication, authorization, security and privacy foundation: observability

**Track:** 09-security-privacy  
**Priority:** P0  
**Prompt position in track:** 12/20  
**Depends on:** P0171

## Objective

Add logs, metrics, audit events and health visibility for the subsystem.

This is a deliberately narrow step inside **Authentication, authorization, security and privacy foundation**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/localization-and-professional-translation.md`
- `docs/MediaForge/adr/0026-localization-and-translation.md`
- `docs/MediaForge/architecture/managed-upstreams-and-product-surface.md`
- `docs/MediaForge/PRODUCT_DECISIONS_2026-08.md`
- `docs/MediaForge/modules/adult-enhancement.md`
- `docs/MediaForge/ui-ux/adult-ui-enhancement.md`
- `docs/MediaForge/architecture/ai-capabilities-model-registry.md`
- `docs/MediaForge/modules/adult-3d-reconstruction-and-tattoo-coverage.md`
- `docs/MediaForge/modules/plugin-theme-sdk.md`

Inspect these source paths/symbol neighborhoods first:
- `apps/server/app/Domain/Users`
- `apps/server/app/Domain/Privacy`
- `apps/web/src/app/auth`
- `platform/storage`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/69_localization_translation_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/21_private_mode_unlock.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

**2026-08-16 architecture rule:** Private 3D meshes, textures, tattoo masks, body measurements and evidence inherit Adult zero-leak rules. Plugin/AI permissions are explicit.


Treat private/adult zero-leak as a server-side property. Client hiding alone is insufficient. Unauthorized resource existence must not leak through search, errors, preload, caches or events.


## Mandatory target additions — 2026-08-17

- Cloud translation is optional and policy-gated; private/Adult metadata must not be sent externally without explicit allowed-provider configuration.
- Managed-upstream credentials/cookies/API keys are secrets and must never leak into URLs, logs, provenance or normal UI.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Authentication, authorization, security and privacy foundation** and the current focus **observability**.
2. Keep these subsystem deliverables in view: auth policy, private-mode session state, zero-leak query scopes, audit/security tests.
3. Add logs, metrics, audit events and health visibility for the subsystem.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- auth policy
- private-mode session state
- zero-leak query scopes
- audit/security tests

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] Important state transitions are logged/audited without secrets.
- [ ] Health distinguishes degraded vs unavailable.
- [ ] Metrics/log labels avoid unbounded cardinality.
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
- **Behavior guaranteed after P0172**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.