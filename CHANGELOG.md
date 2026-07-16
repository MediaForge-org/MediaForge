# Changelog

All notable changes to MediaForge are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows
[Semantic Versioning](https://semver.org/). Entries are generated from
[Conventional Commits](https://www.conventionalcommits.org/).

## [Unreleased]

Targeting the first tagged pre-release **`v0.2.0-alpha.1`** (V1 local core, alpha —
not production-ready). See [docs/MediaForge/V1_READINESS.md](docs/MediaForge/V1_READINESS.md).

### Added — V1 local core (alpha)

- **V1 A — Auth**: local session authentication (login, register, POST-only logout),
  protected routes, roles/policies foundation.
- **V1 B — App shell**: authenticated layout, dashboard, settings foundation, Windows/Docker
  production-build runtime stability.
- **V1 C — Connectors**: Jellyfin & Audiobookshelf connector configuration with encrypted
  secret store and on-demand `testConnection()` diagnostics.
- **V1 D — Library discovery**: discover libraries a connector exposes; select libraries for a
  later sync (library-level metadata only, no media items).
- **V1 E — Premium UI/UX**: design system, switchable design presets, light/dark themes, and a
  large-screen layout.
- **V1 F — Sync Foundation**: dry-run sync runs with per-library plan, run history, and a `/sync`
  page. Dry run only — no import, no file operations.
- **V1 G — Review Center**: central `/review` page aggregating review tasks and connector/sync
  health, with dismiss/reopen for connector-sync tasks and a dashboard summary.
- **V1 H — Final hardening + readiness**: refreshed README and phase/runtime docs, verified
  `.env.example`, navigation/security review, readiness documentation, and green quality gates.

### Foundation (V0)

- Laravel 12 / React + Inertia + TypeScript + Tailwind v4 application skeleton.
- Modular-monolith structure (`app/{Core,Modules,Connectors,Http}`) with
  architecture boundary tests.
- Docker: multi-stage production image, dev + production Compose stacks, Makefile.
- 12-factor environment configuration for the future official Docker image.

### Security

- Connector API tokens are stored only in the encrypted DB secret store; they are never sent to
  the frontend, never placed in Inertia props/DOM, and are masked in audit logs and review evidence.
- Audit logging and review-task evidence are sanitized; no raw remote API responses are stored.
- All state-changing routes are POST-only (including logout); CSRF stays enabled for real requests.
- No network calls occur while rendering pages — health/sync data is read from stored state only.
