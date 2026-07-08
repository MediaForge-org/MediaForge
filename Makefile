# MediaForge — developer workflow.
# Target: `make setup` takes a fresh clone to a running system in < 15 minutes.

COMPOSE := docker compose -f deploy/dev/docker-compose.yml
APP := $(COMPOSE) exec -T app

.DEFAULT_GOAL := help
.PHONY: help setup up down restart build logs shell \
        migrate fresh seed test lint analyse types stan pint ci

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

test: ## Run the Pest suite
	$(APP) php artisan config:clear
	$(APP) php vendor/bin/pest

lint pint: ## Fix code style (Pint)
	$(APP) php vendor/bin/pint

analyse stan: ## Static analysis (PHPStan)
	$(APP) php vendor/bin/phpstan analyse --memory-limit=512M

types: ## Type-check the frontend (vue-tsc)
	$(COMPOSE) run --rm vite npm run type-check

ci: ## Run the full local gate (style, static analysis, tests)
	$(APP) php vendor/bin/pint --test
	$(APP) php vendor/bin/phpstan analyse --memory-limit=512M
	$(APP) php vendor/bin/pest
