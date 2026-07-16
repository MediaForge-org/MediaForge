# MediaForge Current Phase

## Current status

**MediaForge V1 (local core, alpha) is feature-complete and in final hardening.**

V1 was delivered as eight focused packages (A–H). The application runs locally from a stable
production build, and all local gates (Pint, PHPStan max, Pest, TypeScript type-check, Vite build)
are green.

V1 is **local alpha software — not production-ready**. It performs no media import, no file
operations, and no automatic sync.

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
- **V1 H — Final hardening + readiness** *(this package)*: README/docs refresh, `.env.example`,
  runtime docs, navigation/security review, readiness documentation, all gates green.

See [V1_READINESS.md](V1_READINESS.md) for the readiness checklist and release recommendation.

## What V1 deliberately does NOT include

Real media imports · media items / editions · file operations (copy/move/delete) · real or automatic
sync (dry run only) · metadata/enrichment engine · download engine · disc/ISO/AV1 pipeline · fork
integration · admin UI · profile management · role management UI · password reset / email
verification · adult engine · AI engine · plugin engine · mobile/desktop app.

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

1. Finish V1 H (this package), keep all local gates green, commit, push `main`, confirm GitHub CI green.
2. Prepare a **V1 Release Candidate** as a GitHub **pre-release** (recommended tag `v0.2.0-alpha.1`)
   — do not publish a stable release.
3. Begin **V2 — Connector Suite / read-only data foundations** (still no writes to media servers).

## Rules for AI coding agents

- Do not read the full multi-MB master prompt unless explicitly needed; this file plus
  [V1_READINESS.md](V1_READINESS.md) is the source of truth for the active phase.
- Work in small packages. Do not commit, push, or create releases/tags automatically.
- After each package, report changed files, validation results, open issues, and a recommended
  (not executed) commit command.
