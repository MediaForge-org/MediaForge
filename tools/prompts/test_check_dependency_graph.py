#!/usr/bin/env python3
"""Tests for check_dependency_graph.py. Stdlib unittest only, run with:
    python3 -m unittest tools/prompts/test_check_dependency_graph.py -v
"""
import contextlib
import io
import json
import tempfile
import unittest
from pathlib import Path
from unittest import mock

import check_dependency_graph
from check_dependency_graph import (
    EXIT_CLEAN,
    EXIT_ONLY_TRACKED_CYCLES,
    EXIT_UNTRACKED_DEFECT,
    CatalogError,
    RiskRegisterError,
    check,
    check_result_contract_violations,
    format_report,
    load_catalog,
    load_risk_register,
)


def prompt(pid, depends_on=()):
    return {"id": pid, "depends_on": list(depends_on)}


class CleanGraphTests(unittest.TestCase):
    def test_empty_catalog_is_clean(self):
        result = check([])
        self.assertEqual(result["exit_code"], EXIT_CLEAN)

    def test_linear_chain_is_clean(self):
        catalog = [prompt("P0001"), prompt("P0002", ["P0001"]), prompt("P0003", ["P0002"])]
        result = check(catalog)
        self.assertEqual(result["exit_code"], EXIT_CLEAN)
        self.assertEqual(result["missing_targets"], [])
        self.assertEqual(result["self_dependencies"], [])
        self.assertEqual(result["untracked_cycles"], [])

    def test_diamond_dependency_is_clean_not_a_cycle(self):
        # P0004 depends on two prompts that share a common ancestor: not a cycle.
        catalog = [
            prompt("P0001"),
            prompt("P0002", ["P0001"]),
            prompt("P0003", ["P0001"]),
            prompt("P0004", ["P0002", "P0003"]),
        ]
        result = check(catalog)
        self.assertEqual(result["exit_code"], EXIT_CLEAN)


class DefectDetectionTests(unittest.TestCase):
    def test_missing_dependency_target_is_reported(self):
        catalog = [prompt("P0001", ["P0999"])]
        result = check(catalog)
        self.assertEqual(result["exit_code"], EXIT_UNTRACKED_DEFECT)
        self.assertEqual(result["missing_targets"], [("P0001", "P0999")])

    def test_self_dependency_is_reported(self):
        catalog = [prompt("P0001", ["P0001"])]
        result = check(catalog)
        self.assertEqual(result["exit_code"], EXIT_UNTRACKED_DEFECT)
        self.assertEqual(result["self_dependencies"], ["P0001"])

    def test_two_prompt_cycle_is_untracked_without_a_risk_register(self):
        catalog = [prompt("P0001", ["P0002"]), prompt("P0002", ["P0001"])]
        result = check(catalog, risk_register=None)
        self.assertEqual(result["exit_code"], EXIT_UNTRACKED_DEFECT)
        self.assertEqual(len(result["untracked_cycles"]), 1)
        self.assertEqual(result["untracked_cycles"][0], ["P0001", "P0002"])
        self.assertFalse(result["risk_register_present"])

    def test_cycle_becomes_tracked_when_covered_by_an_open_risk_entry(self):
        catalog = [prompt("P0001", ["P0002"]), prompt("P0002", ["P0001"])]
        risk_register = {
            "entries": [
                {"status": "open", "affected_prompt_range": ["P0001", "P0002"]},
            ]
        }
        result = check(catalog, risk_register=risk_register)
        self.assertEqual(result["exit_code"], EXIT_ONLY_TRACKED_CYCLES)
        self.assertEqual(result["untracked_cycles"], [])
        self.assertEqual(len(result["tracked_cycles"]), 1)

    def test_cycle_stays_untracked_when_risk_entry_is_not_open(self):
        catalog = [prompt("P0001", ["P0002"]), prompt("P0002", ["P0001"])]
        risk_register = {
            "entries": [
                {"status": "resolved", "affected_prompt_range": ["P0001", "P0002"]},
            ]
        }
        result = check(catalog, risk_register=risk_register)
        self.assertEqual(result["exit_code"], EXIT_UNTRACKED_DEFECT)
        self.assertEqual(len(result["untracked_cycles"]), 1)

    def test_cycle_stays_untracked_when_risk_entry_range_does_not_cover_it(self):
        catalog = [prompt("P0010", ["P0011"]), prompt("P0011", ["P0010"])]
        risk_register = {
            "entries": [
                {"status": "open", "affected_prompt_range": ["P0001", "P0002"]},
            ]
        }
        result = check(catalog, risk_register=risk_register)
        self.assertEqual(result["exit_code"], EXIT_UNTRACKED_DEFECT)

    def test_idempotent_on_repeated_calls(self):
        catalog = [prompt("P0001", ["P0002"]), prompt("P0002", ["P0001"])]
        first = check(catalog)
        second = check(catalog)
        self.assertEqual(first, second)


def _write(dir_path, name, content):
    path = Path(dir_path) / name
    path.write_text(content)
    return path


class CatalogStructuralValidationTests(unittest.TestCase):
    """P0008: malformed/contradictory catalog input must fail deterministically
    (CatalogError), never silently misparse or crash with an unrelated exception."""

    def test_invalid_json_raises_catalog_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(tmp, "catalog.json", "{not valid json")
            with self.assertRaises(CatalogError):
                load_catalog(path=path)

    def test_top_level_wrong_type_raises_catalog_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(tmp, "catalog.json", json.dumps({"not": "a list"}))
            with self.assertRaises(CatalogError):
                load_catalog(path=path)

    def test_malformed_entry_not_an_object_raises_catalog_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(tmp, "catalog.json", json.dumps(["P0001"]))
            with self.assertRaises(CatalogError):
                load_catalog(path=path)

    def test_missing_id_field_raises_catalog_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(tmp, "catalog.json", json.dumps([{"depends_on": []}]))
            with self.assertRaises(CatalogError):
                load_catalog(path=path)

    def test_invalid_prompt_id_format_raises_catalog_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            for bad_id in ["P1", "P00001", "X0001", "p0001", ""]:
                path = _write(tmp, "catalog.json", json.dumps([{"id": bad_id}]))
                with self.assertRaises(CatalogError, msg=f"id={bad_id!r} should be rejected"):
                    load_catalog(path=path)

    def test_wrong_depends_on_type_raises_catalog_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(
                tmp, "catalog.json", json.dumps([{"id": "P0001", "depends_on": "P0000"}])
            )
            with self.assertRaises(CatalogError):
                load_catalog(path=path)
            # A non-string item inside an otherwise-list depends_on is equally rejected --
            # this is exactly the "silent type coercion" case the checker used to accept.

    def test_non_string_depends_on_item_raises_catalog_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(
                tmp, "catalog.json", json.dumps([{"id": "P0001", "depends_on": [1]}])
            )
            with self.assertRaises(CatalogError):
                load_catalog(path=path)

    def test_duplicate_prompt_id_raises_catalog_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(
                tmp,
                "catalog.json",
                json.dumps([{"id": "P0001"}, {"id": "P0001"}]),
            )
            with self.assertRaises(CatalogError):
                load_catalog(path=path)

    def test_valid_minimal_catalog_still_loads(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(
                tmp,
                "catalog.json",
                json.dumps([{"id": "P0001"}, {"id": "P0002", "depends_on": ["P0001"]}]),
            )
            catalog = load_catalog(path=path)
            self.assertEqual(len(catalog), 2)


class RiskRegisterValidationTests(unittest.TestCase):
    """P0008: malformed/contradictory RISK_REGISTER.json input must fail
    deterministically (RiskRegisterError); a missing file must keep returning
    None unchanged (the documented P0006 behavior)."""

    def test_missing_file_still_returns_none(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "does-not-exist.json"
            self.assertIsNone(load_risk_register(path=path))

    def test_invalid_json_raises_risk_register_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(tmp, "risk.json", "{not valid json")
            with self.assertRaises(RiskRegisterError):
                load_risk_register(path=path)

    def test_top_level_wrong_type_raises_risk_register_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(tmp, "risk.json", json.dumps(["not", "an", "object"]))
            with self.assertRaises(RiskRegisterError):
                load_risk_register(path=path)

    def test_entries_wrong_type_raises_risk_register_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(tmp, "risk.json", json.dumps({"entries": "not-a-list"}))
            with self.assertRaises(RiskRegisterError):
                load_risk_register(path=path)

    def test_entry_not_an_object_raises_risk_register_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(tmp, "risk.json", json.dumps({"entries": ["RISK-0001"]}))
            with self.assertRaises(RiskRegisterError):
                load_risk_register(path=path)

    def test_non_string_status_raises_risk_register_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(tmp, "risk.json", json.dumps({"entries": [{"status": 1}]}))
            with self.assertRaises(RiskRegisterError):
                load_risk_register(path=path)

    def test_contradictory_affected_prompt_range_raises_risk_register_error(self):
        cases = [
            ["P0001"],  # wrong length
            ["P0001", "P0002", "P0003"],  # wrong length
            ["notaprompt", "P0002"],  # wrong pattern
            ["P1", "P0002"],  # wrong pattern (would silently mis-parse as int 1)
            "P0001-P0002",  # wrong type entirely
        ]
        with tempfile.TemporaryDirectory() as tmp:
            for rng in cases:
                path = _write(
                    tmp,
                    "risk.json",
                    json.dumps({"entries": [{"status": "open", "affected_prompt_range": rng}]}),
                )
                with self.assertRaises(RiskRegisterError, msg=f"range={rng!r} should be rejected"):
                    load_risk_register(path=path)

    def test_missing_entries_key_is_tolerated(self):
        # check() already defaults an absent `entries` to [] -- validation must not
        # be stricter than what the code actually requires.
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(tmp, "risk.json", json.dumps({"schema_version": 1}))
            data = load_risk_register(path=path)
            self.assertEqual(data, {"schema_version": 1})

    def test_null_affected_prompt_range_is_tolerated(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(
                tmp,
                "risk.json",
                json.dumps({"entries": [{"status": "open", "affected_prompt_range": None}]}),
            )
            data = load_risk_register(path=path)
            self.assertIsNone(data["entries"][0]["affected_prompt_range"])

    def test_valid_risk_register_still_loads_and_still_tracks(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(
                tmp,
                "risk.json",
                json.dumps(
                    {"entries": [{"status": "open", "affected_prompt_range": ["P0001", "P0002"]}]}
                ),
            )
            risk_register = load_risk_register(path=path)
            catalog = [prompt("P0001", ["P0002"]), prompt("P0002", ["P0001"])]
            result = check(catalog, risk_register=risk_register)
            self.assertEqual(result["exit_code"], EXIT_ONLY_TRACKED_CYCLES)


class CheckResultContractTests(unittest.TestCase):
    """P0008: automatic conformance check of check()'s return value against
    schemas/check_result.schema.json (kept as one function, not a second
    hand-rolled schema, per GOVERNANCE_API_CONTRACT.md #8)."""

    def test_real_check_call_conforms_to_its_own_contract(self):
        catalog = [prompt("P0001"), prompt("P0002", ["P0001"])]
        result = check(catalog)
        self.assertEqual(check_result_contract_violations(result), [])

    def test_missing_key_is_a_violation(self):
        result = check([prompt("P0001")])
        del result["exit_code"]
        violations = check_result_contract_violations(result)
        self.assertTrue(any("missing keys" in v for v in violations))

    def test_unexpected_key_is_a_violation(self):
        result = check([prompt("P0001")])
        result["unexpected"] = True
        violations = check_result_contract_violations(result)
        self.assertTrue(any("unexpected keys" in v for v in violations))

    def test_wrong_type_value_is_a_violation(self):
        result = check([prompt("P0001")])
        result["exit_code"] = "0"  # schema says int, enum {0,1,2}
        violations = check_result_contract_violations(result)
        self.assertTrue(any("exit_code" in v for v in violations))

    def test_non_dict_input_is_a_violation_not_a_crash(self):
        violations = check_result_contract_violations(["not", "a", "dict"])
        self.assertEqual(len(violations), 1)


def _run_main(catalog_path, risk_path):
    """Runs the CLI entrypoint against temp files; returns (exit_code, stdout, stderr)."""
    real_load_catalog = check_dependency_graph.load_catalog
    real_load_risk = check_dependency_graph.load_risk_register
    out, err = io.StringIO(), io.StringIO()
    with mock.patch.object(
        check_dependency_graph, "load_catalog", lambda: real_load_catalog(path=catalog_path)
    ), mock.patch.object(
        check_dependency_graph, "load_risk_register", lambda: real_load_risk(path=risk_path)
    ), contextlib.redirect_stdout(out), contextlib.redirect_stderr(err):
        code = check_dependency_graph.main()
    return code, out.getvalue(), err.getvalue()


class SecurityBoundaryTests(unittest.TestCase):
    """P0009: untrusted/malformed governance input fails closed through the documented
    error types, and never echoes raw or unbounded input into stdout/stderr."""

    def test_invalid_utf8_raises_documented_errors(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "bad.json"
            path.write_bytes(b'[{"id": "P0001\xff"}]')
            with self.assertRaises(CatalogError):
                load_catalog(path=path)
            with self.assertRaises(RiskRegisterError):
                load_risk_register(path=path)

    def test_deeply_nested_json_raises_documented_errors(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(tmp, "deep.json", "[" * 100000 + "]" * 100000)
            with self.assertRaises(CatalogError):
                load_catalog(path=path)
            with self.assertRaises(RiskRegisterError):
                load_risk_register(path=path)

    def test_error_message_echo_is_bounded_and_single_line(self):
        hostile = "\x1b[2J\nResult: clean.\n" + "A" * 5000
        with tempfile.TemporaryDirectory() as tmp:
            cat = _write(tmp, "catalog.json", json.dumps([{"id": hostile}]))
            with self.assertRaises(CatalogError) as ctx:
                load_catalog(path=cat)
            risk = _write(
                tmp,
                "risk.json",
                json.dumps({"entries": [{"status": "open", "affected_prompt_range": [hostile]}]}),
            )
            with self.assertRaises(RiskRegisterError) as rctx:
                load_risk_register(path=risk)
        for exc in (ctx.exception, rctx.exception):
            message = str(exc)
            self.assertNotIn("\n", message)
            self.assertNotIn("\x1b", message)
            self.assertNotIn("A" * 200, message)

    def test_error_message_does_not_echo_file_content(self):
        secret = "hunter2-not-a-real-token"
        with tempfile.TemporaryDirectory() as tmp:
            path = _write(tmp, "catalog.json", '{"token": "' + secret + '", broken')
            with self.assertRaises(CatalogError) as ctx:
                load_catalog(path=path)
        self.assertNotIn(secret, str(ctx.exception))

    def test_untrusted_dependency_string_cannot_forge_report_lines(self):
        result = check([prompt("P0001", ["X\nResult: clean.\x1b[0m"])])
        report = format_report(result)
        lines = report.split("\n")
        self.assertNotIn("Result: clean.", lines)
        self.assertNotIn("\x1b", report)
        self.assertEqual(lines[-1], "Result: untracked structural defect(s) present.")

    def test_valid_missing_target_is_still_printed_verbatim(self):
        report = format_report(check([prompt("P0001", ["P0999"])]))
        self.assertIn("  P0001 depends_on unknown P0999", report.split("\n"))

    def test_long_dependency_chain_does_not_exhaust_recursion(self):
        # Reverse-ordered chain: DFS from the first key descends through every node.
        n = 5000
        catalog = [
            prompt(f"P{i:04d}", [f"P{i + 1:04d}"] if i < n else []) for i in range(1, n + 1)
        ]
        self.assertEqual(check(catalog)["exit_code"], EXIT_CLEAN)
        catalog[-1]["depends_on"] = ["P0001"]
        result = check(catalog)
        self.assertEqual(len(result["untracked_cycles"]), 1)
        self.assertEqual(len(result["untracked_cycles"][0]), n)

    def test_cli_fails_closed_on_malformed_input_without_stdout(self):
        with tempfile.TemporaryDirectory() as tmp:
            cat = Path(tmp) / "catalog.json"
            cat.write_bytes(b"\xff\xfe")
            code, out, err = _run_main(cat, Path(tmp) / "absent.json")
        self.assertEqual(code, EXIT_UNTRACKED_DEFECT)
        self.assertEqual(out, "")
        self.assertTrue(err.startswith("error: "))
        self.assertEqual(err.count("\n"), 1)


class RerunSemanticsTests(unittest.TestCase):
    """P0010: the checker is synchronous and side-effect free, so a duplicate or retried
    run is safe by construction: it re-reads current input and writes nothing."""

    def test_repeated_cli_runs_are_identical_and_write_nothing(self):
        with tempfile.TemporaryDirectory() as tmp:
            cat = _write(
                tmp,
                "catalog.json",
                json.dumps([prompt("P0001", ["P0002"]), prompt("P0002", ["P0001"])]),
            )
            risk = _write(tmp, "risk.json", json.dumps({"entries": []}))
            before = {f.name: f.read_bytes() for f in Path(tmp).iterdir()}
            first = _run_main(cat, risk)
            second = _run_main(cat, risk)
            after = {f.name: f.read_bytes() for f in Path(tmp).iterdir()}
        self.assertEqual(first, second)
        self.assertEqual(before, after)

    def test_rerun_reflects_current_input_not_previous_result(self):
        with tempfile.TemporaryDirectory() as tmp:
            cat = _write(tmp, "catalog.json", json.dumps([prompt("P0001", ["P0999"])]))
            absent = Path(tmp) / "absent.json"
            self.assertEqual(_run_main(cat, absent)[0], EXIT_UNTRACKED_DEFECT)
            _write(tmp, "catalog.json", json.dumps([prompt("P0001")]))
            self.assertEqual(_run_main(cat, absent)[0], EXIT_CLEAN)


class HealthStateTests(unittest.TestCase):
    """P0012: each documented health state (GOVERNANCE_RUNTIME_SCOPE.md) is observable
    from exit code + stdout/stderr alone, without parsing itemized prose."""

    CYCLE = [prompt("P0001", ["P0002"]), prompt("P0002", ["P0001"])]

    def _state(self, catalog_content, risk_content=None):
        with tempfile.TemporaryDirectory() as tmp:
            cat = Path(tmp) / "catalog.json"
            if catalog_content is not None:
                cat.write_text(catalog_content)
            risk = Path(tmp) / "risk.json"
            if risk_content is not None:
                risk.write_text(risk_content)
            return _run_main(cat, risk)

    def test_healthy(self):
        code, out, err = self._state(json.dumps([prompt("P0001")]), json.dumps({"entries": []}))
        self.assertEqual((code, err), (EXIT_CLEAN, ""))
        self.assertEqual(out, "1 prompts checked.\nResult: clean.\n")

    def test_healthy_without_risk_register_says_so(self):
        code, out, err = self._state(json.dumps([prompt("P0001")]))
        self.assertEqual((code, err), (EXIT_CLEAN, ""))
        self.assertIn("No RISK_REGISTER.json", out)

    def test_degraded_only_tracked_cycles(self):
        risk = json.dumps({"entries": [{"status": "open", "affected_prompt_range": ["P0001", "P0002"]}]})
        code, out, err = self._state(json.dumps(self.CYCLE), risk)
        self.assertEqual((code, err), (EXIT_ONLY_TRACKED_CYCLES, ""))
        self.assertTrue(out.endswith("Result: only already-tracked cycles present. Not a fresh failure.\n"))

    def test_failed_untracked_defect(self):
        code, out, err = self._state(json.dumps(self.CYCLE), json.dumps({"entries": []}))
        self.assertEqual((code, err), (EXIT_UNTRACKED_DEFECT, ""))
        self.assertIn("UNTRACKED CYCLE (2 prompts): P0001 .. P0002", out)
        self.assertTrue(out.endswith("Result: untracked structural defect(s) present.\n"))

    def test_malformed_input(self):
        for catalog, risk in (("{broken", None), (json.dumps([prompt("P0001")]), "{broken")):
            code, out, err = self._state(catalog, risk)
            self.assertEqual((code, out), (EXIT_UNTRACKED_DEFECT, ""))
            self.assertRegex(err, r"^error: .* is not valid JSON: .*\n$")

    def test_unavailable_catalog(self):
        code, out, err = self._state(None)
        self.assertEqual((code, out), (EXIT_UNTRACKED_DEFECT, ""))
        self.assertRegex(err, r"^error: cannot read .*\n$")


class RealCatalogSmokeTest(unittest.TestCase):
    """Documents, rather than hides, the current known state of the real repo data.

    The former P0441-P0580 cycle (RISK-0001, found by P0003) was eliminated by dropping
    the redundant P0580 dependency from tracks 23-25; see RISK_REGISTER.json#RISK-0001,
    whose status is now "resolved". This test documents the clean state that replaced it
    and must not regress to expecting that cycle again.
    """

    def test_real_catalog_is_fully_clean_with_no_known_cycle(self):
        catalog = load_catalog()
        risk_register = load_risk_register()  # RISK_REGISTER.json now present, RISK-0001 resolved
        result = check(catalog, risk_register=risk_register)
        self.assertEqual(result["prompt_count"], 720)
        self.assertEqual(result["missing_targets"], [])
        self.assertEqual(result["self_dependencies"], [])
        self.assertEqual(result["untracked_cycles"], [])
        self.assertEqual(result["tracked_cycles"], [])
        self.assertEqual(result["exit_code"], EXIT_CLEAN)
        self.assertEqual(check_result_contract_violations(result), [])


if __name__ == "__main__":
    unittest.main()
