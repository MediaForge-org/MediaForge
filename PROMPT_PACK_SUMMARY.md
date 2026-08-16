# MediaForge Prompt Pack Summary — 2026-08-16

- **720 numbered prompts remain exactly 720** (`P0001`–`P0720`).
- 36 tracks × 20 lifecycle prompts.
- New decisions are integrated into existing prompts; no new numbered IDs were added.
- The former dependency cycle across P0441–P0580 is removed by dropping the backwards hard dependency from Tracks 23–25 to P0580. Track 29 now optimizes behind earlier contracts.
- Updated areas: React Router Framework Mode, optional AI/3D, Tattoo Coverage/Anatomy, 3D Reconstruction Revisions, Artifact/Model Stores, Plugin/Theme SDK, Custom CSS, Resource Scheduler, Derived Storage/GC, green-commit workflow.
- Claude must continue reading only the current prompt's Required Reads/UI references.
- `CURRENT_PHASE.md` is intentionally not included/overwritten by this package.

## 2026-08-17 additions

- Prompt count remains exactly **720**; no new `Pxxxx` IDs.
- Jellyfin/Stash/Audiobookshelf baselines move into the early Track-02 monorepo preparation; full cutover remains later.
- SABnzbd/qBittorrent/Prowlarr/Sonarr/Radarr/Whisparr are defined as unmodified managed upstream backends behind MediaForge UX.
- Acquisition adds broad provider adapters, Release Decision/Wanted, deterministic Usenet naming, torrent hardlink/seeding policy, post-processing DAG, ISO episode/extra remux and optional H.264/H.265/AV1 profiles.
- First-class launch locales: de, en-GB, it, es, fr with professional metadata translation fallback and later gradual locale expansion.
- UI references 68–69 added.
