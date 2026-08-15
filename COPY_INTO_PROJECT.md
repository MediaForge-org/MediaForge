# MediaForge Docs Overlay — Copy Instructions

Dieses ZIP ist ein **Overlay**, kein vollständiger Repository-Snapshot.

## Empfohlen

Im MediaForge-Repo zuerst einen lokalen Sicherungsstand/Branch erstellen.

Dann den Inhalt dieses Ordners **in die Repository-Wurzel** kopieren, sodass z. B.:

`docs/MediaForge/MediaForge_Master_Engineering.md`

die vorhandene Datei ersetzt.

Linux-Beispiel:

```bash
cd /pfad/zu/MediaForge
cp -a /pfad/zum/entpackten/mediaforge_docs_overlay/. .
git status
git diff -- README.md docs/MediaForge
```

Danach **nicht blind committen**. Erst die Diffs lesen.

## Danach Claude sagen

> Lies zuerst `docs/MediaForge/CLAUDE_IMPLEMENTATION_DIRECTIVE.md`, danach `docs/MediaForge/MediaForge_Master_Engineering.md`, `docs/MediaForge/PRODUCT_DECISIONS_2026-08.md`, `docs/MediaForge/roadmap.md` und `docs/MediaForge/CURRENT_PHASE.md`.
> Diese Dokumente definieren die neue langfristige Richtung. `CURRENT_PHASE.md` definiert weiterhin den echten Implementierungsstand. Implementiere keine spätere Phase vorzeitig. Prüfe die Dokumente zunächst auf interne Widersprüche mit dem existierenden Code und berichte sie, bevor du Änderungen am Code machst.

## Hinweis

Die vier UI-Bilder sind Designreferenzen mit neutralen Platzhaltern. Sie gehören unter:

`docs/MediaForge/ui-ux/reference/`


## UI reference expansion (2026-08 update)

This overlay now also includes an expanded UI reference pack under `docs/MediaForge/ui-ux/reference-expanded/` together with two written Claude-facing UI spec files:

- `docs/MediaForge/ui-ux/UI_IMPLEMENTATION_PROMPT.md`
- `docs/MediaForge/ui-ux/SCREEN_REFERENCE_INDEX.md`

Do not rely on screenshots alone. Claude should read the written UI prompt and the screen index, then inspect the reference PNG files.

## After copying this detailed revision

Run:

```bash
git status
git diff --check
git diff --stat
```

This overlay intentionally changes architecture/product documentation. It does **not** change `CURRENT_PHASE.md`; that file must remain the real implementation truth until Claude implements the corresponding phases.

## 720-prompt Claude execution layer

This package adds `docs/MediaForge/prompts/` containing 720 detailed prompts. The prompt system is designed so Claude reads only the context listed inside the currently authorized prompt.

After copying the overlay into the repository, start Claude with:

`PASTE_TO_CLAUDE_PROMPT_SYSTEM.md`

Do not paste all 720 prompts into one conversation message. Give Claude one prompt ID/file at a time.

`docs/MediaForge/CURRENT_PHASE.md` is intentionally not supplied by this overlay and must remain the existing repository copy; the first governance prompts read that live file to determine the real implementation baseline.
