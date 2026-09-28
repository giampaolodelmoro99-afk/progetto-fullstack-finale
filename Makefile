COMPOSE = docker compose
APP = $(COMPOSE) exec app
NODE = $(COMPOSE) exec node

.PHONY: up down build shell logs migrate fresh test test-backend test-frontend coverage-backend coverage-frontend lint artisan composer npm

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build

shell:
	$(APP) bash

logs:
	$(COMPOSE) logs -f

migrate:
	$(APP) php artisan migrate

fresh:
	$(APP) php artisan migrate:fresh --seed

test: test-backend test-frontend

test-backend:
	$(APP) php artisan test

test-frontend:
	$(NODE) npm run test

coverage-backend:
	$(APP) php artisan test --coverage --min=80

coverage-frontend:
	$(NODE) npm run test:coverage

lint:
	$(APP) vendor/bin/pint --test
	$(NODE) npm run lint

artisan:
	$(APP) php artisan $(cmd)

composer:
	$(APP) composer $(cmd)

npm:
	$(NODE) npm $(cmd)
