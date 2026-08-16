#!/usr/bin/env python3
"""Tests for check_dependency_graph.py. Stdlib unittest only, run with:
    python3 -m unittest tools/prompts/test_check_dependency_graph.py -v
"""
import unittest

from check_dependency_graph import (
    EXIT_CLEAN,
    EXIT_ONLY_TRACKED_CYCLES,
    EXIT_UNTRACKED_DEFECT,
    check,
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


if __name__ == "__main__":
    unittest.main()
