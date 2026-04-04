.PHONY: up down build shell migrate fresh certs trust-cert prod-up prod-down prod-build prod-logs local-prod-up local-prod-down local-prod-build local-prod-logs storybook storybook-logs e2e

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

shell:
	docker compose exec backend sh

migrate:
	docker compose exec backend php artisan migrate

fresh:
	docker compose exec backend php artisan migrate:fresh --seed

PROD_COMPOSE = APP_ENV=production APP_DEBUG=false NODE_ENV=production docker compose -f docker-compose.yml -f docker-compose.prod.yml
LOCAL_PROD_COMPOSE = APP_ENV=production APP_DEBUG=false NODE_ENV=production docker compose -f docker-compose.yml -f docker-compose.local-prod.yml -f docker-compose.stack.yml

prod-up:
	$(PROD_COMPOSE) up -d

prod-down:
	$(PROD_COMPOSE) down

prod-build:
	$(PROD_COMPOSE) build

prod-logs:
	$(PROD_COMPOSE) logs -f

local-prod-up:
	$(LOCAL_PROD_COMPOSE) up -d

local-prod-down:
	$(LOCAL_PROD_COMPOSE) down

local-prod-build:
	$(LOCAL_PROD_COMPOSE) build

local-prod-logs:
	$(LOCAL_PROD_COMPOSE) logs -f

certs:
	@./nginx/certs/generate-certs.sh
	@./redis/certs/generate-certs.sh
	@./postgres/certs/generate-certs.sh

trust-cert:
	@if [ ! -f nginx/certs/ca.pem ]; then echo "Error: No CA certificate found. Run 'make certs' first."; exit 1; fi
	@if ! command -v powershell.exe > /dev/null 2>&1; then echo "Error: powershell.exe not found. This command requires WSL2."; exit 1; fi
	@APP_DOMAIN=$$(grep '^APP_DOMAIN=' .env | cut -d '=' -f2); \
		APP_DOMAIN=$${APP_DOMAIN:-finalcut.test}; \
		WIN_CERT_PATH=$$(wslpath -w "$$(pwd)/nginx/certs/ca.pem") && \
		powershell.exe -Command "Start-Process powershell -Verb RunAs -ArgumentList '-Command Import-Certificate -FilePath \"$$WIN_CERT_PATH\" -CertStoreLocation Cert:\\LocalMachine\\Root'" && \
		echo "CA certificate import requested (requires admin approval in Windows UAC dialog)" && \
		echo "After approval, restart Chrome for the green padlock on https://$$APP_DOMAIN"

storybook:
	docker compose exec storybook bun run storybook

storybook-logs:
	docker compose logs -f storybook

e2e:
	docker compose run --rm --build playwright
