.PHONY: up down build shell artisan migrate fresh test test-backend test-backend-unit test-backend-feature test-frontend certs trust-cert prod-up prod-down prod-build prod-logs local-prod-up local-prod-down local-prod-build local-prod-logs e2e ci-e2e admin-shell admin-migrate admin-test admin-create-user admin-filament-assets

ifeq (artisan,$(firstword $(MAKECMDGOALS)))
ARTISAN_ARGS := $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))

%:
	@:
endif

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

shell:
	docker compose exec backend sh

artisan:
	@if [ -z "$(strip $(ARTISAN_ARGS))" ]; then echo 'Usage: make artisan <command> [args...]'; exit 1; fi
	docker compose exec backend php artisan $(ARTISAN_ARGS)

migrate:
	docker compose exec backend php artisan migrate

fresh:
	docker compose exec backend php artisan migrate:fresh --seed

test: test-backend test-frontend

test-backend:
	docker compose exec -u 1000 backend php artisan test

test-backend-unit:
	docker compose exec -u 1000 backend php artisan test --testsuite=Unit

test-backend-feature:
	docker compose exec -u 1000 backend php artisan test --testsuite=Feature

test-frontend:
	docker compose exec frontend deno run -A npm:vitest run

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

# ── Admin panel (Filament) convenience targets ─────
# Pinned to UID 1000 so file writes land under the host's devuser mount
# without triggering root-owned files in backend/.

admin-shell:
	docker compose exec -u 1000 backend sh

admin-migrate:
	docker compose exec -u 1000 backend php artisan migrate

admin-test:
	docker compose exec -u 1000 backend php artisan test --filter=Admin

# Lands in Plan 02. Until then the command is absent and this target reports
# "command not found" — that is expected and documented in docs/progress/admin-v1.md.
admin-create-user:
	docker compose exec -u 1000 -it backend php artisan admin:create-user

admin-filament-assets:
	docker compose exec -u 1000 backend php artisan filament:assets

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

E2E_COMPOSE = docker compose -f docker-compose.yml -f docker-compose.e2e.yml -f docker-compose.stack.yml

# Run the whole sequence in a single shell so a trap guarantees the
# stack is torn down even when the seeder or playwright step fails.
# Without this, a failed step exits the recipe early and leaves
# containers / volumes lying around.
e2e:
	@set -e; \
	trap 'status=$$?; $(E2E_COMPOSE) down -v; exit $$status' EXIT INT TERM; \
	$(E2E_COMPOSE) up -d --build --wait; \
	$(E2E_COMPOSE) run --rm backend-seeder; \
	$(E2E_COMPOSE) run --rm playwright

ci-e2e: e2e
