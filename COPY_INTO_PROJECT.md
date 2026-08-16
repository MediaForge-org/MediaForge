# Copy the 2026-08-17 MediaForge update into the repository root

The archives are **repo-relative**: they do not contain an extra `MediaForge/` wrapper directory. Extract/copy their contents directly into:

```text
/mnt/Festplatte/Schreibtisch/Projekte/MediaForge
```

## Recommended: use the ROOT UPDATE archive

```bash
cd /mnt/Festplatte/Schreibtisch/Projekte/MediaForge

rm -rf /tmp/mediaforge-2026-08-17-update
mkdir -p /tmp/mediaforge-2026-08-17-update

unzip -q \
  /mnt/Festplatte/Downloads/MediaForge_ROOT_UPDATE_managed_upstreams_acquisition_i18n_2026-08-17.zip \
  -d /tmp/mediaforge-2026-08-17-update

rsync -av --itemize-changes \
  /tmp/mediaforge-2026-08-17-update/ \
  /mnt/Festplatte/Schreibtisch/Projekte/MediaForge/

rm -rf /tmp/mediaforge-2026-08-17-update
```

If Firefox saved the ZIP to `~/Downloads`, replace the ZIP path accordingly.

## Verification

```bash
cd /mnt/Festplatte/Schreibtisch/Projekte/MediaForge

git status --short
git diff --stat
git diff --check

find docs/MediaForge/prompts \
  -type f \
  -regextype posix-extended \
  -regex '.*/P[0-9]{4}_.+\.md' \
  | wc -l

python3 tools/prompts/check_dependency_graph.py
```

Expected numbered prompt count: **720**.
Expected dependency checker result in the live repo: **clean / exit 0**.

## Important preservation rules

`CURRENT_PHASE.md` is intentionally not part of these prepared archives.

The root update also does **not** replace live Track-01 runtime/governance artefacts that were created after the previous package, including `RISK_REGISTER.json` and the P0005 Python validator/tests. Your already-resolved `RISK-0001` and corrected smoke test therefore remain intact.
