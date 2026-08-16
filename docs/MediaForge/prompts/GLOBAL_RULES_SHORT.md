# Global rules — short context loaded for every numbered prompt

These rules are intentionally short. Do not replace them by rereading the whole master specification every time.

1. **One product / one visible UI.** MediaForge is the user-facing application. Jellyfin-, Stash- and Audiobookshelf-derived components are internal engines.
2. **Target monorepo.** `apps/`, `engines/`, `services/`, `packages/`, `platform/`, `tests/`, `tools/`, `docs/` are the long-term responsibility boundaries.
3. **Web target.** React 19 + TypeScript + React Router **Framework Mode** + Vite + real MediaForge API. Inertia is transitional; do not introduce Next.js as a second Full-Stack server without a new ADR.
4. **Server.** Laravel/PHP is the control plane/BFF/domain orchestration layer; it must not proxy large media bytes unnecessarily or reimplement specialist engines.
5. **Canonical DB.** PostgreSQL is the MediaForge source of truth. Engine/provider IDs belong in mapping tables, not as canonical identities.
6. **Engine isolation.** No direct cross-engine DB coupling. Communicate through versioned contracts/capabilities/events.
7. **Native tooling.** Prefer Rust for new MediaForge-native media tooling; reuse mature FFmpeg/libbluray/native libraries instead of rewriting codecs.
8. **AI.** Python is appropriate for ML inference/training. Heavy AI/3D is optional and must never be a Core hard dependency. Outputs retain model/version/license/evidence/confidence; large artifacts live outside PostgreSQL.
9. **Media identity.** Work/MediaItem != Edition != File. Episode != File. Scene != File. Audiobook Work != Edition != Chapter != AudioFile.
10. **Provenance.** External data is evidence/source material, not canonical truth. Manual overrides survive sync.
11. **Adult privacy.** Adult may use readable `/adult/...` URLs by default; optional Strict Private URLs may use opaque routes. While locked, adult content must not leak through UI, API, search, cache, events, notifications, logs or preload behavior.
12. **Adult analysis.** Taxonomy is hierarchical; events can carry attributes and timestamps/evidence. Example: `puke` occurrence may have `consistency=watery|chunky` and `appearance=milky`. Audio events such as `crying`/`screaming` are first-class timeline events.
13. **Disc.** Never auto-map an episode from confidence alone. Verified-only evidence rules apply. No DRM/access-control bypass.
14. **Acquisition.** User-supplied NZB/torrent/magnet and permitted custom sources are supported; MediaForge must not become a piracy search engine. Downloads go through staging/import validation.
15. **User files.** Never rename, move, split, transcode, overwrite or delete original user media without an explicit authorized workflow/choice. Preview the action first when practical.
16. **UI quality.** Use the design system/reference images specified by the current prompt. Do not regress to a generic admin dashboard or stock Stash/Jellyfin look.
17. **Current phase is truth.** Do not mark later roadmap phases complete because docs describe them.
18. **Testing.** Add focused tests for each implemented behavior and run the smallest relevant suite plus broader gates when the prompt says so.
19. **Secrets/privacy.** Never commit `.env`, tokens, cookies, private library data, database dumps or personal credentials.
20. **Git.** Standard workflow is small green increments on `main`; branches are optional for unusually risky experiments. Never commit a known-broken state. No commit/push/tag/release unless explicitly authorized. Before destructive restore/reset, prove no user work will be lost.

21. **Plugins/Themes.** Extension types are explicit and permissioned; themes use design tokens/scoped CSS by default. Advanced global CSS is opt-in.
22. **Adult 3D/Tattoos.** Female performer tattoo coverage is surface-based over the complete body, with versioned anatomy regions and observed/estimated/unknown confidence. 3D reconstruction is optional, revisioned and private.
23. **Derived assets.** Evidence, meshes, textures, model weights and other large generated data belong in the Artifact/Model stores with quotas/GC, not database BLOBs.

## 2026-08-17 global product rules

- MediaForge owns the normal product frontend. Integrated upstream applications are backend capabilities; native upstream UIs are admin/debug fallbacks only.
- Jellyfin/Stash/Audiobookshelf baselines are prepared early and later adapted into internal engines. SAB/qBittorrent/Prowlarr/Sonarr/Radarr/Whisparr remain unmodified managed upstreams unless an explicit later ADR says otherwise.
- Never hard-code a small provider/site whitelist into domain logic when capability adapters (Newznab/Torznab/Prowlarr/plugin/etc.) can express the requirement.
- User-visible product copy must use localisation keys. First-class launch locales are de, en-GB, it, es and fr.
- Metadata translation fallback preserves the original value/provenance and may not fabricate facts or overwrite authoritative localised metadata.
- UI reference artwork is illustrative; production artwork must belong to the canonical matched media item or use a neutral placeholder/review state.
- Numbered prompt IDs remain P0001–P0720; do not create new IDs for these additions.
