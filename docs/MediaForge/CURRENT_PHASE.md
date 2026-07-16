# MediaForge Current Phase

## Current status

**V1 (local core, alpha) is complete. V2 has begun with Package A — read-only connector
catalog snapshots.**

V1 was delivered as eight focused packages (A–H). The application runs locally from a stable
production build, and all local gates (Pint, PHPStan max, Pest, TypeScript type-check, Vite build)
are green.

MediaForge remains **local alpha software — not production-ready**. It performs **no media import**,
**no file operations**, and **no automatic sync or background snapshots**.

## Confirmed stack

- Backend: Laravel 12, PHP 8.4
- Frontend: React 19 + Inertia.js + TypeScript, Tailwind CSS v4
- Database: PostgreSQL 17 (pg_trgm, btree_gist, pgvector)
- Cache/Queue: Redis 7
- Dev environment: Docker / Docker Compose
- Tests: Pest (incl. architecture boundary tests)
- Static analysis: PHPStan (max level)
- Formatting: Pint

Vue is not the frontend stack and must not be reintroduced.

## V1 packages — completed

- **V1 A — Auth**: login, register, logout (POST-only), protected routes, roles/policies foundation.
- **V1 B — App shell**: authenticated layout, dashboard, settings foundation, runtime stability.
- **V1 C — Connectors**: connector configuration + `testConnection()` for Jellyfin & Audiobookshelf.
- **V1 D — Library discovery**: discover libraries a server exposes; select libraries for later sync.
- **V1 E — Premium UI/UX**: design system, design presets, large-screen (2560×1600) layout.
- **V1 F — Sync Foundation**: dry-run sync runs, per-library plan + run history, `/sync` page.
- **V1 G — Review Center**: central review tasks + health foundation, dismiss/reopen, `/review` page.
- **V1 H — Final hardening + readiness**: README/docs refresh, `.env.example`, runtime docs,
  navigation/security review, readiness documentation, all gates green.

See [V1_READINESS.md](V1_READINESS.md) for the readiness checklist and release recommendation.

## V2 packages

- **V2 A — Read-only connector catalog snapshots** *(current)*: explicitly triggered, bounded,
  read-only snapshots of a connector library. External items are captured as a **connector
  read-model** (`connector_catalog_items`) with snapshot run history
  (`connector_catalog_snapshot_runs`), surfaced on a new `/catalog` page plus the dashboard,
  connector overview and connector detail. Snapshot problems raise deduplicated
  `connector_catalog` review tasks and write sanitized audit entries.

### V2 A boundaries and limits

- **Read-only.** A snapshot READS external items and stores them for display. It is **not** an
  import: it creates no `media_items`, `media_editions` or `media_files`, performs **no file
  operations**, changes nothing on Jellyfin/Audiobookshelf, and starts no remote scans.
- **Explicit only.** A snapshot runs only on an explicit `POST` from a connector detail page.
  There is no automatic, scheduled or background snapshot.
- **Bounded.** Each run captures at most **500 items** (`RunConnectorCatalogSnapshot::ITEM_LIMIT`).
  If the library holds more, the run is marked `truncated`, raises a `snapshot_truncated` warning
  review task, and the UI says larger paginated snapshots arrive later.
- **Never rendered from the network.** All catalog pages read stored state only.
- **Vanished items are flagged, never deleted** (`is_present=false` + `missing_since`); a *failed*
  snapshot never flags or wipes previously captured items.

### Known V2 A limitations

- No pagination beyond the 500-item limit (a truncated snapshot captures the first page only).
- No per-library or per-connector catalog detail pages yet (`/catalog` is the single overview).
- Only `title`/`kind`/`year`/`index`/`runtime`-level fields are captured — no artwork, no
  descriptions, no file paths, no raw API payloads.

## What V1/V2 A deliberately does NOT include

Real media imports · media items / editions / files · file operations (copy/move/delete/rename) ·
real or automatic sync (dry run only) · automatic/background snapshots · metadata-merge or
enrichment engine · download engine · disc/ISO/AV1 pipeline · fork integration · admin UI · profile
management · role management UI · password reset / email verification · adult engine · AI engine ·
plugin engine · mobile/desktop app.

## Known issues

- **Local alpha only** — not hardened for public/internet exposure.
- **Windows/Docker runtime** — use the production-build mode (default). The Vite HMR server can stall
  on Windows bind mounts; `make runtime-reset` recovers. Because the dev/production PHP overlay keeps
  OPcache timestamp validation disabled, recreate the `app` container after changing PHP files
  (`docker compose -f deploy/dev/docker-compose.yml up -d --force-recreate --no-deps app`) so the
  running web server picks up the new code.
- **Method-mismatch requests** (e.g. issuing `GET` to a POST-only route) can hang on the local dev
  web server instead of returning `405`. This is a dev-server quirk, not an app regression; the test
  suite confirms state-changing routes are POST-only.

## Recommended next step

1. Keep all local gates green, commit, push `main`, confirm GitHub CI green.
2. **V2 B — paginated catalog snapshots**: lift the 500-item bound with real pagination and a
   resumable job, plus per-connector/per-library catalog detail views. Still read-only.
3. Later V2: read-only content strips (recently added / continue watching) on the dashboard —
   still no writes to media servers, no imports, no file operations.

## Rules for AI coding agents

- Do not read the full multi-MB master prompt unless explicitly needed; this file plus
  [V1_READINESS.md](V1_READINESS.md) is the source of truth for the active phase.
- Work in small packages. Do not commit, push, or create releases/tags automatically.
- After each package, report changed files, validation results, open issues, and a recommended
  (not executed) commit command.
