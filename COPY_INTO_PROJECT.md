# Copy into the existing MediaForge repository

This archive is **repo-relative**. Copy/extract its contents directly into the existing MediaForge root.

Target example:

```text
/mnt/Festplatte/Schreibtisch/Projekte/MediaForge
```

Recommended safe overlay:

```bash
rm -rf /tmp/mediaforge-update
mkdir -p /tmp/mediaforge-update
unzip -q MediaForge_full_docs_UI_720_prompts_AI3D_plugins_2026-08-16.zip -d /tmp/mediaforge-update
rsync -av --itemize-changes /tmp/mediaforge-update/ /mnt/Festplatte/Schreibtisch/Projekte/MediaForge/
```

Then verify:

```bash
cd /mnt/Festplatte/Schreibtisch/Projekte/MediaForge
git status --short
git diff --stat
git diff --check
find docs/MediaForge/prompts -type f -regextype posix-extended -regex '.*/P[0-9]{4}_.+\.md' | wc -l
```

Expected prompt count: **720**.

`CURRENT_PHASE.md` is intentionally not part of this prepared archive, so the live project progress file is not overwritten.

Important: the live repository may already contain generated Track-01 artifacts such as `GOVERNANCE_DOMAIN_MODEL.md`, `GOVERNANCE_BOUNDARIES.md`, `RISK_REGISTER.json` and the P0005 dependency checker. This overlay does not remove them. After copying, run the live dependency checker if present; the updated prompt catalog should be acyclic.
