COMPOSE := docker compose
EXEC    := $(COMPOSE) exec -T app

# Build args of the app image: container writes into the bind mount as the host user.
export UID := $(shell id -u)
export GID := $(shell id -g)

.PHONY: up down init migrate fresh test test-race shell

# Compose reads .env too (mysql credentials, APP_PORT), so it must exist before the first `up`.
.env:
	cp -n .env.example .env

up: .env
	$(COMPOSE) up -d --build --wait

down:
	$(COMPOSE) down

init: .env
	$(EXEC) composer install
	@grep -q '^APP_KEY=base64:' .env || $(EXEC) php artisan key:generate

migrate:
	$(EXEC) php artisan migrate --seed

fresh:
	$(EXEC) php artisan migrate:fresh --seed

test: up
	$(EXEC) composer test

test-race: up
	$(EXEC) php artisan test --testsuite=Concurrency

shell:
	$(COMPOSE) exec app bash
