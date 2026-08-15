# Governance module boundaries — Track 01 (governance, repository audit and execution discipline)

Status: **P0003 output**. Documentation only — this track does not implement product features
(see `GLOBAL_RULES_SHORT.md` and each prompt's *Subsystem-specific rule*). No product code, schema
or API was changed to produce this file. Builds on [[GOVERNANCE_DOMAIN_MODEL.md]] (P0002).

## Module boundaries and ownership

| Layer | Owns | Track 01 may | Track 01 may not |
| --- | --- | --- | --- |
| Prompt-system metadata (`docs/MediaForge/prompts/`) | `Track`, `NumberedPrompt`, `Dependency`, `Priority`, execution guardrails | Read, audit, model, report on | Silently rewrite scheduling data as a side effect of an unrelated prompt |
| Product specification (`docs/MediaForge/*.md`, `architecture/`, `modules/`, `adr/`) | The target architecture and product decisions | Read the smallest authoritative section when a concrete ambiguity requires it (`CONTEXT_ROUTING.md`) | Fork or restate it; Track 01 must reference, never duplicate, product decisions |
| Product code (`app/`, `resources/`, `routes/`, `database/`, `tests/`) | Real implemented behavior, proven by `CURRENT_PHASE.md` and the local gates | Inspect (baseline inventory) | Modify — this track's *Subsystem-specific rule* forbids implementing product features |
| ADRs (`docs/MediaForge/adr/`) | Durable architecture decisions | Reference by number | Introduce a competing decision record for a topic an existing ADR already owns |

**Dependency direction:** `ExecutionGuardrail` and `GOVERNANCE_DOMAIN_MODEL.md` flow one way — from
Track 01 outward, informing every other track's execution. No other track's product code or
persisted state may be read back into Track 01's own rules; Track 01's guardrails must stay derivable
from `GLOBAL_RULES_SHORT.md`/`CLAUDE_EXECUTION_PROTOCOL.md`/`PRODUCT_DECISIONS_2026-08.md` alone,
never from a specific engine's implementation detail. This satisfies the prompt's explicit subsystem
rule: *"Keep canonical MediaForge contracts free of engine-specific identifiers and implementation
details."*

**No cross-engine coupling applies vacuously here:** Track 01 owns no database table and talks to no
engine (Jellyfin/Stash/Audiobookshelf), so the global rule "no direct cross-engine DB coupling" has
no surface to violate at this step. It becomes load-bearing starting with tracks that own persistence
or engine adapters (08, 26, 27, 28).

## Interfaces (minimal, capability-oriented)

The only way another track or a future prompt is meant to consume Track 01's output:

1. **`GLOBAL_RULES_SHORT.md`** — the guardrail interface. Every prompt reads it first, unconditionally.
2. **`CLAUDE_EXECUTION_PROTOCOL.md`** — the process interface. Defines the step sequence every prompt follows.
3. **`TRACK_INDEX.md` / `PROMPT_ORDER.md` / `PROMPT_CATALOG.json`** — the scheduling interface (structured, machine-checkable; see *Testable dependency direction* below).
4. **`GOVERNANCE_DOMAIN_MODEL.md`** — the vocabulary interface, opt-in via `CONTEXT_ROUTING.md`, not preloaded by default.

Audit reports and risk-register findings produced by individual prompt runs (e.g. this file, P0001's
response) are **not** an interface other automated prompts read — they are addressed to the human
operator. This keeps the interface surface minimal: a later track cannot silently start depending on
the prose of a specific past audit response.

## Testable dependency direction — verified now, not deferred

`PROMPT_CATALOG.json` is structured data, so "dependency direction is explicit and testable" was
checked directly rather than asserted:

```
720 prompts, 0 missing dependency targets, 0 self-dependencies.
Strongly-connected-component analysis (Tarjan) on the depends_on graph:
  1 cyclic component, size 140: P0441 … P0580
```

**Finding — persisted as `RISK_REGISTER.json#RISK-0001` (added by P0004), not fixed by this prompt:**
tracks 23–29 (`adult-analysis`,
`disc-iso`, `audio-enhancement`, `video-engine`, `adult-engine`, `audio-engine`,
`rust-media-tools` — all P2, all far behind `CURRENT_PHASE.md`) form one genuine dependency cycle:

- Every prompt in `23-adult-analysis` (P0441–P0460) declares `depends_on` including `P0580`
  (the gate prompt of `29-rust-media-tools`) — e.g. `P0442.depends_on = [P0441, P0440, P0580]`.
- Each of tracks 24–29 starts by depending on the *previous* track's last prompt — e.g.
  `P0461.depends_on = [P0460, P0240, P0580]` (`24-disc-iso` depends on `23-adult-analysis`'s last
  prompt, `P0460`).
- Chaining that "depends on previous track's last prompt" link forward through 24→25→26→27→28→29
  reaches `P0580`, which is exactly the node every prompt in `23-adult-analysis` also declares as a
  direct dependency — closing the loop back onto `P0441`.

Net effect: as declared, none of P0441–P0580 has a valid topological execution order. This is a data
defect in `PROMPT_CATALOG.json` (and the corresponding individual prompt files), not a code defect —
it affects only far-future P2 tracks, not the current V2 E baseline or the work between here and
`P0020`. It does, however, gate `P0020` itself: `RISK_REGISTER.json#RISK-0001.gate_condition` records
that Track 01's own gate prompt must not pass while this entry's `status` is `open`, since a prompt
system whose own dependency graph contains a cycle cannot certify itself as a trustworthy execution
schedule. Per this prompt's non-goals ("do not redesign or refactor unrelated subsystems", "do not read
the full repository documentation tree") and the user's explicit instruction not to pull forward
later-prompt decisions, **this cycle is reported and persisted, not repaired, here.** Recommended smallest fix for
whoever owns that data later: drop the redundant per-prompt `P0580` dependency in tracks 23–25 (the
track-to-track chain already sequences them after `29-rust-media-tools` transitively is *not* true
today — more likely the intended fix is the reverse: drop the track-initial "depends on previous
track's last prompt" edges for 24–29 and rely solely on each track's explicit `P0580`/`P0240`-style
capability dependency, which is what actually expresses "this engine track needs Rust MediaTools and
the target monorepo, not the second I finish adult-analysis").

## Acceptance criteria check

- [x] Dependency direction is explicit and testable — verified by direct graph analysis above
      (tools: `python3`, stdlib `json`, Tarjan SCC — no new dependency added to the repo).
- [x] No direct cross-engine database coupling introduced — none exists in this track; stated as an
      explicit (currently vacuous) invariant above.
- [x] Interfaces are minimal and capability-oriented — enumerated above; audit prose explicitly
      excluded from the interface surface.
- [x] Existing relevant behavior outside this prompt remains working — no product file touched.
- [x] New code follows target responsibility boundaries — no code added; boundaries are documentation.
- [x] No secrets/private user data added — none.
