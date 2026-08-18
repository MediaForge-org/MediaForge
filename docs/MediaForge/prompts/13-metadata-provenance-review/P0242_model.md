# P0242 — Metadata vault, provenance, matching and review center: model

**Track:** 13-metadata-provenance-review  
**Priority:** P0  
**Prompt position in track:** 2/20  
**Depends on:** P0241

## Objective

Define or refine the domain model and invariants for this subsystem.

This is a deliberately narrow step inside **Metadata vault, provenance, matching and review center**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/modules/adult-source-vault-and-local-provenance.md`
- `docs/MediaForge/modules/books-ebooks-and-persistent-metadata.md`
- `docs/MediaForge/architecture/localization-and-professional-translation.md`
- `docs/MediaForge/modules/acquisition-automation-and-postprocessing.md`
- `docs/MediaForge/adr/0026-localization-and-translation.md`
- `docs/MediaForge/adr/0027-acquisition-blueprint-processing-dag.md`
- `docs/MediaForge/MediaForge_Master_Engineering.md`
- `docs/MediaForge/modules/adult-lineage-and-catalog.md`
- `docs/MediaForge/ui-ux/FEATURE_SCREEN_SPECIFICATIONS.md`

Inspect these source paths/symbol neighborhoods first:
- `apps/server/app/Domain/Metadata`
- `apps/server/app/Domain/Matching`
- `apps/server/app/Domain/Reviews`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/69_localization_translation_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/15_metadata_matching_workbench.png`
- `docs/MediaForge/ui-ux/reference-expanded/20_provenance_inspector.png`
- `docs/MediaForge/ui-ux/reference-expanded/35_adult_metadata_provenance_date_conflict.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Every important field should be traceable to evidence/source/observation where practical. Manual overrides must survive automated syncs.


## Mandatory target additions — 2026-08-17

- Preserve original and translated metadata separately with field-level provenance; machine-translated fields may later be superseded by authoritative locale metadata.
- Acquisition provenance records source/decision/post-processing without storing secrets.

## Mandatory target additions — 2026-08-18 — books and persistent metadata

- Treat Audiobookshelf, embedded ebook metadata, sidecars, providers and manual edits as provenance-bearing source facts. Later empty/partial refreshes must not erase previously retained facts.
- Books need edition-aware metadata/provenance and manual lock semantics.

## Mandatory target additions — 2026-08-18 — adult source vault

- Metadata Vault must retain historical Adult source facts even if TPDB/StashDB/official sources later omit or remove the scene; Local Filename/Local Curated values are first-class provenance.
- Source disappearance is not canonical scene deletion, and manual locks are not silently overwritten.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Metadata vault, provenance, matching and review center** and the current focus **model**.
2. Keep these subsystem deliverables in view: field observations, source authority, manual overrides, review queue.
3. Define or refine the domain model and invariants for this subsystem.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

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

- [ ] All new concepts have explicit ownership and invariants.
- [ ] Names/types avoid coupling canonical identity to provider/engine IDs.
- [ ] Schema/API implications are documented before implementation proceeds.
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
- **Behavior guaranteed after P0242**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
