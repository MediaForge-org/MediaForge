# Governance API/contract surface — Track 01 (governance, repository audit and execution discipline)

Status: **P0006 output, extended by P0008**. Documentation and JSON Schema only — this track does
not implement product features (see `GLOBAL_RULES_SHORT.md` and each prompt's *Subsystem-specific
rule*). This document formalizes the already-shipped P0005 tool
(`tools/prompts/check_dependency_graph.py`); P0008 added deterministic structural validation to
that same tool (still stdlib-only, still no HTTP/product surface) and this document was updated in
the same change, per this contract's own versioning policy (§8). No product code, database schema,
route or Laravel contract was changed to produce this file. Builds on
[[GOVERNANCE_DOMAIN_MODEL.md]] (P0002), [[GOVERNANCE_BOUNDARIES.md]] (P0003) and
`RISK_REGISTER.json` (P0004).

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
`f"error: {exc}"`, emitted when `load_catalog()` raises `CatalogError` **or** `load_risk_register()`
raises `RiskRegisterError` (added P0008 — see §4.2). Nothing else is written to stderr.

### 3.4 Exit code contract

| Exit code | Constant | Meaning |
| --- | --- | --- |
| `0` | `EXIT_CLEAN` | No defects of any kind. |
| `1` | `EXIT_UNTRACKED_DEFECT` | A missing dependency target, a self-dependency, an untracked cycle, a `CatalogError`, **or** (added P0008) a `RiskRegisterError` — all collapse to the same exit code from the CLI. This is a deliberate, documented choice, not an oversight: this contract does not promise a caller can tell "malformed governance input" apart from "valid input with an untracked defect" *by exit code alone* — only by reading stderr (empty vs. one `error:` line) or by calling `load_catalog`/`load_risk_register`/`check` directly and catching the specific exception. |
| `2` | `EXIT_ONLY_TRACKED_CYCLES` | Only already-tracked (open, risk-register-covered) cycles remain; no other defect. |

### 3.5 Stability promise

The exit-code table above and the three trailer sentences are the contract. Anything not listed
here (exact column widths, ordering beyond "sorted by prompt id", additional stdout lines for
future defect types) may change without that being treated as a breaking change to this contract,
unless this document is updated to add it.

## 4. Python programmatic contract

### 4.1 `load_catalog(path=CATALOG_PATH) -> list[dict]`

Reads and JSON-parses the catalog file. Raises `CatalogError` if the file cannot be read, is not
valid JSON, or does not parse to a JSON array — **and (added P0008)** if the array contains a
record that is not a JSON object, has no `id`, has an `id` not matching `^P[0-9]{4}$`, has a
`depends_on` that is not a list, has a `depends_on` item that is not a string, or if two records
share the same `id`. A `depends_on` item that *is* a string but does not match `^P[0-9]{4}$` or
does not resolve to a real id is deliberately **not** rejected here — it already surfaces
correctly as a `missing_targets` entry in `check()`'s result (§4.3), which is the existing,
documented behavior for an unresolved dependency, not a gap P0008 needed to close.

### 4.2 `load_risk_register(path=RISK_REGISTER_PATH) -> dict | None`

Returns the parsed risk register, or `None` if the file does not exist at `path` — unchanged by
P0008, still never raises for a missing file. **Changed in P0008:** a malformed-but-present file
now raises `RiskRegisterError` (unreadable, not valid JSON, not a JSON object, a non-list
`entries`, a non-object entry, a non-string `status`, or an `affected_prompt_range` that is not a
2-item list of `^P[0-9]{4}$` strings) instead of leaking an unwrapped `json.JSONDecodeError` or
crashing later inside `_cycle_is_tracked` with a `ValueError`/`AttributeError` on first use. An
absent `entries` key and an explicit `null` `affected_prompt_range` are both still tolerated —
`check()` already defaults/skips them safely, so validation does not reject more than the code
actually needs. `schema_version` is intentionally not validated: nothing in this module reads it.
**This is a documented breaking change per §8** (it changes *when* an exception is raised for this
function), made because the old behavior — an unwrapped stdlib exception for JSON, and an
uncontrolled crash for a structurally-wrong-but-valid-JSON file — was exactly the "silent/uncontrolled
failure" this whole prompt exists to close, not a behavior worth preserving for compatibility.
No caller of `load_risk_register()` existed anywhere in this repository outside this module's own
tests at the time of this change (verified by search), so there is no real caller to break.

### 4.3 `check(catalog, risk_register=None) -> dict`

Pure function, no I/O, no exceptions raised for data-quality problems in `catalog`. Its return
shape is the authoritative contract in
[`schemas/check_result.schema.json`](schemas/check_result.schema.json) — see that file for the
exact, closed (`additionalProperties: false`) key set and types. Summary: `prompt_count`,
`missing_targets`, `self_dependencies`, `tracked_cycles`, `untracked_cycles`,
`risk_register_present`, `exit_code`. **Unchanged by P0008** — the graph-defect logic itself
(missing targets, self-dependencies, cycles) stays exactly as documented; only its two callers'
*input* is now validated more strictly before it ever reaches this function.

### 4.3a `check_result_contract_violations(result) -> list[str]` (added P0008)

Pure function, no I/O. Validates a `check()`-shaped dict against
[`schemas/check_result.schema.json`](schemas/check_result.schema.json) — the exact key set (extra
or missing keys are reported), and per-key type/shape — and returns a list of human-readable
violation strings (empty means conformant). This is the single source of truth for that schema's
enforcement in Python; it is not a second, independent re-encoding of it, and it does not duplicate
`prompt_catalog_entry.schema.json` or `risk_register.schema.json` (those remain enforced only by
`load_catalog`/`load_risk_register`, per §4.1/§4.2, not by a generic schema engine — see §8 for why
no `jsonschema` dependency was added). Intended for tests, and for any future caller that receives
a `check()`-shaped dict from an unproven source (e.g. after a JSON round-trip) and wants to confirm
its shape before trusting it.

### 4.4 `format_report(result) -> str`

Pure function turning a `check()` result into the stdout text described in section 3.2.

### 4.5 `main(argv=None) -> int`

CLI entrypoint. Loads the catalog (catching `CatalogError` and printing to stderr per §3.3), then
loads the risk register (**added P0008:** catching `RiskRegisterError` the same way), calls
`check()`, prints `format_report()`'s output, and returns the result's `exit_code` (or
`EXIT_UNTRACKED_DEFECT` on either exception).

### 4.6 Out of contract

`_strongly_connected_components`, `_prompt_number`, `_cycle_is_tracked`, and (added P0008)
`_validate_catalog_entry`, `_validate_risk_register_structure` are private (leading-underscore)
implementation details. They are not part of this contract and may change signature or behavior at
any time without notice. `check_result_contract_violations` (§4.3a) is deliberately public and
**is** part of the contract, since it exists specifically to be called from outside the module.

## 5. Data contracts consumed

- **`PROMPT_CATALOG.json` entry** — [`schemas/prompt_catalog_entry.schema.json`](schemas/prompt_catalog_entry.schema.json).
- **`RISK_REGISTER.json`** — [`schemas/risk_register.schema.json`](schemas/risk_register.schema.json).

Both schemas describe only the fields `check_dependency_graph.py` actually reads (`id`,
`depends_on` for the catalog entry; `schema_version`, `entries[].status`,
`entries[].affected_prompt_range` for the risk register) and use `additionalProperties: true`
throughout, so the full human-authored records — carrying track metadata, severity, evidence,
`gate_condition`, `resolution`, and so on — remain valid without this contract having to
speculatively describe fields nothing here reads. **Since P0008, both schemas are also actively
enforced** at load time (`load_catalog`/`load_risk_register`, §4.1/§4.2) — not just documented —
using hand-written stdlib validation kept in 1:1 correspondence with these exact schema files
(same fields, same patterns, same optionality), rather than a generic JSON Schema engine. `id` and
`entries[].affected_prompt_range` items both reuse one `_PROMPT_ID_PATTERN` constant in the source,
so the two schemas' `P[0-9]{4}` pattern cannot silently drift apart from what the code enforces.

## 6. Identity rule (canonical MediaForge IDs)

Every identity that appears anywhere in this contract, its schemas, or the tool it describes is
either a prompt id matching `^P[0-9]{4}$` or a risk id matching `^RISK-[0-9]{4}$`. No engine id,
provider id, ULID, or database primary key appears anywhere in this surface — there is nothing
here to couple to one, since this track owns no database table (see
[[GOVERNANCE_BOUNDARIES.md]] §"No cross-engine coupling applies vacuously here").

## 7. Error/validation-response consistency rule

One consistent three-tier model, used identically by both the CLI and programmatic contracts. This
is the anchor of P0008's failure semantics — every conflict/malformed-input case added by this
prompt slots into tier 1, never into tier 2:

1. **Structural exceptions** — `CatalogError` (raised by `load_catalog()`) and (added P0008)
   `RiskRegisterError` (raised by `load_risk_register()`) for structurally invalid *input*:
   unreadable file, invalid JSON, wrong top-level type, or (P0008) a malformed individual record —
   a catalog entry with no/invalid `id` or a wrongly-typed `depends_on`, or a risk-register entry
   with a wrongly-typed `status` or a malformed `affected_prompt_range`. These are the only two
   exception types this contract raises. Nothing here is guessed or defaulted: an ambiguous or
   contradictory record fails loudly instead of being silently coerced or skipped.
2. **Structured result** — `check()` never raises for graph-*shape* defects, and P0008 did not
   change that: a missing dependency target, a self-dependency, or a cycle is not an exception; it
   is data in the returned dict, summarized by `exit_code`. Callers that want defect details read
   the dict's fields directly. `check_result_contract_violations()` (§4.3a) validates the *shape*
   of that dict itself, for callers that don't trust their source of it.
3. **CLI translation** — `main()` converts either tier-1 exception into a one-line stderr message
   plus `EXIT_UNTRACKED_DEFECT`, and tier 2 into the stdout report plus the result's own
   `exit_code`. Both paths use the same three exit codes from section 3.4; there is no separate
   error-code space, and (documented explicitly, not accidentally) tier 1 and an untracked tier-2
   defect are not distinguishable by exit code alone — see the note in §3.4.

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
- **P0008 breaking change, made explicitly, not silently:** `load_risk_register()` now raises
  `RiskRegisterError` for a malformed-but-present file, where it previously either leaked an
  unwrapped `json.JSONDecodeError` (invalid JSON) or crashed later, uncontrolled, inside
  `_cycle_is_tracked` (structurally-wrong-but-valid JSON — e.g. a `ValueError` from `_prompt_number`
  on a non-`P####` `affected_prompt_range` entry). Per this section's own rule ("changing when
  CatalogError is raised is breaking"), the equivalent change for the new `RiskRegisterError` is
  documented here in the same change that made it, with code and tests updated together (see the
  P0008 completion response for the exact diff). No caller outside this module's own tests existed
  at the time, so nothing external observably broke.
- **P0008 non-breaking additions:** `CatalogError`'s raise conditions were widened (§4.1) to cover
  malformed individual catalog records — additive to an existing exception type, not a new one, and
  not a change to any exit code's meaning, so not classified as breaking under this section's own
  test ("changing what an exit code means" / "changing when CatalogError is raised" — the latter is
  arguably touched, but only by *narrowing what already-invalid input is accepted*, never by
  rejecting previously-valid input; every catalog record this module could load before P0008 still
  loads unchanged after it). `check_result_contract_violations()` (§4.3a) is a wholly new, additive,
  opt-in function.

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
- **(P0008) No `jsonschema` (or any other) pip dependency added.** The repository has no Python
  dependency-management convention anywhere (no `requirements.txt`, `pyproject.toml`, or `Pipfile`
  at any level) and `tools/prompts/`'s own test suite is deliberately stdlib-only. The validation
  surface P0008 needed is narrow and already scoped down to "fields the checker actually reads"
  (§5) — expressing exactly that in ~2 small validator functions is not a second, competing source
  of truth for the schemas (see §4.3a), and does not carry the packaging/CI-wiring cost a new
  interpreter-level dependency would add for a benefit this narrow. If validation ever needs to
  cover the schemas' full, speculative surface (not just load-bearing fields), that is the point to
  revisit this decision — not before.
- **(P0008) No GitHub Actions CI job added** for `tools/prompts/test_check_dependency_graph.py`.
  It still is not wired into `.github/workflows/ci.yml` (confirmed still true after this prompt).
  This was not part of P0008's requested scope and is called out as a known limitation, not fixed
  here, to avoid pulling a later prompt's work forward.

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

### P0008 verification

- `cd tools/prompts && python3 -m unittest test_check_dependency_graph -v` — see the P0008
  completion response for the exact test count and result (11 pre-existing tests, all still
  passing, plus new negative tests for every malformed-input case in §4.1/§4.2).
- `python3 tools/prompts/check_dependency_graph.py` against the real, unmodified
  `PROMPT_CATALOG.json` and `RISK_REGISTER.json` — must still report `720 prompts checked. /
  Result: clean.`, exit `0`, proving the new validation does not reject real, already-valid data.
- `grep` confirmed no other file in the repository imports `load_risk_register` (the one function
  whose raise conditions genuinely widened), supporting the "no external caller broke" claim in §8.
