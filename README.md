# MediaForge

MediaForge is an open-source, local-first **unified media application**.

The long-term product has **one MediaForge interface** for movies, series, music, audiobooks, podcasts, discs and private adult media. During the early engineering phases MediaForge integrates existing Jellyfin and Audiobookshelf installations through connectors. Later phases may bundle and maintain compatible forks/engines behind stable MediaForge interfaces. Users should not need to switch between Jellyfin, Audiobookshelf or Stash web interfaces in the final product.

The current alpha is intentionally much smaller than that target: it is still building the canonical catalog, connector and safety foundations before playback engines, fork integration, Disc/ISO, enhancement engines and Adult are activated.


MediaForge is an open-source, **local-first** enhancement suite that runs *beside* your existing
[Jellyfin](https://jellyfin.org/) and [Audiobookshelf](https://www.audiobookshelf.org/)
installations. It does **not** replace their playback, streaming, transcoding, or library cores —
it adds a safe local control surface for configuring connectors, discovering libraries, and
preparing future sync.

## Long-term product model

```text
MediaForge UI (React + TypeScript)
            |
       MediaForge Core
       PostgreSQL catalog
            |
    +-------+---------+
    |       |         |
 Video   Adult     Audio
 Engine  Engine    Engine
    |       |         |
Jellyfin  Stash-   Audiobookshelf
derived/  derived/ derived/
compatible compatible compatible
```

The engine implementation is deliberately hidden behind MediaForge contracts. The current connector phase is a migration path, not the final visible product boundary. Fork/bundling work remains a later engineering phase so the existing alpha can mature first.

Adult content is a special privacy domain: while Private/Adult Mode is locked, adult routes, navigation, search results, thumbnails, preload requests, activity, history, notifications and API existence must not leak into the normal UI.

## Project status

**V1 — local core (alpha). Not production-ready.**

MediaForge V1 is a usable local foundation, but it is an early alpha meant for local/self-hosted
evaluation only. It performs **no** media import, **no** file operations, and **no** automatic
sync. Do not expose it to the public internet; run it on a trusted local network behind your own
reverse proxy and TLS if you must reach it remotely.

V2 is under way on top of that foundation: a read-only external catalog, normalization, a matching
preview, a reviewable import plan, and — since V2 E — a **database-only internal import** that
turns an approved plan into MediaForge records. It still performs **no file operations** (nothing
is copied, moved, deleted or renamed) and writes **nothing** back to Jellyfin or Audiobookshelf.
See [docs/MediaForge/CURRENT_PHASE.md](docs/MediaForge/CURRENT_PHASE.md) for the active phase.

The delivered V1 packages are tracked in
[docs/MediaForge/V1_READINESS.md](docs/MediaForge/V1_READINESS.md) and
[docs/MediaForge/CURRENT_PHASE.md](docs/MediaForge/CURRENT_PHASE.md).

## What V1 can do

- **Authentication** — local session auth (login, register, logout) with roles/policies foundation.
- **Premium UI / design presets** — a polished React + Inertia shell with per-user light/dark themes
  and switchable design presets, laid out for large screens.
- **Dashboard** — a workspace overview with connector health, sync foundation, and review summary.
- **Settings foundation** — read-only overview of typed application settings.
- **Connector configuration** — add and configure Jellyfin and Audiobookshelf connectors; API tokens
  are stored encrypted in a secret store and are never rendered back to the browser.
- **`testConnection()`** — validate a connector's URL + credentials against the live server on demand
  (Jellyfin `X-Emby-Token` / Audiobookshelf `Bearer`), storing only a sanitized health status.
- **Library discovery** — list the libraries each configured server exposes (library-level metadata
  only — no media items).
- **Library selection** — mark libraries as selected for a *later* sync.
- **Sync Foundation — dry run** — inspect the stored discovery/health state and produce a per-library
  plan and run history. **Dry run only. Nothing is imported, moved, copied, or deleted.**
- **Review Center** — a central place that surfaces open review tasks, connector health, and dry-run
  warnings, with safe next-step guidance; connector-sync tasks can be dismissed/reopened.
- **Health foundation** — connector and sync health computed entirely from stored state (no network
  calls during rendering).

## What V1 deliberately does NOT do

V1 is a foundation, not the finished product. It intentionally does **not** include:

- ❌ Real media imports or media/edition records
- ❌ Any file operations (copy / move / delete / rename)
- ❌ Real or automatic connector sync (dry run only)
- ❌ Metadata engine / enrichment / rollback
- ❌ Download engine (NZB / torrent / download clients)
- ❌ Disc / ISO / Blu-ray / AV1 pipeline
- ❌ Fork integration
- ❌ Mobile or desktop app
- ❌ AI engine, plugin SDK, or adult engine

These belong to later engineering phases (see [docs/MediaForge/roadmap.md](docs/MediaForge/roadmap.md)).

## Technology baseline

- PHP 8.4 and Laravel 12
- React 19, TypeScript, Inertia.js, Vite, Tailwind CSS v4
- PostgreSQL 17 (pg_trgm, btree_gist, pgvector)
- Redis 7 for cache and queues
- Docker Compose for the supported development environment

## Local setup

Requirements: Docker with Compose support and, for the shortest workflow, GNU Make.

```bash
git clone https://github.com/MediaForge-org/MediaForge.git
cd MediaForge
make setup
```

`make setup` creates a local `.env` from `.env.example`, builds the development image, installs
Composer and NPM dependencies, generates the application key, starts the stack, migrates PostgreSQL,
and runs the development seeder. MediaForge is then served at **http://localhost:8100**.

Without Make, run the equivalent commands from the repository root:

```bash
cp .env.example .env   # PowerShell: Copy-Item .env.example .env
docker compose -f deploy/dev/docker-compose.yml build
docker compose -f deploy/dev/docker-compose.yml up -d postgres redis
docker compose -f deploy/dev/docker-compose.yml run --rm app composer install
docker compose -f deploy/dev/docker-compose.yml run --rm app php artisan key:generate --force
docker compose -f deploy/dev/docker-compose.yml up -d
docker compose -f deploy/dev/docker-compose.yml exec -T app php artisan migrate --force
docker compose -f deploy/dev/docker-compose.yml exec -T app php artisan db:seed --force
```

### Create a local development user

The command is restricted to the `local` and `testing` environments:

```bash
docker compose -f deploy/dev/docker-compose.yml exec app php artisan mediaforge:dev-user
```

Default credentials: `test@mediaforge.local` / `test123456`.

### Runtime notes

MediaForge runs from a **stable production build by default** — the Vite HMR server is opt-in behind
the `hmr` Compose profile. On Windows/Docker bind mounts this avoids the recurring "blank page from a
stale Vite server" failure. If the browser shows a blank or stale page:

```bash
make runtime-reset   # remove public/hot + clear caches (production-build mode)
make assets          # rebuild public/build in a clean one-off node container
make hmr             # opt in to Vite HMR only when you need live frontend updates
```

`APP_URL` must stay aligned with the served port (default `http://localhost:8100`) — see
[docs/MediaForge/dev-runtime.md](docs/MediaForge/dev-runtime.md) for the full runtime guide.

### After a reboot

Docker Desktop does not reliably restart every container, so an unreachable app after a reboot
usually means the stack is simply not running:

```bash
make dev-up       # docker compose -f deploy/dev/docker-compose.yml up -d
make dev-ps       # which containers are running, with status and ports
make dev-doctor   # read-only check: compose state, app /up, registered GET routes
```

The expected container list is in
[docs/MediaForge/dev-runtime.md](docs/MediaForge/dev-runtime.md#after-a-laptop-reboot). MediaForge
installs no autostart service or system change for Docker on purpose.

## Tests and quality gates

The hermetic test environment (`APP_ENV=testing`, `DB_DATABASE=mediaforge_test`) is pinned by
`tests/bootstrap.php`, so tests never touch the dev database and CSRF stays correct.

```bash
make test        # full Pest suite (testing env, mediaforge_test DB)
make ci          # Pint + PHPStan + Pest (the local backend gate)
```

Frontend checks run in the Node development container:

```bash
docker compose -f deploy/dev/docker-compose.yml run --rm vite npm run type-check
docker compose -f deploy/dev/docker-compose.yml run --rm vite npm run build
```

## Local ports

| Service | Default host port | Notes |
|---|---:|---|
| MediaForge | 8100 | `MEDIAFORGE_PORT` |
| Jellyfin (dev/bundled) | 8110 | `JELLYFIN_PORT`; avoids an existing Jellyfin on 8096 |
| Audiobookshelf (dev/bundled) | 13380 | `AUDIOBOOKSHELF_PORT`; avoids an existing ABS on 13378 |
| Vite HMR | 5273 | development only |
| PostgreSQL | 5440 | development only |
| Redis | 6390 | development only |
| Mailpit web / SMTP | 8126 / 1126 | development only |

The defaults deliberately leave SABnzbd (8080), Jellyfin (8096), and Audiobookshelf (13378) on their
usual host ports untouched. External services can be reached from the app container via
`host.docker.internal` or their LAN address.

## Known limitations

- Alpha software — expect breaking changes; there is no upgrade/migration guarantee between alphas.
- Dry run only — MediaForge never writes to your media servers or files in V1.
- Not hardened for public/internet exposure; run locally, keep `APP_DEBUG=false` outside development.
- Windows/Docker: use the production-build runtime mode; HMR can stall on bind mounts.

## Repository hygiene

Never commit `.env`, `vendor/`, `node_modules/`, `public/build/`, or `public/hot`. Connector
credentials and real service tokens must never be committed — they live only in the encrypted DB
secret store at runtime.

## Documentation

- [CURRENT_PHASE.md](docs/MediaForge/CURRENT_PHASE.md) — active phase and delivery status
- [V1_READINESS.md](docs/MediaForge/V1_READINESS.md) — V1 readiness checklist and release recommendation
- [dev-runtime.md](docs/MediaForge/dev-runtime.md) — local runtime modes and troubleshooting
- [roadmap.md](docs/MediaForge/roadmap.md) — the internal V0–V34 engineering roadmap

## License

MediaForge is licensed under the
[GNU Affero General Public License v3.0 or later](LICENSE) (`AGPL-3.0-or-later`).


## UI reference expansion (2026-08 update)

This overlay now also includes an expanded UI reference pack under `docs/MediaForge/ui-ux/reference-expanded/` together with two written Claude-facing UI spec files:

- `docs/MediaForge/ui-ux/UI_IMPLEMENTATION_PROMPT.md`
- `docs/MediaForge/ui-ux/SCREEN_REFERENCE_INDEX.md`

Do not rely on screenshots alone. Claude should read the written UI prompt and the screen index, then inspect the reference PNG files.
