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
	# Clear, then rebuild — leaves the same warmed-cache state as a fresh
	# container boot (dev-entrypoint.sh). `optimize:clear` alone would leave
	# config/route/view caches cold and inconsistent with the boot contract.
	docker compose exec backend php artisan optimize:clear
	docker compose exec backend php artisan optimize

test: test-backend test-frontend

test-backend:
	docker compose exec -u 1000 backend php artisan test

test-backend-unit:
	docker compose exec -u 1000 backend php artisan test --testsuite=Unit

test-backend-feature:
	docker compose exec -u 1000 backend php artisan test --testsuite=Feature

# Frontend tests run in a fresh, disposable container (`run --rm`, not `exec`)
# with their OWN isolated cache + .nuxt volumes — never the live dev server's.
#
# Root cause of the historical hangs: `docker compose run frontend` auto-loads
# docker-compose.override.yml, so the ephemeral test container used to mount the
# SAME `frontend-deno-cache` and `frontend-nuxt` volumes that the running dev
# server (`deno task dev`) is actively writing. vitest's vite/esbuild pipeline
# then contended with the dev server over the shared esbuild service and the
# vite optimize-deps lock inside .nuxt — and deadlocked indefinitely (observed:
# a backgrounded run held a container for 12h). Restarting the dev container
# "fixed" it only by clearing the contended state — a workaround, not a fix.
#
# The fix: `-v frontend-test-*` shadows both shared volumes with dedicated,
# persistent test-only volumes, so the test run shares NO mutable state with the
# dev server and the contention is structurally impossible. `nuxt prepare`
# populates the isolated .nuxt (the `environment: 'nuxt'` vitest env needs it;
# an empty .nuxt makes every file fail in environment setup). The named volumes
# persist, so only the very first run is cold; steady-state is ~25s.
#
# vitest.config.ts already forces `pool: 'forks'` so the parent exits cleanly
# (Deno's npm-vitest shim doesn't fire tinypool's worker unref under the default
# threads pool). --no-deps keeps backend/postgres/redis untouched; -T streams
# output cleanly. ALWAYS run this foreground with a timeout — never background it.
FE_TEST_VOLS = -v frontend-test-deno-cache:/home/devuser/.cache/deno -v frontend-test-nuxt:/app/.nuxt

test-frontend:
	docker compose run --rm --no-deps -T $(FE_TEST_VOLS) frontend \
		sh -c 'deno run -A npm:nuxt prepare && deno run -A npm:vitest run $(FE_ARGS)'

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
