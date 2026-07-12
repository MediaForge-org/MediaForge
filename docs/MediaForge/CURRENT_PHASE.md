# MediaForge Current Phase

## Current status

MediaForge V0 is complete.

GitHub CI is green:

- Backend: Pint, PHPStan, Pest
- Frontend: type-check and build

The application runs locally and the React/Inertia V0 foundation page is visible.

## Current phase

Current phase: V1

V1 may begin.

V1 must remain small and focused.

## Confirmed stack

- Backend: Laravel 12
- PHP: 8.4
- Frontend: React + Inertia.js + TypeScript
- Styling: Tailwind/CSS foundation
- Database: PostgreSQL
- Cache/Queue: Redis
- Dev environment: Docker / Docker Compose
- Tests: Pest
- Static analysis: PHPStan max
- Formatting: Pint

Vue is no longer the target frontend stack.

Vue must not be reintroduced.

## V0 completed

Completed V0 work:

- Repository foundation is working.
- GitHub CI is green.
- PHP baseline is 8.4.
- Composer lockfile is synchronized.
- React/Inertia/TypeScript replaced the previous Vue foundation.
- `resources/js/app.tsx` is the active frontend entry.
- `@viteReactRefresh` is present in the Inertia Blade layout.
- Dev OPcache timestamp validation is enabled only for the dev stack.
- Root and dev Docker ports were aligned:
  - MediaForge: 8100
  - Jellyfin optional: 8110
  - Audiobookshelf optional: 13380
- `.env.example` was updated.
- `.ai/` is ignored and used only for local AI context.
- `tests/Unit/.gitkeep` keeps the Unit test directory in Git.
- Smoke tests use `withoutVite()` so Backend CI does not require a frontend manifest.
- V0 documentation was aligned with React and the V0–V34 roadmap.

## Important local workflow note

On Windows Docker bind mounts, `npm run build` should not be executed inside the long-running Vite HMR container.

Builds should be run outside the HMR process or in a clean one-off environment.

The code is valid; this is a Windows/Docker workflow note, not a V0 blocker.

## Current V1 goal

V1 goal:

Build the first local core app foundation.

V1 should include only:

- Login
- Logout
- protected routes
- basic authenticated layout
- dashboard placeholder
- settings placeholder
- role/policy foundation
- connector configuration foundation
- Jellyfin `testConnection()` only
- Audiobookshelf `testConnection()` only
- basic V1 tests
- minimal documentation update

## V1 non-goals

Do not build these in V1:

- Mobile app
- Desktop app
- Adult engine
- Download engine
- Disc/Blu-ray engine
- AI engine
- Plugin system
- Codec engine
- Online provider search
- Metadata graph
- Fork integration
- Full dashboard
- Full settings engine
- Full connector sync
- Large UI redesign

## Rules for AI coding agents

When using Codex or Claude:

- Do not read the full 5-MB master prompt unless explicitly needed.
- Use `.ai/MEDIAFORGE_MASTER_PROMPT.md` only as long-term context.
- Use this file as the current source of truth for the active phase.
- Work in small packages.
- Do not commit automatically.
- Do not push.
- Do not create releases.
- After each package, report:
  - changed files
  - validation results
  - open issues
  - recommended commit command

## Recommended next step

Next Codex task:

Analyze V1 starting point only.

Do not change code yet.

Check:

- existing routes
- existing User model
- role enum / policy foundation
- LoginRequest
- existing React pages
- existing layouts/components
- existing tests

Then propose the smallest safe V1 Package A for login/logout.