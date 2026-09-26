# Governance security boundary — Track 01 (governance, repository audit and execution discipline)

Status: **P0009 output**. This file records the real security boundary of the governance tooling
(`tools/prompts/check_dependency_graph.py`), the gaps P0009 closed in it, and the generic P0009
criteria that do not apply to a local, offline tool. Builds on [[GOVERNANCE_BOUNDARIES.md]] (P0003)
and [[GOVERNANCE_API_CONTRACT.md]] (P0006/P0008). The contract changes are recorded in that
contract, in the same change as the code and tests (§8 there).

## 1. Trust model

- **Principal.** The only actor is the local developer or agent who runs the CLI or imports the
  module. The tool runs with that user's OS privileges and never changes them. It has no login,
  session, token, network listener, network client, subprocess, `eval`/`exec`, or environment-variable
  input. This was checked by searching the module source; P0009 adds none of these.
- **Inputs.** There are exactly two, and both have fixed paths resolved from the script's own
  location: `PROMPT_CATALOG.json` and, optionally, `RISK_REGISTER.json`. The CLI takes no arguments.
  No input value is ever used as a path, a command, or a format string.
- **Trust level of inputs.** Both files are git-tracked and reviewed through diffs. They are still
  **treated as untrusted for parsing**, because they can be hand-edited, merge-corrupted, or replaced
  by an external import (this already happened once, on 2026-08-16; see `RISK-0001`). "Untrusted for
  parsing" means that no input shape may crash the tool, forge its output, or make it echo content.
- **Outputs.** The tool writes to stdout and stderr and returns an exit code. It never writes a file,
  so there is no write-path, symlink-write, or TOCTOU surface.

## 2. Semantic trust boundary of `RISK_REGISTER.json`

The risk register is the only input that can make the result **less strict**. An `open` entry whose
`affected_prompt_range` covers a cycle moves that cycle from `untracked` (exit 1) to `tracked`
(exit 2). The bounds on that power:

- It can never produce `Result: clean.` (exit 0). Missing targets and self-dependencies are never
  suppressible.
- A missing register, or an entry that is not `open`, makes the result stricter, never more lenient
  (fail-closed: "silence is never treated as acknowledgement").
- A very wide `open` range, for example `["P0001", "P0720"]`, would downgrade **every** cycle to
  exit 2. The tool cannot tell a legitimate wide entry from an abusive one. The control is human
  review of `RISK_REGISTER.json` diffs. This is a documented trust assumption, not a code guarantee.

## 3. Threat review — what was checked and what P0009 changed

| Surface | Real? | Finding before P0009 | Action |
| --- | --- | --- | --- |
| Invalid UTF-8 input | yes | `read_text()` used the locale encoding and leaked an uncaught `UnicodeDecodeError` (traceback, not the documented error type). | Inputs are now read as bytes and decoded as strict UTF-8. Failure becomes `CatalogError`/`RiskRegisterError`, and the message reports a byte offset only. |
| Deeply nested JSON | yes | `json.loads` raised an uncaught `RecursionError`. | Wrapped as `CatalogError`/`RiskRegisterError` ("nested too deeply to parse"). |
| Long dependency chains | yes | The recursive Tarjan pass raised `RecursionError` inside `check()` for a reverse-ordered chain of more than about 1000 prompts. That broke the "`check()` never raises for data problems" contract. The real catalog's longest chain is 720, so it was one reordering away from the default recursion limit of 1000. | Tarjan is now iterative, with an unchanged result. It was verified equivalent to the old implementation on 3000 random graphs and on the real catalog. |
| Echo of input in errors | yes | An invalid `id` or `affected_prompt_range` was echoed in full with `!r`, which is unbounded (5086 characters in a probe). | Every echoed input value goes through `_bounded_repr`: at most 80 characters, and `repr` escaping, so there are no raw newlines or terminal escapes. |
| Report forging on stdout | yes | A `depends_on` string (validated only as "a string") was printed raw. `"X\nResult: clean."` injected a fake trailer line into stdout. | An unresolved dependency that is not a well-formed `P####` id is printed through `_bounded_repr`. Well-formed ids still print verbatim, so real reports are unchanged. |
| File content in errors | checked | None. `JSONDecodeError` messages carry a position, not content. | Regression test added. |
| Secrets / private data | checked | None. The tool reads no credentials, `.env` file, environment, or private library data. Diagnostics contain at most the configured file path, a position, and a bounded, escaped input value. | None needed. |
| Command / shell injection | N/A | No subprocess, shell, `eval` or `exec`. | None. |
| Path traversal | N/A | No input value is used as a path. | None. |
| Symlinks | N/A | A symlinked input is only read, never written, and its content is never echoed. A symlink swap is visible in git review like any other change. | None. |

Residual, deliberate: an interpreter-level failure such as `MemoryError` is not caught. It still
ends the process with a non-zero exit, so it stays fail-closed. A blanket `except Exception` would
hide real defects, so none is added.

`tools/prompts/show_prompt.py` is a separate developer helper outside this contract. It opens
`root / p["file"]` from the catalog, so a hostile `file` value (absolute or `..`) would print another
file that the invoking user can already read. No privilege boundary is crossed, and all 720 real
`file` values are relative with no `..` (verified), so it was reviewed and not changed.

## 4. Acceptance criteria disposition

| P0009 criterion | Disposition |
| --- | --- |
| Authorization happens server-side | **N/A.** There is no server, no principal model, and no remote caller. OS file permissions are the only access control, and the tool neither widens nor bypasses them. An auth layer would invent a product surface that this track forbids (see GOVERNANCE_API_CONTRACT.md §9). |
| Sensitive existence/data does not leak in locked/unauthorized contexts | The inputs contain no adult, private-library or user data. What applies is the rule that diagnostics must not leak input content, which is enforced by bounded, escaped echo (§3). |
| Security regression tests cover direct API access, not just UI navigation | Covered. There is no UI, and the "direct API" is the Python/CLI contract. `SecurityBoundaryTests` calls `load_catalog`, `load_risk_register`, `check`, `format_report` and `main()` directly. |
| Existing behavior outside this prompt keeps working | The real catalog still reports `720 prompts checked. / Result: clean.` (exit 0). All pre-existing tests still pass. |
| New code follows target responsibility boundaries | Still stdlib-only, still read-only, still local. No product code was touched. |
| No secrets/private data added | None were added. The only test fixture that looks like a token is the literal `hunter2-not-a-real-token`. |
