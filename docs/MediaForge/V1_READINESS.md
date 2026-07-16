# MediaForge V1 Readiness

Status date: **2026-07-16**

## Summary

MediaForge **V1 (local core)** is feature-complete across packages **V1 A–H** and passes every
local quality gate. It is **local alpha software — not production-ready**. It performs no media
import, no file operations, and no automatic sync.

## Completed V1 packages

| Package | Scope | Status |
|---|---|---|
| V1 A | Auth: login / register / logout (POST-only), protected routes | ✅ complete |
| V1 B | App shell: authenticated layout, dashboard, settings foundation, runtime stability | ✅ complete |
| V1 C | Connector configuration + `testConnection()` (Jellyfin, Audiobookshelf) | ✅ complete |
| V1 D | Library discovery + library selection for later sync | ✅ complete |
| V1 E | Premium UI/UX, design presets, large-screen layout | ✅ complete |
| V1 F | Sync Foundation — dry run, run history, `/sync` | ✅ complete |
| V1 G | Review Center + health foundation, `/review` | ✅ complete |
| V1 H | Final hardening, docs, `.env.example`, navigation/security review, readiness | ✅ complete |

## Validation gates

All gates must be green for release readiness. Run locally:

```bash
# Frontend
docker compose -f deploy/dev/docker-compose.yml run --rm vite npm run type-check
docker compose -f deploy/dev/docker-compose.yml run --rm vite npm run build

# Backend (hermetic: APP_ENV=testing, DB=mediaforge_test — pinned by tests/bootstrap.php)
make test
docker compose -f deploy/dev/docker-compose.yml exec -T app php vendor/bin/pint --test
docker compose -f deploy/dev/docker-compose.yml exec -T app php vendor/bin/phpstan analyse --memory-limit=512M

# Targeted suites
make test  # or: php artisan test --filter=Auth|Dashboard|Connector|Sync|Review|Settings
```

Latest local run (2026-07-16): Pest **all green**, Pint clean, PHPStan (max) clean, TypeScript
type-check clean, Vite build succeeds.

## Security baseline (verified)

- Connector API tokens live only in the encrypted DB secret store; never rendered to the frontend,
  never in Inertia props/DOM, masked in audit logs and review evidence.
- Audit and review-task evidence are sanitized; no raw remote API responses are stored.
- CSRF stays enabled for real requests; the hermetic test harness does not weaken it for the app.
- All state-changing routes are POST-only, including logout. No GET route changes state.
- No network calls occur while rendering pages; health/sync data is read from stored state only.
- Connector base URLs are validated on save.
- `.env`, `public/build/`, and `public/hot` are git-ignored and untracked; `.env.example` carries no
  real secrets. `APP_DEBUG=false` and `APP_ENV=production` are the shipped defaults.

## Known issues

- **Local alpha only** — not hardened for public/internet exposure. Run on a trusted local network.
- **Windows/Docker runtime** — use the default production-build mode. The Vite HMR server can stall
  on Windows bind mounts (`make runtime-reset` recovers). Because OPcache timestamp validation is
  disabled, recreate the `app` container after PHP changes so the running server sees new code.
- **Method-mismatch requests** (e.g. `GET` on a POST-only route) can hang on the local dev web
  server instead of returning `405`. Dev-server quirk, not an app regression — the suite confirms
  state-changing routes are POST-only.

## Not production ready

MediaForge V1 is an early local alpha. There is no upgrade/migration guarantee between alphas, no
authentication hardening for internet exposure, and no real media/sync behavior. Do not run it as a
production service.

## Release recommendation

**Release candidate: YES — as a GitHub pre-release only.**

- Recommended tag: **`v0.2.0-alpha.1`**
- Release type: **pre-release** (never a stable/latest release)
- Rationale: V1 A–H are complete, all local gates are green, docs are accurate, no known blockers for
  a local-alpha pre-release. It is explicitly documented as not production-ready.

Do **not** create the release or tag automatically. Recommended (not executed) sequence after
`main` is committed, pushed, and GitHub CI is green:

```bash
git switch main
git pull
git branch v1-h-readiness-complete
git push origin v1-h-readiness-complete

git tag v1-h-readiness
git push origin v1-h-readiness
```

The recovery branch is a snapshot only; development continues on `main`.
