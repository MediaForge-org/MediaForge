# Paste this to Claude first — prompt-system bootstrap

The repository now contains a granular MediaForge prompt system under `docs/MediaForge/prompts/`.

For this response **do not implement product code**.

Read only:
1. `docs/MediaForge/prompts/README_START_HERE.md`
2. `docs/MediaForge/prompts/GLOBAL_RULES_SHORT.md`
3. `docs/MediaForge/prompts/CONTEXT_ROUTING.md`
4. `docs/MediaForge/prompts/CLAUDE_EXECUTION_PROTOCOL.md`
5. `docs/MediaForge/prompts/TRACK_INDEX.md`
6. `docs/MediaForge/prompts/PROMPT_ORDER.md` only far enough to locate `P0001`
7. the file for `P0001`

Then verify current git HEAD / working tree and execute **P0001 only**.

Important: there are 720 prompts. You are not supposed to read or execute them all at once. Every prompt explicitly lists the minimum context it needs. Do not preload unrelated Adult, Disc, AI, Acquisition, fork or UI documentation.

After P0001, stop and wait for the next authorized prompt ID.
