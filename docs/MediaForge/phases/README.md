# MediaForge milestone execution system

This directory is the active execution layer for MediaForge.

Use `M1.1`, `M2.4`, `M8.7`, etc. instead of the old `P0001..P0720` workflow.

The old plan was useful for coverage but forced every subsystem through the same 20 tiny steps. The new system keeps the discipline while matching granularity to real complexity: small milestones can have only a few units, while difficult areas are split much more deeply.

## Start

1. Read `CURRENT_PHASE.md`.
2. Read `EXECUTION_RULES.md`.
3. Run `python3 tools/phases/show_phase.py <Mx.y>`.
4. Read only the listed context/source neighborhoods.
5. Implement + test that complete unit.
6. Continue only when the user authorized the next unit/bundle.

Files:
- `PHASE_INDEX.md` — human map.
- `PHASE_CATALOG.json` — machine plan/dependencies.
- `EXECUTION_RULES.md` — execution discipline.
- `TEST_STRATEGY.md` — mandatory test depth.
- `CURRENT_PHASE.md` — active unit.
- `IMPLEMENTED_BASELINE.md` — old detailed implementation status, copied at migration time.
- `LEGACY_TRACK_MAPPING.md` — proves all 36 old tracks are covered.
