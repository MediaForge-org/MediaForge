# MediaForge — developer workflow.
# Target: `make setup` takes a fresh clone to a running system in < 15 minutes.

COMPOSE := docker compose -f deploy/dev/docker-compose.yml
APP := $(COMPOSE) exec -T app

# The dev container injects .env (APP_ENV=local, DB=mediaforge, redis, ...) as real
# process env vars that shadow phpunit.xml's <env>. Inject the hermetic test
# environment explicitly so the suite always runs as `testing` against
# mediaforge_test (never the dev database/redis) — this is also what lets Laravel
# skip CSRF for POST feature tests. On GitHub CI (no shadowing) it is a harmless
# no-op. See docs/MediaForge/dev-runtime.md.
TEST_ENV := -e APP_ENV=testing -e DB_DATABASE=mediaforge_test \
            -e SESSION_DRIVER=array -e CACHE_STORE=array -e QUEUE_CONNECTION=sync \
            -e BCRYPT_ROUNDS=4 -e MAIL_MAILER=array
PEST := $(COMPOSE) exec -T $(TEST_ENV) app php vendor/bin/pest

.DEFAULT_GOAL := help
.PHONY: help setup up down restart build logs shell \
        migrate fresh seed test lint analyse types stan pint ci \
        assets runtime-reset hmr

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

setup: ## First-run: build, install deps, migrate, seed
	@test -f .env || cp .env.example .env
	$(COMPOSE) build
	$(COMPOSE) up -d postgres redis
	$(COMPOSE) run --rm app composer install
	$(COMPOSE) run --rm app php artisan key:generate --force
	$(COMPOSE) up -d
	$(APP) php artisan migrate --force
	$(APP) php artisan db:seed --force
	@echo "\nMediaForge is up:  http://localhost:8100"
	@echo "Mailpit:           http://localhost:8126"
	@echo "Jellyfin (dev):    http://localhost:8110"
	@echo "Audiobookshelf:    http://localhost:13380"

up: ## Start the stack
	$(COMPOSE) up -d

down: ## Stop the stack
	$(COMPOSE) down

restart: ## Restart the stack
	$(COMPOSE) restart

build: ## Rebuild the app image
	$(COMPOSE) build

logs: ## Tail logs
	$(COMPOSE) logs -f --tail=100

shell: ## Shell into the app container
	$(COMPOSE) exec app bash

migrate: ## Run migrations
	$(APP) php artisan migrate

fresh: ## Drop + re-migrate + seed
	$(APP) php artisan migrate:fresh --seed

seed: ## Run seeders
	$(APP) php artisan db:seed

test: ## Run the Pest suite (hermetic: testing env, mediaforge_test DB)
	$(PEST)

lint pint: ## Fix code style (Pint)
	$(APP) php vendor/bin/pint

analyse stan: ## Static analysis (PHPStan)
	$(APP) php vendor/bin/phpstan analyse --memory-limit=512M

types: ## Type-check the React frontend (TypeScript)
	$(COMPOSE) run --rm vite npm run type-check

ci: ## Run the full local gate (style, static analysis, tests)
	$(APP) php vendor/bin/pint --test
	$(APP) php vendor/bin/phpstan analyse --memory-limit=512M
	$(PEST)

assets: ## Build frontend assets in a clean one-off node container (not the HMR service)
	$(COMPOSE) run --rm --no-deps vite npm run build

runtime-reset: ## Force stable production-build mode (remove public/hot, clear caches)
	$(APP) php artisan mediaforge:runtime:reset

hmr: ## Start optional Vite HMR (Windows: fall back to `make assets` if it stalls)
	$(COMPOSE) --profile hmr up -d vite
