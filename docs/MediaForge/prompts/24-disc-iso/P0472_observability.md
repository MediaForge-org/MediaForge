# P0472 — Disc, ISO, BDMV, VIDEO_TS and verified-only mapping: observability

**Track:** 24-disc-iso  
**Priority:** P2  
**Prompt position in track:** 12/20  
**Depends on:** P0471, P0240, P0580

## Objective

Add logs, metrics, audit events and health visibility for the subsystem.

This is a deliberately narrow step inside **Disc, ISO, BDMV, VIDEO_TS and verified-only mapping**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/modules/acquisition-automation-and-postprocessing.md`
- `docs/MediaForge/adr/0027-acquisition-blueprint-processing-dag.md`
- `docs/MediaForge/modules/disc-engine.md`
- `docs/MediaForge/modules/disc-verification-policy.md`
- `docs/MediaForge/architecture/polyglot-runtime-and-contracts.md`

Inspect these source paths/symbol neighborhoods first:
- `services/media-tools`
- `apps/server/app/Domain/Disc`
- `apps/web/src/features/discs`

### UI references for this prompt
- `docs/MediaForge/ui-ux/reference-expanded/68_backend_capabilities_acquisition_overview.png`
- `docs/MediaForge/ui-ux/reference-expanded/19_disc_exact_verification.png`

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

No confidence-only episode mapping. Automated episode identity requires verified evidence; otherwise keep the mapping unresolved. Do not implement DRM bypass.


## Mandatory target additions — 2026-08-17

- For authorised disc/ISO inputs, verified mapping may branch into episode/main-feature/extra remux operations and optional derived transcode profiles.
- MKVToolNix-style remux and FFmpeg-style transcoding are separate stages; ambiguous title/playlist mapping goes to Review, never guesswork.
- Minimum derived video profile families are H.264/AVC, H.265/HEVC and AV1, with multiple selectable outputs allowed.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **Disc, ISO, BDMV, VIDEO_TS and verified-only mapping** and the current focus **observability**.
2. Keep these subsystem deliverables in view: disc structure model, playlist analysis, external evidence providers, verified-only mapping.
3. Add logs, metrics, audit events and health visibility for the subsystem.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- disc structure model
- playlist analysis
- external evidence providers
- verified-only mapping

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.
- Do not pull this advanced feature ahead of the usable-core/roadmap gates merely because the code is interesting.

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
- **Behavior guaranteed after P0472**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
