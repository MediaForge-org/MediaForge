# Paste this to Claude when you want it to understand the MediaForge UI direction

Read these files before any significant UI implementation or redesign:

- `docs/MediaForge/ui-ux/UI_IMPLEMENTATION_PROMPT.md`
- `docs/MediaForge/ui-ux/SCREEN_REFERENCE_INDEX.md`
- `docs/MediaForge/ui-ux/reference/README.md`
- `docs/MediaForge/ui-ux/reference-expanded/README.md`

Then inspect **every PNG** in both folders:

- `docs/MediaForge/ui-ux/reference/`
- `docs/MediaForge/ui-ux/reference-expanded/`

Important rules:
1. The screenshots are not optional inspiration; they are binding visual references.
2. Do not just look at the images. Also follow the written design prompt in `UI_IMPLEMENTATION_PROMPT.md`.
3. Before implementing a screen, say which reference images are relevant and what visual traits you will preserve.
4. Keep one coherent MediaForge design system across movies/TV, audiobooks, disc/ISO, private/adult mode, metadata tools, sync/queue tools, analytics and audio enhancement.
5. The UI must feel premium and media-first, significantly better-looking than stock Stash, and must not collapse into a generic admin dashboard.
6. Adult/private mode must stay hidden from the normal family-facing home experience and only appear behind private mode access.

For the next response, first summarize the design system you infer from these files and images, then explain how you will apply it to the current implementation task before you start coding.
