.DEFAULT_GOAL := help
DC  := docker compose --env-file src/.env
APP := $(DC) exec app

help: ## Daftar perintah
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Jalankan stack (app, web, db)
	$(DC) up -d

down: ## Hentikan stack
	$(DC) down

build: ## Build ulang image
	$(DC) build

logs: ## Lihat log
	$(DC) logs -f

sh: ## Masuk shell container app
	$(APP) sh

artisan: ## Jalankan artisan, mis: make artisan c="migrate"
	$(APP) php artisan $(c)

migrate: ## Jalankan migration
	$(APP) php artisan migrate

fresh: ## Migrate fresh + seed
	$(APP) php artisan migrate:fresh --seed

composer: ## Jalankan composer, mis: make composer c="install"
	$(APP) composer $(c)

assets: ## Jalankan Vite dev server
	$(DC) --profile assets up node

worker: ## Jalankan queue + scheduler
	$(DC) --profile worker up -d queue scheduler
