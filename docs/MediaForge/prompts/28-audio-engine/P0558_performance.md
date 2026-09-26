# P0558 — Audiobookshelf external audio/book/playback adapter and compatibility: performance

**Track:** 28-audio-engine  
**Priority:** P2  
**Prompt position in track:** 18/20  
**Depends on:** P0557, P0140, P0120

## Objective

Profile and harden the subsystem for realistic library sizes and failure conditions.

This is a deliberately narrow step inside **Audiobookshelf external audio/book/playback adapter and compatibility**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/external-specialist-services-and-adapters.md`
- `docs/MediaForge/adr/0028-external-specialist-services-via-adapters.md`
- `docs/MediaForge/architecture/engine-contracts.md`
- `docs/MediaForge/architecture/postgresql-source-of-truth.md`
- `docs/MediaForge/modules/audiobook-chapters-and-storage.md`
- `docs/MediaForge/modules/books-ebooks-and-persistent-metadata.md`
- `docs/MediaForge/architecture/player-audio-loudness-and-device-policy.md`

Inspect these source paths/symbol neighborhoods first:
- `app/Connectors/Audiobookshelf`
- `app/Connectors/Sdk`
- `packages/contracts`
- `apps/server/app/Domain/Audiobooks`
- `platform/integrations`
- `deploy/dev/docker-compose.yml`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/68_backend_capabilities_acquisition_overview.png`
- None for this prompt unless a changed screen directly requires an existing design-system reference.

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Treat Audiobookshelf as an independently updateable external specialist service. Integrate through its supported/versioned API behind MediaForge contracts; never query/migrate the ABS internal database and do not copy the ABS source tree by default.


## Mandatory target architecture — 2026-09-27

- Detect Audiobookshelf version/capabilities and maintain an explicit supported-version compatibility range.
- Expand the existing Audiobookshelf adapter rather than creating a parallel integration stack.
- Synchronize useful audiobook/book/podcast metadata into MediaForge source facts/projections so rename, move, ABS rescan or ABS-local-ID changes do not erase retained metadata.
- When the prompt focus reaches it, integrate playback, chapters and listening progress through supported ABS APIs with explicit ownership/conflict semantics.
- Keep MediaForge PostgreSQL canonical for MediaForge identity, Work/Edition/File, provenance, search/review and MediaForge-owned reading/listening state.
- No Audiobookshelf source import, fork cutover or direct ABS DB access is required.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Audiobookshelf external audio/book/playback adapter and compatibility** and the current focus **performance**.
2. Keep these subsystem deliverables in view: Audiobookshelf API adapter boundary, persistent catalog/metadata projection, chapter/playback/progress bridge, compatibility/sync workflow.
3. Profile and harden the subsystem for realistic library sizes and failure conditions.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- Audiobookshelf API adapter boundary
- MediaForge canonical mapping/source-fact projection
- chapter/playback/progress bridge
- compatibility/sync/offline workflow

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.
- Do not pull this advanced feature ahead of the usable-core/roadmap gates merely because the code is interesting.

## Acceptance criteria

- [ ] A representative benchmark/load fixture exists.
- [ ] No optimization is accepted without before/after evidence.
- [ ] Memory, I/O and query behavior are considered, not only wall-clock time.
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
- **Behavior guaranteed after P0558**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
