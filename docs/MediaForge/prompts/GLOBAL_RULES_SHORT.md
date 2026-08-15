# Global rules — short context loaded for every numbered prompt

These rules are intentionally short. Do not replace them by rereading the whole master specification every time.

1. **One product / one visible UI.** MediaForge is the user-facing application. Jellyfin-, Stash- and Audiobookshelf-derived components are internal engines.
2. **Target monorepo.** `apps/`, `engines/`, `services/`, `packages/`, `platform/`, `tests/`, `tools/`, `docs/` are the long-term responsibility boundaries.
3. **Web target.** React + TypeScript + React Router + real MediaForge API. Inertia is transitional and should not remain a permanent architecture dependency.
4. **Server.** Laravel/PHP is the control plane/BFF/domain orchestration layer; it must not proxy large media bytes unnecessarily or reimplement specialist engines.
5. **Canonical DB.** PostgreSQL is the MediaForge source of truth. Engine/provider IDs belong in mapping tables, not as canonical identities.
6. **Engine isolation.** No direct cross-engine DB coupling. Communicate through versioned contracts/capabilities/events.
7. **Native tooling.** Prefer Rust for new MediaForge-native media tooling; reuse mature FFmpeg/libbluray/native libraries instead of rewriting codecs.
8. **AI.** Python is appropriate for ML inference/training. AI outputs must retain model/version/evidence/confidence and must not be misrepresented as certain facts.
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
20. **Git.** No push/tag/release by default. Do not silently force-push. Preserve upstream history for future engine forks.
