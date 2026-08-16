# P0292 — SABnzbd/qBittorrent intake, staging and import sandbox: observability

**Track:** 15-download-import  
**Priority:** P1  
**Prompt position in track:** 12/20  
**Depends on:** P0291, P0280

## Objective

Add logs, metrics, audit events and health visibility for the subsystem.

This is a deliberately narrow step inside **SABnzbd/qBittorrent intake, staging and import sandbox**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/managed-upstreams-and-product-surface.md`
- `docs/MediaForge/modules/acquisition-automation-and-postprocessing.md`
- `docs/MediaForge/adr/0025-managed-upstream-backends.md`
- `docs/MediaForge/adr/0027-acquisition-blueprint-processing-dag.md`
- `docs/MediaForge/modules/acquisition-center.md`
- `docs/MediaForge/modules/media-editions-and-lineage.md`
- `docs/MediaForge/adr/0017-acquisition-and-staging.md`

Inspect these source paths/symbol neighborhoods first:
- `apps/server/app/Infrastructure/DownloadClients`
- `apps/server/app/Domain/Acquisition`
- `apps/web/src/features/acquisition`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/68_backend_capabilities_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/31_manual_download_intake.png`
- `docs/MediaForge/ui-ux/reference-expanded/32_import_sandbox_upgrade_review.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

Downloads land in staging first. No completed download should be moved directly into a library without a deterministic import plan, validation and provenance record.


## Mandatory target additions — 2026-08-17

- SABnzbd handles Usenet download/repair/unpack; MediaForge owns classification, final naming, provenance and library placement.
- qBittorrent seed payload is preserved by default; prefer hardlink/reflink/copy for renamed library views, or rename active payload through qBittorrent APIs only.
- Implement resumable post-processing stages rather than one opaque script; custom scripts are explicit contract-bound DAG nodes.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **SABnzbd/qBittorrent intake, staging and import sandbox** and the current focus **observability**.
2. Keep these subsystem deliverables in view: download client adapters, staging layout, ImportCandidate/Plan, quality upgrade decision.
3. Add logs, metrics, audit events and health visibility for the subsystem.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- download client adapters
- staging layout
- ImportCandidate/Plan
- quality upgrade decision

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
- **Behavior guaranteed after P0292**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
