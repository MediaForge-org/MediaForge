# MediaForge

MediaForge is an open-source, local media enhancement suite for existing Jellyfin and Audiobookshelf installations. It runs beside those services; it does not replace their playback, streaming, transcoding, or library cores.

## Project status

MediaForge is in **V0 — repository, foundation, and developer baseline**. V0 is not a usable product release and V1 has not started. The current work is limited to a reproducible Laravel 12, React, Inertia.js, TypeScript, PostgreSQL, Redis, and Docker Compose foundation.

The internal V0–V34 engineering roadmap is governed by [ADR-0013](docs/MediaForge/adr/0013-react-inertia-typescript-and-roadmap-governance.md). V1 may begin only after every V0 validation gate is green.

## Technology baseline

- PHP 8.4 and Laravel 12
- React, TypeScript, Inertia.js, Vite, and Tailwind CSS
- PostgreSQL 17 with pg_trgm, btree_gist, and pgvector
- Redis 7 for queues and cache
- Docker Compose for the supported development environment

## Development setup

Requirements: Docker with Compose support and, for the shortest workflow, GNU Make.

```bash
git clone https://github.com/MediaForge-org/MediaForge.git
cd MediaForge
make setup
```

`make setup` creates a local `.env` from `.env.example`, builds the development image, installs Composer and NPM dependencies, generates the application key, starts the stack, migrates PostgreSQL, and runs the development seeder.

Without Make, run the equivalent commands from the repository root:

```bash
cp .env.example .env
docker compose -f deploy/dev/docker-compose.yml build
docker compose -f deploy/dev/docker-compose.yml up -d postgres redis
docker compose -f deploy/dev/docker-compose.yml run --rm app composer install
docker compose -f deploy/dev/docker-compose.yml run --rm app php artisan key:generate --force
docker compose -f deploy/dev/docker-compose.yml up -d
docker compose -f deploy/dev/docker-compose.yml exec -T app php artisan migrate --force
docker compose -f deploy/dev/docker-compose.yml exec -T app php artisan db:seed --force
```

On Windows PowerShell, use `Copy-Item .env.example .env` instead of `cp` when Make is unavailable.

The root `docker-compose.yml` describes the packaged-image topology. During V0, the development stack is the supported contributor workflow; no stable MediaForge image or release is claimed yet.

## Local ports

| Service | Default host port | Notes |
|---|---:|---|
| MediaForge | 8100 | Configurable with `MEDIAFORGE_PORT` |
| Jellyfin dev/bundled | 8110 | Configurable with `JELLYFIN_PORT`; avoids an existing Jellyfin on 8096 |
| Audiobookshelf dev/bundled | 13380 | Configurable with `AUDIOBOOKSHELF_PORT`; avoids an existing ABS on 13378 |
| Vite HMR | 5273 | Development only |
| PostgreSQL | 5440 | Development only |
| Redis | 6390 | Development only |
| Mailpit web / SMTP | 8126 / 1126 | Development only |

The defaults intentionally leave SABnzbd on 8080, Jellyfin on 8096, and Audiobookshelf on 13378 untouched. Existing external services can be reached from the app container via `host.docker.internal` or their LAN address.

## Media paths

Media libraries do not have to live in Docker. Direct media mounts are optional and are not required for the V0 foundation or later connector-only operation. When mounts are introduced for direct analysis, they must be explicitly configured and read-only by default.

## Validation

The local backend gate is:

```bash
make ci
```

Frontend checks run in the Node 22 development container:

```bash
docker compose -f deploy/dev/docker-compose.yml run --rm vite npm install
docker compose -f deploy/dev/docker-compose.yml run --rm vite npm run type-check
docker compose -f deploy/dev/docker-compose.yml run --rm vite npm run build
```

Both Compose files must also parse successfully:

```bash
docker compose config
docker compose -f deploy/dev/docker-compose.yml config
```

## Repository hygiene

Never commit `.env`, `vendor/`, `node_modules/`, `public/build/`, or `public/hot`. The large `.ai/` master-prompt workspace is intentionally local unless the maintainers explicitly decide otherwise. Connector credentials and real service tokens must never be committed.

## Documentation

Start with [MediaForge Master Engineering](docs/MediaForge/MediaForge_Master_Engineering.md), [ADR-0013](docs/MediaForge/adr/0013-react-inertia-typescript-and-roadmap-governance.md), and the [current roadmap](docs/MediaForge/roadmap.md). Detailed feature documents describe later engineering phases unless their implementation status explicitly says otherwise.

## License

MediaForge is licensed under the [GNU Affero General Public License v3.0 or later](LICENSE) (`AGPL-3.0-or-later`).
