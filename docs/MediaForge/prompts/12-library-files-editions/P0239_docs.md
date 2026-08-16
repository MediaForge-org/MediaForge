# P0239 — Library, files, editions and storage model: docs

**Track:** 12-library-files-editions  
**Priority:** P0  
**Prompt position in track:** 19/20  
**Depends on:** P0238

## Objective

Update only the documentation/ADR sections made true by the implementation.

This is a deliberately narrow step inside **Library, files, editions and storage model**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/modules/acquisition-automation-and-postprocessing.md`
- `docs/MediaForge/architecture/localization-and-professional-translation.md`
- `docs/MediaForge/adr/0027-acquisition-blueprint-processing-dag.md`
- `docs/MediaForge/modules/media-editions-and-lineage.md`
- `docs/MediaForge/architecture/postgresql-source-of-truth.md`
- `docs/MediaForge/MediaForge_Master_Engineering.md`

Inspect these source paths/symbol neighborhoods first:
- `apps/server/app/Domain/Library`
- `apps/server/app/Domain/Media`
- `apps/web/src/features/library`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/68_backend_capabilities_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/69_localization_translation_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/13_library_settings_and_parser.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Keep Work/MediaItem, Edition and File separate. Never let physical filesystem layout become canonical media identity.


## Mandatory target additions — 2026-08-17

- Final file/folder naming is owned by MediaForge and follows explicit templates/policies; acquisition source names remain provenance, not canonical identity.
- Torrent-safe imports must preserve seeding through hardlink/reflink/copy or qBittorrent-aware rename flows.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Library, files, editions and storage model** and the current focus **docs**.
2. Keep these subsystem deliverables in view: library roots, MediaItem/Edition/File relations, path mappings, storage actions.
3. Update only the documentation/ADR sections made true by the implementation.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- library roots
- MediaItem/Edition/File relations
- path mappings
- storage actions

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] Docs describe only implemented truth.
- [ ] Current-phase status is not advanced unless all gate requirements are met.
- [ ] Cross-links point to the authoritative spec rather than duplicating conflicting prose.
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
- **Behavior guaranteed after P0239**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
