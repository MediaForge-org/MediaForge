#!/usr/bin/env python3
"""Verify PROMPT_CATALOG.json's depends_on graph and cross-reference known cycles
against the governance risk register, if one is present in this checkout.

Application-layer orchestration logic for Track 01 (governance, repository audit
and execution discipline). Read-only, idempotent, stdlib-only, no product code
touched. See docs/MediaForge/prompts/01-governance-audit/P0005_application.md.
"""
import json
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
CATALOG_PATH = ROOT / "docs/MediaForge/prompts/PROMPT_CATALOG.json"
RISK_REGISTER_PATH = ROOT / "docs/MediaForge/prompts/01-governance-audit/RISK_REGISTER.json"

# Deterministic, domain-specific exit codes.
EXIT_CLEAN = 0
EXIT_UNTRACKED_DEFECT = 1
EXIT_ONLY_TRACKED_CYCLES = 2

# Matches schemas/prompt_catalog_entry.schema.json's `id` pattern and
# schemas/risk_register.schema.json's `affected_prompt_range` item pattern.
# Single source of truth for both -- if this ever needs to change, the two
# schema files must change with it (see GOVERNANCE_API_CONTRACT.md).
_PROMPT_ID_PATTERN = re.compile(r"^P[0-9]{4}$")


class CatalogError(Exception):
    """Raised when PROMPT_CATALOG.json cannot be loaded, parsed, or contains a
    structurally invalid record (as of P0008: also covers a non-object entry,
    a missing/malformed id, a non-list depends_on, a non-string depends_on
    item, or a duplicate id -- extended from P0005/P0006, which only covered
    file-level defects). See GOVERNANCE_API_CONTRACT.md #4.1 / #7."""


class RiskRegisterError(Exception):
    """Raised when RISK_REGISTER.json is present but structurally invalid:
    unreadable, not valid JSON, not a JSON object, a non-list `entries`, a
    non-object entry, a non-string `status`, or a malformed
    `affected_prompt_range` (not a 2-item list of P#### strings). Added in
    P0008 -- previously a malformed-but-present file leaked an unwrapped
    json.JSONDecodeError, or crashed later inside _cycle_is_tracked with a
    ValueError/AttributeError on first use. A missing file still returns
    None, unchanged. See GOVERNANCE_API_CONTRACT.md #4.2 / #7."""


def _validate_catalog_entry(record, index):
    """Structural validation only -- the load-bearing shape check(), _prompt_number
    and the SCC pass actually depend on, nothing beyond it (matches the load-bearing
    scope schemas/prompt_catalog_entry.schema.json already documents). A depends_on
    item that is a string but does not resolve to a real id, or does not match the
    P#### pattern, is deliberately NOT rejected here -- it already surfaces safely
    and correctly as a `missing_targets` structured result (see check()), which is
    the existing, documented P0006 behavior for an unresolved dependency, not a new
    failure mode P0008 needs to invent."""
    if not isinstance(record, dict):
        raise CatalogError(
            f"catalog entry at index {index} is not a JSON object (got {type(record).__name__})"
        )
    if "id" not in record:
        raise CatalogError(f"catalog entry at index {index} has no 'id' field")
    prompt_id = record["id"]
    if not isinstance(prompt_id, str) or not _PROMPT_ID_PATTERN.match(prompt_id):
        raise CatalogError(
            f"catalog entry at index {index} has an invalid id {prompt_id!r} "
            "(must be a string matching P####)"
        )
    if "depends_on" in record:
        deps = record["depends_on"]
        if not isinstance(deps, list):
            raise CatalogError(
                f"{prompt_id}: depends_on must be a list (got {type(deps).__name__})"
            )
        for dep_index, dep in enumerate(deps):
            if not isinstance(dep, str):
                raise CatalogError(
                    f"{prompt_id}: depends_on[{dep_index}] must be a string "
                    f"(got {type(dep).__name__})"
                )


def load_catalog(path=CATALOG_PATH):
    try:
        raw = path.read_text()
    except OSError as exc:
        raise CatalogError(f"cannot read {path}: {exc}") from exc
    try:
        data = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise CatalogError(f"{path} is not valid JSON: {exc}") from exc
    if not isinstance(data, list):
        raise CatalogError(f"{path} must be a JSON array of prompt records")

    seen_ids = set()
    for index, record in enumerate(data):
        _validate_catalog_entry(record, index)
        prompt_id = record["id"]
        if prompt_id in seen_ids:
            raise CatalogError(f"duplicate prompt id {prompt_id!r} in catalog")
        seen_ids.add(prompt_id)

    return data


def _validate_risk_register_structure(data):
    """Structural validation limited to the fields check()/_cycle_is_tracked
    actually reads (entries[].status, entries[].affected_prompt_range) -- the
    same load-bearing-only scope schemas/risk_register.schema.json documents.
    `schema_version` is intentionally not validated: nothing in this module
    reads it, so enforcing it here would reject files P0006's own contract
    does not require this code to care about."""
    if not isinstance(data, dict):
        raise RiskRegisterError(
            f"RISK_REGISTER.json must be a JSON object (got {type(data).__name__})"
        )
    if "entries" not in data:
        return  # absent `entries` is tolerated -- check() already defaults it to [].
    entries = data["entries"]
    if not isinstance(entries, list):
        raise RiskRegisterError(
            f"RISK_REGISTER.json 'entries' must be a list (got {type(entries).__name__})"
        )
    for index, entry in enumerate(entries):
        if not isinstance(entry, dict):
            raise RiskRegisterError(f"risk entry at index {index} is not a JSON object")
        if "status" in entry and not isinstance(entry["status"], str):
            raise RiskRegisterError(f"risk entry at index {index}: status must be a string")
        if "affected_prompt_range" in entry and entry["affected_prompt_range"] is not None:
            rng = entry["affected_prompt_range"]
            valid_shape = (
                isinstance(rng, list)
                and len(rng) == 2
                and all(isinstance(x, str) and _PROMPT_ID_PATTERN.match(x) for x in rng)
            )
            if not valid_shape:
                raise RiskRegisterError(
                    f"risk entry at index {index}: affected_prompt_range must be a "
                    f"[low, high] pair of P#### ids (got {rng!r})"
                )


def load_risk_register(path=RISK_REGISTER_PATH):
    """Returns the parsed risk register, or None if this checkout does not have one."""
    if not path.exists():
        return None
    try:
        raw = path.read_text()
    except OSError as exc:
        raise RiskRegisterError(f"cannot read {path}: {exc}") from exc
    try:
        data = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise RiskRegisterError(f"{path} is not valid JSON: {exc}") from exc
    _validate_risk_register_structure(data)
    return data


def _strongly_connected_components(graph):
    """Tarjan's SCC algorithm. graph: id -> list of dependency ids (edges may point
    outside the key set; such targets are treated as their own trivial component)."""
    index_counter = [0]
    stack = []
    lowlink = {}
    index = {}
    on_stack = {}
    sccs = []

    def strongconnect(node):
        index[node] = index_counter[0]
        lowlink[node] = index_counter[0]
        index_counter[0] += 1
        stack.append(node)
        on_stack[node] = True
        for succ in graph.get(node, []):
            if succ not in graph:
                continue
            if succ not in index:
                strongconnect(succ)
                lowlink[node] = min(lowlink[node], lowlink[succ])
            elif on_stack.get(succ):
                lowlink[node] = min(lowlink[node], index[succ])
        if lowlink[node] == index[node]:
            comp = []
            while True:
                w = stack.pop()
                on_stack[w] = False
                comp.append(w)
                if w == node:
                    break
            sccs.append(comp)

    for n in list(graph):
        if n not in index:
            strongconnect(n)
    return sccs


def _prompt_number(prompt_id):
    return int(prompt_id[1:])


def _cycle_is_tracked(cycle_ids, risk_register):
    """A cycle is 'tracked' if an open risk-register entry's affected_prompt_range
    fully covers it. Anything else (no register, no matching entry, entry not open)
    counts as untracked — silence is never treated as acknowledgement."""
    if not risk_register:
        return False
    lo = min(_prompt_number(p) for p in cycle_ids)
    hi = max(_prompt_number(p) for p in cycle_ids)
    for entry in risk_register.get("entries", []):
        if entry.get("status") != "open":
            continue
        rng = entry.get("affected_prompt_range")
        if not rng or len(rng) != 2:
            continue
        entry_lo, entry_hi = _prompt_number(rng[0]), _prompt_number(rng[1])
        if entry_lo <= lo and hi <= entry_hi:
            return True
    return False


def check(catalog, risk_register=None):
    """Pure function: no I/O. Returns a result dict describing the graph's health."""
    ids = {p["id"] for p in catalog}
    graph = {p["id"]: p.get("depends_on", []) for p in catalog}

    missing_targets = sorted(
        (p["id"], dep)
        for p in catalog
        for dep in p.get("depends_on", [])
        if dep not in ids
    )
    self_deps = sorted(p["id"] for p in catalog if p["id"] in p.get("depends_on", []))

    sccs = _strongly_connected_components(graph)
    cycles = [sorted(c, key=_prompt_number) for c in sccs if len(c) > 1]
    cycles.sort(key=lambda c: _prompt_number(c[0]))

    tracked_cycles = [c for c in cycles if _cycle_is_tracked(c, risk_register)]
    untracked_cycles = [c for c in cycles if not _cycle_is_tracked(c, risk_register)]

    has_untracked_defect = bool(missing_targets or self_deps or untracked_cycles)
    if has_untracked_defect:
        exit_code = EXIT_UNTRACKED_DEFECT
    elif tracked_cycles:
        exit_code = EXIT_ONLY_TRACKED_CYCLES
    else:
        exit_code = EXIT_CLEAN

    return {
        "prompt_count": len(catalog),
        "missing_targets": missing_targets,
        "self_dependencies": self_deps,
        "tracked_cycles": tracked_cycles,
        "untracked_cycles": untracked_cycles,
        "risk_register_present": risk_register is not None,
        "exit_code": exit_code,
    }


def check_result_contract_violations(result):
    """Validates a check()-shaped dict against schemas/check_result.schema.json --
    the same closed, 7-key, additionalProperties:false contract, expressed once
    here rather than re-encoded a second time as a general-purpose JSON Schema
    validator. Returns a list of human-readable violation strings; an empty list
    means `result` conforms. Pure, no I/O. Intended for tests and for any future
    caller that receives a check()-shaped dict from an unproven source (e.g. after
    round-tripping through JSON) and wants to confirm it before trusting it."""
    violations = []
    expected_keys = {
        "prompt_count",
        "missing_targets",
        "self_dependencies",
        "tracked_cycles",
        "untracked_cycles",
        "risk_register_present",
        "exit_code",
    }
    if not isinstance(result, dict):
        return [f"result must be a dict (got {type(result).__name__})"]

    actual_keys = set(result.keys())
    if actual_keys != expected_keys:
        missing = expected_keys - actual_keys
        extra = actual_keys - expected_keys
        if missing:
            violations.append(f"missing keys: {sorted(missing)}")
        if extra:
            violations.append(f"unexpected keys: {sorted(extra)}")
        return violations  # further per-key checks need the keys to exist.

    def is_prompt_id(value):
        return isinstance(value, str) and bool(_PROMPT_ID_PATTERN.match(value))

    if not (isinstance(result["prompt_count"], int) and result["prompt_count"] >= 0):
        violations.append("prompt_count must be an int >= 0")

    if not (
        isinstance(result["missing_targets"], list)
        and all(
            isinstance(pair, (list, tuple)) and len(pair) == 2
            and is_prompt_id(pair[0]) and isinstance(pair[1], str)
            for pair in result["missing_targets"]
        )
    ):
        violations.append("missing_targets must be a list of (prompt_id, dep) pairs")

    if not (
        isinstance(result["self_dependencies"], list)
        and all(is_prompt_id(pid) for pid in result["self_dependencies"])
    ):
        violations.append("self_dependencies must be a list of P#### ids")

    for key in ("tracked_cycles", "untracked_cycles"):
        value = result[key]
        if not (
            isinstance(value, list)
            and all(
                isinstance(cycle, list) and len(cycle) >= 2
                and all(is_prompt_id(pid) for pid in cycle)
                for cycle in value
            )
        ):
            violations.append(f"{key} must be a list of P#### id lists (each length >= 2)")

    if not isinstance(result["risk_register_present"], bool):
        violations.append("risk_register_present must be a bool")

    if result["exit_code"] not in (EXIT_CLEAN, EXIT_UNTRACKED_DEFECT, EXIT_ONLY_TRACKED_CYCLES):
        violations.append("exit_code must be one of EXIT_CLEAN, EXIT_UNTRACKED_DEFECT, EXIT_ONLY_TRACKED_CYCLES")

    return violations


def format_report(result):
    lines = [f"{result['prompt_count']} prompts checked."]
    if result["missing_targets"]:
        lines.append(f"MISSING DEPENDENCY TARGETS ({len(result['missing_targets'])}):")
        for pid, dep in result["missing_targets"]:
            lines.append(f"  {pid} depends_on unknown {dep}")
    if result["self_dependencies"]:
        lines.append(f"SELF-DEPENDENCIES ({len(result['self_dependencies'])}):")
        for pid in result["self_dependencies"]:
            lines.append(f"  {pid}")
    if not result["risk_register_present"]:
        lines.append(
            "No RISK_REGISTER.json in this checkout — any cycle below is reported "
            "as untracked from this branch's point of view, regardless of whether "
            "it is recorded elsewhere."
        )
    for c in result["untracked_cycles"]:
        lines.append(f"UNTRACKED CYCLE ({len(c)} prompts): {c[0]} .. {c[-1]}")
    for c in result["tracked_cycles"]:
        lines.append(f"Tracked cycle, already in an open risk-register entry ({len(c)} prompts): {c[0]} .. {c[-1]}")
    if result["exit_code"] == EXIT_CLEAN:
        lines.append("Result: clean.")
    elif result["exit_code"] == EXIT_ONLY_TRACKED_CYCLES:
        lines.append("Result: only already-tracked cycles present. Not a fresh failure.")
    else:
        lines.append("Result: untracked structural defect(s) present.")
    return "\n".join(lines)


def main(argv=None):
    try:
        catalog = load_catalog()
    except CatalogError as exc:
        print(f"error: {exc}", file=sys.stderr)
        return EXIT_UNTRACKED_DEFECT
    try:
        risk_register = load_risk_register()
    except RiskRegisterError as exc:
        print(f"error: {exc}", file=sys.stderr)
        return EXIT_UNTRACKED_DEFECT
    result = check(catalog, risk_register)
    print(format_report(result))
    return result["exit_code"]


if __name__ == "__main__":
    raise SystemExit(main())
