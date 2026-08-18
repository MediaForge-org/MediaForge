# Governance frontend scope decision — Track 01 (governance, repository audit and execution discipline)

Status: **P0007 output**. Documentation only, no code changed — this track does not implement
product features (see `GLOBAL_RULES_SHORT.md` and every prompt's *Subsystem-specific rule* in this
track, restated verbatim in P0001–P0007). Builds on [[GOVERNANCE_DOMAIN_MODEL.md]] (P0002),
[[GOVERNANCE_BOUNDARIES.md]] (P0003) and [[GOVERNANCE_API_CONTRACT.md]] (P0006).

## Decision

P0007's exact work is "Implement the first frontend integration surface without redesigning
unrelated screens." No frontend surface was implemented. **No file under `app/Http/Controllers/`,
`routes/`, or `resources/js/` was created or changed by this prompt.** This mirrors the pattern
already established for this track: P0004 substituted a versioned JSON file for a database table
because no runtime query/write need existed; P0005 substituted a stdlib CLI script for a Laravel
application service; P0006 substituted a contract document for an HTTP API because none exists and
none was warranted. P0007 continues that pattern by concluding no product-facing UI is warranted
either, for the same underlying reason each time: **this track governs how work is executed across
the 720-prompt system — it has no product feature to expose, and its data (prompt counts,
dependency-graph health, risk-register status) has no end-user audience in MediaForge, the media
server product.** A MediaForge user watching a show has no use for "720 prompts checked, 0 open
risks"; that information is for the developer/agent operating the prompt system, and P0006 already
gave that audience its integration surface: the CLI and Python contract in
[[GOVERNANCE_API_CONTRACT.md]].

This was a genuine three-way fork, not a foregone conclusion, and was resolved explicitly with the
user before writing any code: build a `make`-target CLI entry point (reusing the existing
`dev-doctor`/`setup-check` developer-tooling pattern already in this repo's `Makefile`), build a
minimal read-only Laravel/Inertia page, or document the non-applicability with no new artifact. The
user chose the third option.

## Why the acceptance criteria still hold, unmet by design

| Acceptance criterion | Disposition |
| --- | --- |
| UI uses shared design primitives and current route/API contracts | Not applicable — no UI built. There is no product route/API for this track's data (P0006 §1: "not an HTTP/REST API"), so there is nothing new to render against. |
| Loading/empty/error/disabled states are implemented | Already fully covered, by the CLI/Python contract this track already shipped in P0006 — not newly built here, but not missing either. See mapping below. |
| Unrelated pages are not restyled in this prompt | Trivially satisfied — no page of any kind was touched. |
| Existing relevant behavior outside this prompt remains working | Trivially satisfied — no code changed. Verified by an unchanged re-run of `make ci` and the governance-tooling test suite (see completion response). |
| New code follows the target responsibility boundaries rather than adding another temporary permanent architecture | Trivially satisfied — no new code, so no boundary to violate. Adding a real (even minimal) HTTP route/page here would itself have been the "temporary permanent architecture" this criterion warns against: a governance-only read surface bolted onto the shipped consumer product with no product justification. |
| No secrets/private user data are added to the repository | Trivially true for a documentation-only change. |

### State mapping (already shipped, not new)

The four states this criterion asks for exist today in the CLI/Python surface documented in
[[GOVERNANCE_API_CONTRACT.md]] — restated here only as a mapping, not reimplemented:

| UI-shaped state | Governance-tooling equivalent |
| --- | --- |
| Loading | N/A — `check_dependency_graph.py` is synchronous with no progress reporting; see contract §3.5 stability promise. |
| Empty | A clean graph: `Result: clean.`, `exit_code == EXIT_CLEAN`. |
| Error | A malformed/unreadable catalog: `CatalogError` → one stderr line, `exit_code == EXIT_UNTRACKED_DEFECT`; see contract §3.3/§7. |
| Disabled/unavailable | No `RISK_REGISTER.json` in the checkout: `load_risk_register()` returns `None`, `risk_register_present: false`, and the report says so explicitly; see contract §4.2. |

## Non-goals honored

No media player, audio/DSP, library/file management, acquisition, Jellyfin/Stash/Audiobookshelf
engine work, or new media-server functionality was touched or implied — none of that was ever in
scope for this track. No generic dashboard redesign, no unrelated screen restyled. No speculative
HTTP API invented to give a hypothetical page something to call. No P0008 or later work started.

## Verification performed for this prompt

Since no code changed, verification proves the *negative* — that nothing regressed and nothing
new leaked into product surfaces:
- `git diff --check` — no whitespace/conflict-marker issues in the one file this prompt touches.
- `python3 tools/prompts/check_dependency_graph.py` — unchanged, still clean (720 prompts, no
  untracked cycles); this prompt did not touch the catalog or the risk register.
- `grep` across `app/Http/Controllers/`, `routes/`, `resources/js/Pages/` for anything governance/
  prompt-catalog/risk-register-related — confirms none exists, before and after this prompt.
- See the completion response for exact commands and results.
