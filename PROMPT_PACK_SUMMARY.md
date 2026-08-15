# MediaForge Claude prompt pack summary

- **720 detailed numbered prompts** (`P0001`–`P0720`)
- **36 tracks**
- **20 prompts per track**
- Each prompt contains:
  - explicit dependencies
  - priority P0/P1/P2
  - minimal required docs
  - targeted source paths
  - exact UI images where relevant
  - subsystem-specific architectural rule
  - exact work scope
  - non-goals
  - acceptance criteria
  - validation/test expectations
  - completion/handoff format
- Claude is explicitly instructed not to read all MediaForge documentation for each task.
- The full MediaForge docs remain the product specification; `docs/MediaForge/prompts/` is the execution layer.

Start with `PASTE_TO_CLAUDE_PROMPT_SYSTEM.md`.
