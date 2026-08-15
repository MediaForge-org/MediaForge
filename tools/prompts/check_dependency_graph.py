#!/usr/bin/env python3
"""Verify PROMPT_CATALOG.json's depends_on graph and cross-reference known cycles
against the governance risk register, if one is present in this checkout.

Application-layer orchestration logic for Track 01 (governance, repository audit
and execution discipline). Read-only, idempotent, stdlib-only, no product code
touched. See docs/MediaForge/prompts/01-governance-audit/P0005_application.md.
"""
import json
import pathlib
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
CATALOG_PATH = ROOT / "docs/MediaForge/prompts/PROMPT_CATALOG.json"
RISK_REGISTER_PATH = ROOT / "docs/MediaForge/prompts/01-governance-audit/RISK_REGISTER.json"

# Deterministic, domain-specific exit codes.
EXIT_CLEAN = 0
EXIT_UNTRACKED_DEFECT = 1
EXIT_ONLY_TRACKED_CYCLES = 2


class CatalogError(Exception):
    """Raised when PROMPT_CATALOG.json cannot be loaded or parsed."""


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
    return data


def load_risk_register(path=RISK_REGISTER_PATH):
    """Returns the parsed risk register, or None if this checkout does not have one."""
    if not path.exists():
        return None
    return json.loads(path.read_text())


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
    risk_register = load_risk_register()
    result = check(catalog, risk_register)
    print(format_report(result))
    return result["exit_code"]


if __name__ == "__main__":
    raise SystemExit(main())
