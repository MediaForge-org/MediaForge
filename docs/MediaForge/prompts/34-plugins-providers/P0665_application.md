# P0665 — Plugin SDK, metadata providers and automation extensions: application

**Track:** 34-plugins-providers  
**Priority:** P2  
**Prompt position in track:** 5/20  
**Depends on:** P0664

## Objective

Implement application services, commands/queries or orchestration logic.

This is a deliberately narrow step inside **Plugin SDK, metadata providers and automation extensions**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/modules/adult-source-vault-and-local-provenance.md`
- `docs/MediaForge/modules/books-ebooks-and-persistent-metadata.md`
- `docs/MediaForge/architecture/managed-upstreams-and-product-surface.md`
- `docs/MediaForge/modules/acquisition-automation-and-postprocessing.md`
- `docs/MediaForge/architecture/localization-and-professional-translation.md`
- `docs/MediaForge/adr/0025-managed-upstream-backends.md`
- `docs/MediaForge/adr/0026-localization-and-translation.md`
- `docs/MediaForge/adr/0027-acquisition-blueprint-processing-dag.md`
- `docs/MediaForge/MediaForge_Master_Engineering.md`
- `docs/MediaForge/architecture/engine-contracts.md`
- `docs/MediaForge/modules/acquisition-center.md`
- `docs/MediaForge/modules/plugin-theme-sdk.md`

Inspect these source paths/symbol neighborhoods first:
- `packages/sdk`
- `apps/server/app/Infrastructure/Providers`
- `plugins`
- `packages/plugin-sdk`
- `packages/theme-sdk`
- `apps/web/src/extensions`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/68_backend_capabilities_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/69_localization_translation_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/46_plugins_themes_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/55_plugin_marketplace.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

**2026-08-16 architecture rule:** Plugin types, manifests, compatibility and permissions are explicit. Theme SDK uses tokens/scoped CSS; global Custom CSS is Advanced opt-in.


Extensions run through versioned capabilities and permissions. Do not let plugins write arbitrary core tables or bypass privacy policies.


## Mandatory target additions — 2026-08-17

- Provider plugins may extend indexer/search, Browser Companion, automation and TranslationProvider capabilities without bypassing MediaForge security/provenance.
- Plugin locale bundles integrate with the same localisation message schema and quality checks.

## Mandatory target additions — 2026-08-18 — books and persistent metadata

- Book metadata provider plugins append provenance-bearing facts and must preserve history on partial/empty refresh; provider ids remain mappings, not identity.

## Mandatory target additions — 2026-08-18 — adult source vault

- Provider contracts support Adult historical/public metadata sources, browser-companion captures and local-filename provenance; recovery preserves observation time and never bypasses access controls.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Plugin SDK, metadata providers and automation extensions** and the current focus **application**.
2. Keep these subsystem deliverables in view: plugin manifest, capabilities/permissions, provider adapter SDK, sandbox/versioning.
3. Implement application services, commands/queries or orchestration logic.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- plugin manifest
- capabilities/permissions
- provider adapter SDK
- sandbox/versioning

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.
- Do not pull this advanced feature ahead of the usable-core/roadmap gates merely because the code is interesting.

## Acceptance criteria

- [ ] Business behavior is implemented outside controllers/UI components.
- [ ] Operations are idempotent where retries are possible.
- [ ] Errors are domain-specific and deterministic.
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
- **Behavior guaranteed after P0665**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.