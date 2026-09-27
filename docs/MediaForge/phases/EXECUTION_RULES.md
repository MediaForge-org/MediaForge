# MediaForge milestone execution rules

## Authority

Execution order/status:
1. `docs/MediaForge/phases/CURRENT_PHASE.md`
2. `docs/MediaForge/phases/PHASE_CATALOG.json`
3. `python3 tools/phases/show_phase.py Mx.y`

Product/architecture semantics remain in the normal MediaForge architecture/module/ADR documents. `CURRENT_PHASE.md` remains the truth for what is actually implemented.

Legacy `Pxxxx` files are not an active execution authority after 2026-09-27.

## Context budget

Before a unit:
1. `git status --short`;
2. use `graphify-out/GRAPH_REPORT.md` first for code navigation when present;
3. run `show_phase.py` for the authorized unit;
4. read only displayed required docs;
5. inspect only displayed source hints plus directly called/imported code needed for the change.

Do not recursively read the whole documentation tree or the 720 legacy prompts.

## Granularity

- simple units may be bundled up to `recommended_bundle_max`;
- hard/critical units default to one at a time;
- bundles must be adjacent and dependencies satisfied;
- if a unit proves too large, split it into `Mx.y.1`, `Mx.y.2`, ... before implementing;
- never split merely for symmetry.

Each unit is a complete work package: audit/model/boundaries/persistence/application/API/UI/security/tests/docs are included as relevant. Do not recreate the old fixed lifecycle as separate prompts unless the work genuinely requires a split.

## Tests

`TEST_STRATEGY.md` is binding. Every behavior change gets focused automated tests. High-risk units require negative/failure-path tests. Milestone gates run broader relevant suites. Never claim a command passed unless it actually ran.

## Preserve working code

V1 and V2 A–E are real baseline. Migrate incrementally; do not rebuild or delete working functionality merely because a target milestone describes a cleaner end-state.

## Core architecture

- one MediaForge product/UI;
- React 19 + TypeScript; React Router Framework Mode target, Inertia transitional;
- Laravel control plane/domain/BFF;
- PostgreSQL owns MediaForge canonical state;
- external IDs are mappings;
- Jellyfin/Audiobookshelf/Scene Tracker are separate services behind adapters;
- no direct cross-service DB access;
- no mandatory deep fork without a new evidence-backed ADR;
- originals stay untouched unless explicitly authorized;
- Adult Zero Leak is server-side;
- AI/3D optional, never a core hard dependency;
- large artifacts live outside ordinary PostgreSQL BLOB storage.

## Git safety

If unrelated changes overlap the unit, stop. Never silently stash/reset/delete user work. No commit/push/tag/release without explicit authorization.

## Completion report

Report units completed, changed files, schema/contracts, security/failure semantics, exact tests/results, performance where relevant, known limits, scope check, working-tree state and next eligible unit.
