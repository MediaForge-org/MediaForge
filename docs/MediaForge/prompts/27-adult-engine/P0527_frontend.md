# P0527 — Scene Tracker metadata integration, private scene domain and Jellyfin playback mapping: frontend

**Track:** 27-adult-engine  
**Priority:** P2  
**Prompt position in track:** 7/20  
**Depends on:** P0526, P0140, P0120

## Objective

Implement the first frontend integration surface without redesigning unrelated screens.

This is a deliberately narrow step inside **Scene Tracker metadata integration, private scene domain and Jellyfin playback mapping**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/external-specialist-services-and-adapters.md`
- `docs/MediaForge/adr/0028-external-specialist-services-via-adapters.md`
- `docs/MediaForge/modules/adult-engine-target.md`
- `docs/MediaForge/modules/adult-enhancement.md`
- `docs/MediaForge/architecture/postgresql-source-of-truth.md`
- `docs/MediaForge/architecture/engine-contracts.md`
- `docs/MediaForge/architecture/unified-application.md`

Inspect these source paths/symbol neighborhoods first:
- `app/Connectors/Sdk`
- `app/Connectors/Jellyfin`
- `app/Integrations/SceneTracker`
- `packages/contracts`
- `services/media-tools`
- `docs/MediaForge/modules/adult-source-vault-and-local-provenance.md`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/68_backend_capabilities_acquisition_overview.png`
- None for this prompt unless a changed screen directly requires an existing design-system reference.

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Treat Scene Tracker as a separate metadata/community service with its own database and Jellyfin as the preferred local scene playback/streaming service. MediaForge owns private-domain identity/provenance/review/privacy and integrates through versioned APIs only. Stash is optional, not a mandatory fork.


## Mandatory target architecture — 2026-09-27

- Build/extend a versioned Scene Tracker adapter for scenes, performers, studios, tags/taxonomy, source provenance and matching/community data.
- Map local playable scene media to Jellyfin through MediaForge IDs/external mappings; Jellyfin remains responsible for specialist playback/streaming runtime.
- Preserve Adult Zero Leak and keep all private-domain authorization/filtering server-side in MediaForge.
- Preserve local filename/local-curated/source-vault facts when Scene Tracker or another upstream changes/disappears.
- Never query Scene Tracker's PostgreSQL directly and do not migrate it into MediaForge.
- Do not import/fork Stash by default. A future Stash adapter requires an actual capability need, not historical architecture inertia.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Scene Tracker metadata integration, private scene domain and Jellyfin playback mapping** and the current focus **frontend**.
2. Keep these subsystem deliverables in view: Scene Tracker API adapter, canonical private-domain mapping/provenance, Jellyfin playback mapping, compatibility/privacy workflow.
3. Implement the first frontend integration surface without redesigning unrelated screens.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- Scene Tracker API adapter boundary
- canonical MediaForge scene/performer/studio mappings
- Jellyfin local playback mapping and optional provider adapters
- compatibility/sync/privacy workflow

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.
- Do not pull this advanced feature ahead of the usable-core/roadmap gates merely because the code is interesting.

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
- **Behavior guaranteed after P0527**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
