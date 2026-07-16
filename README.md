# MediaForge

MediaForge is an open-source, **local-first** enhancement suite that runs *beside* your existing
[Jellyfin](https://jellyfin.org/) and [Audiobookshelf](https://www.audiobookshelf.org/)
installations. It does **not** replace their playback, streaming, transcoding, or library cores —
it adds a safe local control surface for configuring connectors, discovering libraries, and
preparing future sync.

## Project status

**V1 — local core (alpha). Not production-ready.**

MediaForge V1 is a usable local foundation, but it is an early alpha meant for local/self-hosted
evaluation only. It performs **no** media import, **no** file operations, and **no** automatic
sync. Do not expose it to the public internet; run it on a trusted local network behind your own
reverse proxy and TLS if you must reach it remotely.

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
