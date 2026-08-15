# P0100 — React Router, human-readable routing and removal of Inertia: gate

**Track:** 05-react-router-urls  
**Priority:** P0  
**Prompt position in track:** 20/20  
**Depends on:** P0099

## Objective

Run the subsystem gate: verify architecture, tests, migrations, UX and no regression before moving on.

This is a deliberately narrow step inside **React Router, human-readable routing and removal of Inertia**. The goal is to make one verifiable increment while keeping the rest of MediaForge stable.

## Context budget — read only what is required

First read:
- `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
- `docs/MediaForge/prompts/CONTEXT_ROUTING.md`

Then read these required documents only:
- `docs/MediaForge/architecture/routing-and-public-urls.md`
- `docs/MediaForge/adr/0015-human-readable-routing.md`
- `docs/MediaForge/architecture/unified-application.md`

Inspect these source paths/symbol neighborhoods first:
- `apps/web`
- `apps/server`
- `routes`

### UI references for this prompt
- None for this prompt unless a changed screen directly requires an existing design-system reference.

Do **not** recursively open every document linked from the required reads. If a concrete ambiguity remains, use `CONTEXT_ROUTING.md` to open the smallest authoritative document/section that resolves it.

## Subsystem-specific rule

The end state is React Router + real API. Do not retain Inertia as a permanent hidden dependency. Human-readable routes are product contracts; internal API identity remains ULID-based.

## Exact work for this prompt

1. Inspect the existing implementation specifically for **React Router, human-readable routing and removal of Inertia** and the current focus **gate**.
2. Keep these subsystem deliverables in view: route tree, slug resolver, legacy redirect layer, Inertia removal inventory.
3. Run the subsystem gate: verify architecture, tests, migrations, UX and no regression before moving on.
4. Preserve already-working V1/V2 behavior unless this prompt explicitly replaces it with the documented target architecture.
5. Do not implement the next focus or a later feature just because you notice it while editing.

## Expected deliverables

The implementation/report for this prompt should address the relevant subset of:
- route tree
- slug resolver
- legacy redirect layer
- Inertia removal inventory

Do not create placeholder abstractions that have no immediate use in this prompt unless the target architecture explicitly requires the seam now.

## Non-goals

- Do not read the full repository documentation tree.
- Do not start the next numbered prompt.
- Do not redesign or refactor unrelated subsystems.
- Do not push, tag or publish a release.

## Acceptance criteria

- [ ] Focused tests and broader relevant suites pass.
- [ ] No known architecture violation is left undocumented.
- [ ] The next track/prompt is explicitly declared ready or blocked with reasons.
- [ ] Existing relevant behavior outside this prompt remains working.
- [ ] New code follows the target responsibility boundaries rather than adding another temporary permanent architecture.
- [ ] No secrets/private user data are added to the repository.

## Testing / validation

Use the smallest relevant commands for the files changed. At minimum:
1. run focused unit/integration tests for the modified subsystem;
2. run static/type/format checks appropriate to the changed language(s);
3. run a broader build/test gate if the change affects startup, routing, contracts, database migrations or shared packages;
4. if UI behavior changed, validate loading, empty, error and responsive states and run the applicable browser/E2E check;
5. if a migration changed, prove both fresh setup and upgrade-path behavior where practical.

Do not claim a test passed unless you actually ran it. Record exact commands and results.

## Completion response

End your response with:
- **Changed files**
- **Data/schema changes**
- **Contracts/API changes**
- **Tests run + results**
- **Behavior guaranteed after P0100**
- **Known limits / blockers**
- **Ready for next prompt?** yes/no, with reason

Stop after this prompt. Do not automatically execute the next numbered prompt.
