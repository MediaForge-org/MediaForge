# MediaForge Claude Prompt System — 720 detailed prompts

This directory is the **execution layer** for Claude. The rest of `docs/MediaForge/` is the complete product specification and long-term source of truth.

## Core rule: do not read everything

Claude must **not** reread the complete MediaForge documentation for each task. Every numbered prompt contains a targeted `Required reads` section. Read those files/sections and inspect only the listed source paths and UI reference images.

Read additional documentation only when:
1. the current prompt explicitly links it; or
2. a concrete ambiguity cannot be resolved from code + required reads.

If additional context is required, read the **smallest authoritative file/section** that resolves that ambiguity. Do not recursively open every linked document.

## Prompt count

- 36 tracks
- 20 prompts per track
- **720 numbered prompts total**

The prompts are intentionally granular. Run one prompt at a time unless the user explicitly authorizes a batch.

## Baseline

This prompt pack was generated after documentation commit `5c9e7ee` (`docs: expand MediaForge architecture features and UI references`). Always inspect current `HEAD` and `CURRENT_PHASE.md`; do not assume the code has remained unchanged.

## Priority

- **P0:** architecture/data-model decisions that should be correct early
- **P1:** core product functionality after foundations are stable
- **P2:** advanced/expensive features that remain in scope but should not block a usable core

Priority does **not** override dependencies or `CURRENT_PHASE.md`.

## Execution

1. Start with `PROMPT_ORDER.md` and `GLOBAL_RULES_SHORT.md`.
2. Give Claude only the next authorized numbered prompt.
3. Claude reads only the prompt's required context.
4. Claude implements only that prompt.
5. Claude runs the prompt's tests/gates and reports results.
6. Do not continue automatically to the next prompt.

No numbered prompt authorizes a push, tag or release unless it explicitly says so. By default Claude must not push/tag and should not commit unless the user explicitly asks.
