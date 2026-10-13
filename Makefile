COMPOSE = docker compose
APP = $(COMPOSE) exec app
NODE = $(COMPOSE) exec node

.PHONY: up up-ai up-gpu down build shell logs migrate fresh test test-backend test-frontend coverage-backend coverage-frontend lint artisan composer npm ask admin admin-remove

up:
	$(COMPOSE) up -d

up-ai:
	$(COMPOSE) --profile ai up -d

up-gpu:
	$(COMPOSE) -f docker-compose.yml -f docker-compose.gpu.yml --profile ai up -d

down:
	$(COMPOSE) --profile ai down

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

ask:
	$(APP) php artisan assistant:ask --timing "$(q)"

admin:
	$(APP) php artisan admin:create

admin-remove:
	$(APP) php artisan admin:remove
