# Governance domain model — Track 01 (governance, repository audit and execution discipline)

Status: **P0002 output**. Documentation only — this track does not implement product features
(see `GLOBAL_RULES_SHORT.md` and each prompt's *Subsystem-specific rule*). No code, schema or API
was changed to produce this file.

This defines the concepts this track reasons about and their invariants, so P0003–P0020 (boundaries,
persistence, application, API, frontend, validation, security, jobs, realtime, observability,
migration, fixtures, tests, docs, gate) have a stable vocabulary instead of re-deriving it each time.

## Entities

- **Track** — one of the 36 subsystems in `TRACK_INDEX.md`. Owns a contiguous `P00xx`–`P00yy` range
  and a fixed 20-step lifecycle (audit → model → … → gate). Identity: the folder name
  (e.g. `01-governance-audit`), not the numeric range, which is derivable but not canonical.
- **NumberedPrompt** — one `P00xx` unit of work. Owns exactly one lifecycle step of exactly one
  track. Fields that matter: `id`, `track`, `position (1–20)`, `priority (P0/P1/P2)`,
  `depends_on`, `required_reads`, `source_paths`, `non_goals`, `acceptance_criteria`.
- **Dependency** — a directed edge `NumberedPrompt → NumberedPrompt` (currently: prior prompt in
  the same track; cross-track dependencies are not yet declared anywhere and must not be assumed).
- **Priority** — `P0` (architecture/data-model decisions that should be correct early), `P1` (core
  product functionality once foundations are stable), `P2` (advanced/expensive, non-blocking).
  Priority orders *importance*, not *execution order* — it never overrides `Dependency` or
  `CURRENT_PHASE.md`.
- **Phase** — the real, code-and-tests-proven state of the product, recorded in `CURRENT_PHASE.md`.
  Exactly one phase is current at a time. A track/prompt can be written for a later phase without
  that phase being current.
- **ExecutionGuardrail** — a standing constraint that applies to every prompt regardless of track,
  sourced from `GLOBAL_RULES_SHORT.md` and `CLAUDE_EXECUTION_PROTOCOL.md` (e.g. run one prompt at a
  time, no push/tag/commit without explicit user instruction, no reading the full doc tree by
  default). Guardrails are never overridden by an individual prompt's content.
- **RiskRegisterEntry** — a named blocker, unknown or fragility surfaced by an `audit` step
  (P0001-shaped prompts) together with the exact files/subsystems it affects. Lives in that audit's
  response, not in a separate persisted store, until/unless a later prompt in this track gives it
  one (not decided by P0002).
- **BaselineInventory** — the audit step's snapshot of what actually exists (stack, packages,
  gates, test counts) as opposed to what the docs describe. Superseded by the next audit; never
  patched in place.

## Invariants

1. A `NumberedPrompt` executes only after every prompt it `depends_on` is complete. P0002 depends on
   P0001 (complete, see prior response).
2. `CURRENT_PHASE.md` is the only authority on what is actually implemented; a track/prompt document
   describing a later phase is a plan, not a claim of current behavior.
3. No `NumberedPrompt` in this track authorizes push/tag/release, and none authorizes a commit unless
   it says so explicitly and the user has also asked for it — the stricter of the two always wins.
4. A `RiskRegisterEntry` must name the exact affected files/subsystem; a risk with no concrete blast
   radius is not yet a risk register entry, it is a note.
5. `ExecutionGuardrail`s bind every track uniformly; a track-specific "Subsystem-specific rule" may
   narrow scope further (e.g. "do not implement product features" here) but may never loosen a
   global guardrail.
6. Canonical product identity (media items, editions, etc.) is out of scope for this track's model —
   Track 01 governs *how work is executed*, not the product's data model. The generic acceptance
   criterion "avoid coupling canonical identity to provider/engine IDs" (shared boilerplate across
   all `*_model.md` prompts) has no applicable subject here; it becomes load-bearing starting with
   tracks that own persistence (e.g. Track 08, `08-postgres-core`).

## Ownership

- `TRACK_INDEX.md`, `PROMPT_ORDER.md`, `PROMPT_CATALOG.json` — own `Track`, `NumberedPrompt`,
  `Dependency`, `Priority`.
- `CURRENT_PHASE.md` — owns `Phase`.
- `GLOBAL_RULES_SHORT.md`, `CLAUDE_EXECUTION_PROTOCOL.md` — own `ExecutionGuardrail`.
- **`RISK_REGISTER.json`** (added by P0004) — owns any `RiskRegisterEntry` that must survive past the
  single audit response that found it, i.e. one with a concrete `gate_condition` blocking a later
  prompt. An audit response may still surface a risk inline; it graduates into the register only
  when it needs to outlive that response. `BaselineInventory` remains unpersisted (see Schema/API
  implications) — each audit step's snapshot is superseded by the next, so there is nothing to keep.

## Schema/API implications

None in the product database. Track 01 has no Laravel model, migration, route or contract, and P0004
deliberately did not add one: the only durable governance state identified so far (the P0003 dependency
cycle finding) is static, has no concurrent-write or query-at-runtime requirement, and is fully served
by a versioned, git-diffable JSON file (`RISK_REGISTER.json`) — introducing a database table for it
would be exactly the kind of placeholder persistence this track's own guardrails warn against. If a
future need appears that genuinely requires runtime querying/writing of governance state from the
application (not just from a human or an agent reading the repo), that would be a new, explicitly
justified decision for whichever prompt first needs it — not assumed here.
