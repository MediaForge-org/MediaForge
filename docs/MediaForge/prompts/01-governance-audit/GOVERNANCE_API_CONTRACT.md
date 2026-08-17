# Governance API/contract surface — Track 01 (governance, repository audit and execution discipline)

Status: **P0006 output**. Documentation and JSON Schema only — this track does not implement
product features (see `GLOBAL_RULES_SHORT.md` and each prompt's *Subsystem-specific rule*). This
document formalizes, and does not reimplement, the already-shipped P0005 tool
(`tools/prompts/check_dependency_graph.py`). No product code, database schema, route or Laravel
contract was changed to produce this file. Builds on [[GOVERNANCE_DOMAIN_MODEL.md]] (P0002),
[[GOVERNANCE_BOUNDARIES.md]] (P0003) and `RISK_REGISTER.json` (P0004).

## 1. Scope and what this is not

This is the stable contract for the local governance tooling that checks
`docs/MediaForge/prompts/PROMPT_CATALOG.json`'s dependency graph for consistency (missing
targets, self-dependencies, cycles) and cross-references cycles against `RISK_REGISTER.json`.

**It is not:**
- an HTTP/REST API — there is no route, controller or artisan command anywhere in `app/` or
  `routes/` related to this tooling, and this prompt does not add one (this track's subsystem rule
  forbids implementing product features);
- part of `docs/MediaForge/api/` — that tree (`conventions.md`, `endpoint-catalog.md`,
  `error-catalog.md`, `webhook-catalog.md`) is the real, normative MediaForge product REST API
  surface for external consumers (`/api/v1`, RFC 9457 errors, Sanctum tokens, ULIDs). This tooling
  has no route, no token, no ULID, and no relationship to that surface;
- a rewrite or extension of `check_dependency_graph.py` — the script and its test suite are
  unchanged by this prompt; this document only pins down the interface they already have.

## 2. Consumers

Two kinds of consumer exist today:
1. A human (or an agent, in a later prompt's execution) running the CLI directly:
   `python3 tools/prompts/check_dependency_graph.py`.
2. Python code importing the module directly, e.g.
   `from check_dependency_graph import check, load_catalog, load_risk_register` (run with
   `tools/prompts/` on `sys.path`, as `test_check_dependency_graph.py` already does).

There is no third consumer (no network client, no other language binding) as of this prompt.

## 3. CLI contract — `check_dependency_graph.py`

### 3.1 Invocation

```
python3 tools/prompts/check_dependency_graph.py
```

Takes no arguments. Reads `docs/MediaForge/prompts/PROMPT_CATALOG.json` and, if present,
`docs/MediaForge/prompts/01-governance-audit/RISK_REGISTER.json`, both resolved relative to the
repository root (`pathlib.Path(__file__).resolve().parents[2]`), not the current working
directory — so it may be invoked from anywhere in the checkout.

### 3.2 stdout contract

The report always starts with `"{prompt_count} prompts checked."`, followed by zero or more
itemized sections (only present when relevant defects exist), and always ends with exactly one of
these three trailer sentences:

| Trailer sentence | Meaning |
| --- | --- |
| `Result: clean.` | No missing targets, no self-dependencies, no cycles at all. |
| `Result: only already-tracked cycles present. Not a fresh failure.` | Every cycle found is fully covered by an open, range-matching `RISK_REGISTER.json` entry. |
| `Result: untracked structural defect(s) present.` | At least one missing target, self-dependency, or untracked cycle exists. |

**Stability promise:** the first line's prefix (`"{N} prompts checked."`) and the trailer sentence
(verbatim, one of the three above) are the stable, machine-checkable part of stdout. The itemized
`MISSING DEPENDENCY TARGETS`/`SELF-DEPENDENCIES`/`UNTRACKED CYCLE`/`Tracked cycle` lines in
between are informative for a human reading the report; their *presence given a defect* is
guaranteed, but their exact prose is not pinned as a parseable contract. A caller that needs
structured data should call `check()` directly (section 4.3) rather than parse stdout.

### 3.3 stderr contract

The only content ever written to stderr by the current implementation is a single line,
`f"error: {exc}"`, emitted when `load_catalog()` raises `CatalogError` (unreadable file, invalid
JSON, or a JSON value that is not an array). Nothing else is written to stderr.

### 3.4 Exit code contract

| Exit code | Constant | Meaning |
| --- | --- | --- |
| `0` | `EXIT_CLEAN` | No defects of any kind. |
| `1` | `EXIT_UNTRACKED_DEFECT` | A missing dependency target, a self-dependency, an untracked cycle, **or** a `CatalogError` (catalog unreadable/invalid) — all three collapse to the same exit code from the CLI. |
| `2` | `EXIT_ONLY_TRACKED_CYCLES` | Only already-tracked (open, risk-register-covered) cycles remain; no other defect. |

### 3.5 Stability promise

The exit-code table above and the three trailer sentences are the contract. Anything not listed
here (exact column widths, ordering beyond "sorted by prompt id", additional stdout lines for
future defect types) may change without that being treated as a breaking change to this contract,
unless this document is updated to add it.

## 4. Python programmatic contract

### 4.1 `load_catalog(path=CATALOG_PATH) -> list[dict]`

Reads and JSON-parses the catalog file. Raises `CatalogError` if the file cannot be read, is not
valid JSON, or does not parse to a JSON array.

### 4.2 `load_risk_register(path=RISK_REGISTER_PATH) -> dict | None`

Returns the parsed risk register, or `None` if the file does not exist at `path`. Never raises for
a missing file; a malformed-but-present file still raises the underlying `json.JSONDecodeError`
(not wrapped in `CatalogError` — this function only wraps *absence*, not malformed content).

### 4.3 `check(catalog, risk_register=None) -> dict`

Pure function, no I/O, no exceptions raised for data-quality problems in `catalog`. Its return
shape is the authoritative contract in
[`schemas/check_result.schema.json`](schemas/check_result.schema.json) — see that file for the
exact, closed (`additionalProperties: false`) key set and types. Summary: `prompt_count`,
`missing_targets`, `self_dependencies`, `tracked_cycles`, `untracked_cycles`,
`risk_register_present`, `exit_code`.

### 4.4 `format_report(result) -> str`

Pure function turning a `check()` result into the stdout text described in section 3.2.

### 4.5 `main(argv=None) -> int`

CLI entrypoint. Loads the catalog (catching `CatalogError` and printing to stderr per 3.3),
loads the risk register, calls `check()`, prints `format_report()`'s output, and returns the
result's `exit_code` (or `EXIT_UNTRACKED_DEFECT` on `CatalogError`).

### 4.6 Out of contract

`_strongly_connected_components`, `_prompt_number`, and `_cycle_is_tracked` are private
(leading-underscore) implementation details. They are not part of this contract and may change
signature or behavior at any time without notice.

## 5. Data contracts consumed

- **`PROMPT_CATALOG.json` entry** — [`schemas/prompt_catalog_entry.schema.json`](schemas/prompt_catalog_entry.schema.json).
- **`RISK_REGISTER.json`** — [`schemas/risk_register.schema.json`](schemas/risk_register.schema.json).

Both schemas describe only the fields `check_dependency_graph.py` actually reads (`id`,
`depends_on` for the catalog entry; `schema_version`, `entries[].status`,
`entries[].affected_prompt_range` for the risk register) and use `additionalProperties: true`
throughout, so the full human-authored records — carrying track metadata, severity, evidence,
`gate_condition`, `resolution`, and so on — remain valid without this contract having to
speculatively describe fields nothing here reads.

## 6. Identity rule (canonical MediaForge IDs)

Every identity that appears anywhere in this contract, its schemas, or the tool it describes is
either a prompt id matching `^P[0-9]{4}$` or a risk id matching `^RISK-[0-9]{4}$`. No engine id,
provider id, ULID, or database primary key appears anywhere in this surface — there is nothing
here to couple to one, since this track owns no database table (see
[[GOVERNANCE_BOUNDARIES.md]] §"No cross-engine coupling applies vacuously here").

## 7. Error/validation-response consistency rule

One consistent three-tier model, used identically by both the CLI and programmatic contracts:

1. **`CatalogError`** — raised by `load_catalog()` only for structurally invalid input (unreadable
   file, invalid JSON, JSON that isn't an array). This is the only exception this contract raises.
2. **Structured result** — `check()` never raises for graph-*shape* defects. A missing dependency
   target, a self-dependency, or a cycle is not an exception; it is data in the returned dict,
   summarized by `exit_code`. Callers that want defect details read the dict's fields directly.
3. **CLI translation** — `main()` converts (1) into a one-line stderr message plus
   `EXIT_UNTRACKED_DEFECT`, and (2) into the stdout report plus the result's own `exit_code`. Both
   paths use the same three exit codes from section 3.4; there is no separate error-code space.

## 8. Versioning / change policy for this contract

- **Additive is non-breaking:** a new key in `check()`'s result, a new field in either data file,
  or a new CLI stdout line does not break this contract as long as every key/behavior documented
  here still holds.
- **Breaking changes** — removing or renaming a documented key, changing what an exit code means,
  or changing when `CatalogError` is raised — require updating this file in the same change.
- This contract tracks `RISK_REGISTER.json`'s own `schema_version` field (currently `1`) for the
  risk-register shape, and the named key set of `check()`'s return dict for the Python contract.
  This is deliberately lighter-weight than `docs/MediaForge/api/conventions.md`'s v1/v2
  path-versioning scheme: this is a single, low-traffic, local file — not a network API with
  external consumers to avoid breaking.

## 9. Explicit non-goals / out of scope

Per this prompt's own non-goals, plus what this contract intentionally does not add:
- No HTTP/network surface of any kind.
- No authentication/authorization model — there is nothing here to authenticate against (a local,
  offline, read-only script over files already in the git working tree).
- No relation to, or entry in, `docs/MediaForge/api/*` (the real product API surface).
- No new CLI behavior — in particular, no `--json` output flag or any other flag. This document
  describes what exists today; it does not propose or add new behavior.
- Do not read the full repository documentation tree, do not start P0007, do not redesign or
  refactor unrelated subsystems, do not push/tag/publish a release (this prompt's own non-goals).

## 10. Verification performed for this prompt

- `python3 -m json.tool` on all three schema files under `schemas/` — all valid JSON.
- A throwaway, uncommitted conformance script (run from the scratchpad, not added to the repo)
  re-implemented each schema's constraints by hand against the real, current
  `RISK_REGISTER.json`, several `PROMPT_CATALOG.json` records, and a live call to
  `check(load_catalog(), load_risk_register())` — confirming the real `check()` result's key set
  and types match `schemas/check_result.schema.json` exactly, and that real data conforms to the
  other two schemas. `jsonschema` is not installed in this environment and was deliberately not
  added as a dependency for a docs-only prompt; see the completion response for exact commands and
  results.
- The existing, unmodified test suite (`cd tools/prompts && python3 -m unittest
  test_check_dependency_graph -v`) was re-run to confirm this prompt changed no behavior — see the
  completion response for the exact result.
