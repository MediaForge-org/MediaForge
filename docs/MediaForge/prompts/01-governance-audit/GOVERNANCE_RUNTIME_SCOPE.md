# Governance runtime scope — Track 01 (governance, repository audit and execution discipline)

Status: **P0010 output, extended by P0011 and P0012**. This file records the applicability decisions for the runtime-shaped
lifecycle steps of this track, applied to the governance tooling
(`tools/prompts/check_dependency_graph.py`). Builds on [[GOVERNANCE_API_CONTRACT.md]] and
[[GOVERNANCE_SECURITY.md]].

These steps share the same premise as P0004–P0007. The tool is a local, synchronous, read-only
check over two git-tracked files. It has no product feature and no end-user audience (see
[[GOVERNANCE_FRONTEND_SCOPE.md]]). A generic lifecycle criterion that has no real subject here is
therefore recorded as N/A with its reason. It is not turned into queue, event or metrics
infrastructure that later tracks (30 jobs, 35 observability) own.

## P0010 — jobs: synchronous by design (N/A)

**Decision.** Background jobs are not applicable. None is added: no Laravel job, Redis queue,
worker, scheduler, cron or systemd unit, and no job or checkpoint state.

**Why no async is needed.** A full run over the real 720-prompt catalog finishes in well under a
second. It is only started explicitly by a developer or an agent, and nothing waits on it in the
background. No work item needs to outlive the process.

| Job concern | Reality for this tool |
| --- | --- |
| Irreversible side effects | None. The tool writes no file, database, network or git state. Its only effects are stdout, stderr and the exit code. |
| Retry | Safe by construction: re-run the command. There is nothing to deduplicate, because a run commits nothing. |
| Duplicate / concurrent execution | Harmless. Concurrent runs only read, so they cannot interfere, and each prints its own report. |
| Restart mid-run | Nothing to resume. An interrupted run leaves no partial state, and the next run starts from scratch. |
| Checkpoints / progress persistence | Not needed. One pass costs less than a second, so a checkpoint would add state without saving work. |
| Failed-run state | Surfaced synchronously through the exit code and a stderr line (§3.3–§3.4 of the contract), and not persisted. See P0012 below for the health mapping. |

**Idempotency semantics.** Every run reads the *current* `PROMPT_CATALOG.json` and
`RISK_REGISTER.json` fresh. The result is a pure function of those two files: `check()` is pure, and
`format_report()` is deterministic because its output is sorted. The same input always gives
byte-identical stdout, stderr and exit code. Changed input gives the new result, never a cached
earlier one.

**Tests.** `RerunSemanticsTests` covers this at CLI level:
- two consecutive `main()` runs give identical output and exit codes, and leave the input directory
  byte-for-byte unchanged;
- a re-run after the input changes reflects the new input.

The pre-existing `test_idempotent_on_repeated_calls` covers the same property for `check()`.

**When this would change.** A background job would only be warranted by a future need for
governance state that is written at runtime, or for a check that is slow enough to run
asynchronously. That would be a new, explicitly justified decision, and it belongs to Track 30's
job infrastructure rather than a Track-01 one-off.

## P0011 — realtime: not applicable

**Decision.** Progress, event and realtime behavior is not applicable. None is added: no
WebSocket, SSE, broadcast channel, event broker, event schema or polling endpoint.

**Existing event semantics checked.** There are none to review. The tool emits no event, streams no
progress, and has no subscriber. Its whole output is one report, printed once at process exit.

| Realtime concern | Reality for this tool |
| --- | --- |
| Event schema versioning | No events exist. The only output is the CLI/Python contract, which is already versioned through [[GOVERNANCE_API_CONTRACT.md]] §8 and `schemas/check_result.schema.json`. |
| Reconnect / duplicate delivery | There is no connection to lose and no message to re-deliver. Running the command again is the "reconnect", and per P0010 it is idempotent and always returns a fresh, complete snapshot. |
| Progress updates | Not meaningful. A run takes about 0.08 s over the real catalog (measured while writing this section), so it finishes before a progress channel would help. |
| UI correct without realtime transport | There is no product UI ([[GOVERNANCE_FRONTEND_SCOPE.md]]), so there is no client-side state that could go stale. The terminal output is the complete state at the moment of the run. |

**When this would change.** Only if governance state gains a live consumer, such as a product
screen watching prompt execution. That consumer would use the shared realtime infrastructure of its
own track, not a Track-01 transport.

## P0012 — observability: CLI/Python signals are the observability surface

**Decision.** The existing CLI and Python contract is already the right observability surface for
a developer- or agent-run local check. No Prometheus, OpenTelemetry, log files, audit table or
dashboard is added. P0012 closes one real gap: before P0012 nothing tested `main()`'s
state-to-signal mapping. That mapping is now defined below and pinned by `HealthStateTests`.

### Health states

The stable machine signal is the exit code plus whether stdout and stderr are empty. Nobody needs
to parse the itemized prose (contract §3.2–§3.4).

| State | Condition | Exit | stdout | stderr |
| --- | --- | --- | --- | --- |
| **healthy** | no missing target, self-dependency or cycle | `0` | report ending `Result: clean.` | empty |
| **degraded** | only cycles covered by an `open` risk-register entry | `2` | report ending `Result: only already-tracked cycles present. Not a fresh failure.` | empty |
| **failed** | a missing target, self-dependency or untracked cycle | `1` | itemized report ending `Result: untracked structural defect(s) present.` | empty |
| **malformed** | an input exists but is not valid UTF-8 or JSON, or is structurally invalid (`CatalogError`/`RiskRegisterError`) | `1` | empty | one line `error: <path> is not valid …` or a record-level message |
| **unavailable** | the catalog cannot be read (missing, permission denied, is a directory) | `1` | empty | one line `error: cannot read <path>: …` |

How the states are told apart:
- **degraded vs unavailable** is decided by the exit code alone (`2` vs `1`).
- **failed vs malformed/unavailable** is decided by which stream is non-empty.
- **malformed vs unavailable** is decided by the stderr wording (`is not valid …` / record message vs
  `cannot read`). Programmatic callers get the same split from the exception type and message. The
  wording is diagnostic prose that the tests pin against accidental regression. It is not a new
  stable contract field (contract §3.3).

A **missing `RISK_REGISTER.json` is not "unavailable"**. The register is optional by contract, and
without it the run is *stricter*: every cycle counts as untracked. stdout states this explicitly
("No RISK_REGISTER.json in this checkout — …"). Programmatic callers read it from
`risk_register_present`.

### Reviewed signals

| Signal | Where it is observable |
| --- | --- |
| `prompt_count` | first stdout line `"{N} prompts checked."`, and `check()["prompt_count"]` |
| missing targets / self-dependencies / cycles | itemized stdout sections, only when present, plus the structured `check()` fields |
| risk-register presence | the stdout note when absent, and `risk_register_present` |
| clean / degraded / failed | trailer sentence and exit code |
| malformed / unavailable | one bounded stderr line and exit `1` (see [[GOVERNANCE_SECURITY.md]]) |

### Acceptance criteria disposition

| P0012 criterion | Disposition |
| --- | --- |
| Important state transitions are logged or audited without secrets | The tool has no persistent state to transition. A run's outcome is its state, and it is reported on stdout/stderr. Diagnostics never echo file content, and any echoed input value is escaped and at most 80 characters (P0009). No log file or audit table is added, because there is no long-running process whose history needs keeping. The git history of the two input files is the audit trail of governance state. |
| Health distinguishes degraded vs unavailable | Yes: exit `2` vs exit `1` with empty stdout and a `cannot read` stderr line. Pinned by `HealthStateTests`. |
| Metrics/log labels avoid unbounded cardinality | There are no metrics or labels. Report output is bounded by input size: one line per defect, where defects come from a bounded catalog, and every echoed input value is truncated. |
| No secrets/private data added | None. |

**When this would change.** Continuous monitoring would only be warranted if governance state gained
a long-running producer or a live consumer (see P0010/P0011). That would use Track 35's shared
observability stack, not a Track-01 exporter.
