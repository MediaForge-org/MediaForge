# Claude execution protocol

For each numbered prompt:

1. Verify working tree and current branch.
2. Read `GLOBAL_RULES_SHORT.md`.
3. Read only the prompt's required docs and source paths.
4. Restate a short implementation plan in the work log/response; do not ask unnecessary clarification questions when the prompt already defines the target.
5. Implement **only** the current prompt's scope.
6. Add/update focused tests.
7. Run the exact validation commands appropriate to the changed subsystem.
8. If a test fails because of an unrelated pre-existing defect, prove that with evidence and do not hide it.
9. Do not advance unrelated roadmap features.
10. End with:
   - files changed
   - schema/migration changes
   - commands/tests run and results
   - behavior now guaranteed
   - known limitations/blockers
   - whether the next prompt is ready
11. No push/tag/release unless explicitly authorized by the user.

If the prompt is blocked by a missing prerequisite, stop after documenting the blocker and smallest prerequisite fix. Do not invent a different architecture to bypass the dependency.
