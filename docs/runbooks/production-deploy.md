# Production Deploy Runbook (DigitalOcean droplet)

How Final Cut ships to production: a tag-driven GitHub Actions pipeline builds
images to GHCR and rolls them onto a single DigitalOcean droplet. Postgres is
**DigitalOcean Managed**; Redis runs as a **container** on the droplet.

- **Customer site:** `https://<domain>` · **Admin:** `https://admin.<domain>`
- **Compose layers (runtime):** `docker-compose.yml` + `docker-compose.prod.yml` + `docker-compose.registry.yml`
- **Deploy workflow:** `.github/workflows/release.yml`
- See also `docs/runbooks/admin-operations.md` (create users, unban IPs, emergency shutdown).

---

## Architecture

```
git tag vX.Y.Z ─▶ release.yml
  ├─ build-push  → ghcr.io/abilenduke/final-cut-{backend,frontend}:X.Y.Z (+ :latest)
  └─ deploy(SSH) → droplet: git checkout vX.Y.Z; make prod-pull; prod-deploy; prod-migrate; prod-optimize; /up health gate

droplet: nginx (LE certs) · frontend · backend · backend-worker · backend-scheduler · redis (container) · certbot · fail2ban
external: PostgreSQL (DO Managed, TLS verify-full)
```

All three backend services share one image. The frontend image is
environment-agnostic (`NUXT_PUBLIC_*` are read at boot, not baked).

---

## One-time droplet bootstrap

Do this once on a fresh droplet before the first release.

### 1. Host prerequisites
- Install Docker Engine + the compose plugin, `git`, `make`, `curl`.
- Create a non-root deploy user; add it to the `docker` group.

### 2. Clone the repo
```bash
sudo mkdir -p /opt/final-cut && sudo chown "$USER" /opt/final-cut
git clone https://github.com/ABilenduke/final-cut.git /opt/final-cut
cd /opt/final-cut
```
The checkout provides the compose files, nginx/fail2ban/redis configs, and the
committed Filament assets under `backend/public` that nginx serves directly.

### 3. Secrets and CA certs (chmod 600, never committed)

**Root `.env`** (Docker Compose variable substitution):
```ini
APP_DOMAIN=<domain>
APP_PRIMARY_DOMAIN=<domain>
ADMIN_DOMAIN=admin.<domain>
IMAGE_TAG=v1.0.0
# DigitalOcean Managed Postgres
DB_HOST=<your-db>.db.ondigitalocean.com
DB_PORT=25060
DB_DATABASE=final_cut
DB_USERNAME=<managed-user>
DB_PASSWORD=<managed-password>
# Redis container
REDIS_USERNAME=app
REDIS_PASSWORD=<strong-secret>
# Admin panel (fail-closed — set BEFORE first request)
ADMIN_IP_ALLOWLIST=<your.ip.v4/32 or CIDR>
# CSP img-src CDN host (must match backend DO_SPACES_URL)
DO_SPACES_URL=https://cdn.example.com
```

**`backend/.env`** — copy `backend/.env.production.example` and fill **every**
value: `APP_KEY` (`php artisan key:generate --show` locally), `APP_URL`,
`FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, live `STRIPE_*`, `TMDB_API_KEY`,
`DO_SPACES_*`, `MAIL_*`. `docker-compose.registry.yml` injects this file into the
backend/worker/scheduler containers via `env_file:`.

**`frontend/.env`** — copy `frontend/.env.production.example` and fill the
`NUXT_PUBLIC_*` values (live Stripe publishable key, site/API/CDN URLs).

**Managed-Postgres CA** — place DigitalOcean's CA at `postgres/certs/ca.pem`
(DO console → database → "Download CA certificate", or `doctl databases get`).
`DB_SSLROOTCERT=/tls/postgres/ca.pem` resolves to this host file, satisfying
`DB_SSLMODE=verify-full`.

> ⚠️ **Never run `make certs` on the droplet** — its Postgres generator would
> overwrite DO's CA at `postgres/certs/ca.pem` and break `verify-full`. Run only
> the individual generators below.

### 4. Generate the still-self-signed container certs
```bash
./redis/certs/generate-certs.sh    # Redis container TLS
./nginx/certs/generate-certs.sh    # bootstrap cert so nginx can boot before LE
```

### 5. GHCR login (one-time)
```bash
echo "<read:packages PAT>" | docker login ghcr.io -u abilenduke --password-stdin
```
Stored in `~/.docker/config.json`; the deploy `make prod-pull` then needs no
in-CI registry token. Use a GitHub PAT with **`read:packages`** scope only.

### 6. Issue the Let's Encrypt cert (two-phase — breaks the chicken-and-egg)
nginx in the prod overlay points at `/etc/letsencrypt/live/<domain>/...`, which
doesn't exist yet, but certbot's webroot needs nginx serving `:80`.
```bash
export APP_DOMAIN=<domain>
# Phase 1 — nginx on the self-signed cert (no prod overlay), serving the ACME path:
docker compose -f docker-compose.yml -f docker-compose.registry.yml up -d nginx
# Phase 2 — issue the SAN cert (apex + www + admin):
docker compose -f docker-compose.yml -f docker-compose.prod.yml run --rm certbot \
  certonly --webroot -w /var/www/certbot \
  -d "$APP_DOMAIN" -d "www.$APP_DOMAIN" -d "admin.$APP_DOMAIN" \
  --agree-tos -m "ops@$APP_DOMAIN"
# Phase 3 — full stack with the LE certs:
export IMAGE_TAG=v1.0.0
make prod-pull && make prod-deploy
```
After this the certbot sidecar auto-renews and the `letsencrypt` volume persists
the certs across deploys.

### 7. Database init (NOT the demo seeder)
```bash
make prod-migrate                                                        # migrate --force
make prod-registry-down >/dev/null 2>&1 || true                          # (no-op; just here for reference)
docker compose -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.registry.yml \
  exec -T backend php artisan db:seed --class=AdminRolesAndPermissionsSeeder --force
make admin-create-user   # or: ... exec -it backend php artisan admin:create-user
```
> **Never** run `migrate:fresh --seed` or a bare `db:seed` in production —
> `DatabaseSeeder` creates 10 Faker users + demo locations/auditoriums/seats.
> Real locations and seats are entered through the Filament admin.
>
> The first `migrate --force` **ends the pre-launch "edit migrations in place"
> window** — every schema change after this is an additive migration.

### 8. Verify (see checklist below), then set DNS/HSTS go-live items.

### 9. Apply Spaces CORS (admin image previews) — see § below.

---

## DigitalOcean Spaces CORS (admin image previews)

Filament's `FileUpload` fields (featured slides, calendar events, menu items,
blog posts) render a preview of the **already-saved** image by `fetch()`ing its
CDN URL, and `->imageEditor()` reads the bytes into a `<canvas>`. Both are
cross-origin (`admin.<domain>` → `*.digitaloceanspaces.com`), so the **Space
must return `Access-Control-Allow-Origin`** or the browser blocks the fetch:

> Access to fetch at '…cdn.digitaloceanspaces.com/…' from origin
> 'https://admin.&lt;domain&gt;' has been blocked by CORS policy: No
> 'Access-Control-Allow-Origin' header is present on the requested resource.

This is a **bucket-side** setting. It is **not** `backend/config/cors.php` (that
governs the Laravel API — the failing request never reaches Laravel) and **not**
nginx. Apply it once per Space (re-run after adding/changing an allowed origin —
`put-bucket-cors` replaces the whole policy, so it is idempotent):

```bash
make spaces-cors          # apply from backend/.env: GET/HEAD for FRONTEND_URL + https://$ADMIN_DOMAIN
make spaces-cors-check    # print the Space's live CORS policy (read-only)
```

`scripts/spaces-cors.sh` reads `DO_SPACES_*` plus the origins from `backend/.env`
(env vars override). Override the allow list explicitly with
`SPACES_CORS_ORIGINS="https://a.com,https://b.com"`. It needs the **`aws` CLI**
on PATH (Spaces speaks S3; `DO_SPACES_KEY`/`SECRET` are used as the credentials —
no `aws configure` needed). No CLI? Apply the same rule in the DO console: the
Space → **Settings → CORS Configurations** (Origins = both URLs, Methods = GET +
HEAD, Allowed Headers = `*`).

> ⚠️ **Purge the CDN afterwards.** The CDN caches the object response
> (`cache-control: max-age=3600`), so a previously-cached no-CORS response can
> serve for up to an hour. DO console → the Space → **Settings → Purge Cache**,
> or `doctl compute cdn flush <cdn-id> --files "*"`. Then hard-reload an admin
> form and confirm the preview renders. Verify the header directly with:
>
> ```bash
> curl -sI -H "Origin: https://admin.<domain>" "<a CDN image URL>" \
>   | grep -i access-control-allow-origin
> ```

---

## Ongoing deploys (automated)

1. Land changes on `main`; confirm all CI is green.
2. Tag and push:
   ```bash
   git tag -a v1.2.0 -m "Final Cut v1.2.0" && git push origin v1.2.0
   ```
3. `release.yml` builds + pushes images, then SSHes in and runs
   `prod-pull → prod-deploy → prod-migrate → prod-optimize → /up` health gate.

**Redeploy / rollback:** Actions → *Release* → *Run workflow* → enter an existing
tag (e.g. `v1.1.0`). Images are immutable in GHCR, so this re-pulls the prior
image and rolls back. (DB migrations are not auto-rolled-back — forward-only.)

---

## GitHub repository secrets

| Secret | Purpose |
|---|---|
| `SSH_HOST` | Droplet IP / hostname |
| `SSH_USER` | Deploy user (in the `docker` group) |
| `SSH_PRIVATE_KEY` | Private key whose public half is in the deploy user's `authorized_keys` |
| `SSH_PORT` | Optional (defaults to 22) |

Image **push** uses the built-in `GITHUB_TOKEN` (`packages: write`). The droplet's
image **pull** uses the `read:packages` PAT stored on the droplet (step 5), not a
GitHub secret.

---

## Post-deploy verification checklist

- `docker compose -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.registry.yml ps`
  → nginx/frontend/backend/worker/scheduler/redis/certbot/fail2ban healthy; **no postgres** container.
- `curl -fsSI https://<domain>/` → 200, valid Let's Encrypt cert.
- `curl -fsS https://<domain>/up` → `ok`.
- Customer site loads movies; a credentialed SPA fetch succeeds (confirms
  `FRONTEND_URL` / `SANCTUM_STATEFUL_DOMAINS`).
- `https://admin.<domain>` reachable from an allowlisted IP; **login succeeds
  with no redirect loop** (confirms the `trustProxies` fix); Filament theme renders.
- A Stripe payment, an admin image upload (lands in DO Spaces + serves via CDN),
  a password-reset email, and a scheduler `movies:enrich` run all succeed.
- **Editing** an existing slide/event/menu item shows the saved image **preview**
  (not a broken/blocked thumbnail) — confirms Spaces CORS is applied (§ above).

---

## Gotchas (quick reference)

- **`make certs` overwrites DO's CA** — run only `redis`/`nginx` generators on the droplet.
- **Admin lockout** (empty `ADMIN_IP_ALLOWLIST` fails closed) — set the CIDR before
  first request, or bootstrap with `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN=true` (logs
  error per request) → verify → set the real allowlist → flip back → restart.
- **`make prod-pull` pulls four images by name** — a bare `docker compose pull`
  would try to pull the build-only `redis` service and fail.
- **CSP is Report-Only and HSTS `preload` is inert** until you promote the CSP to
  enforcing and submit the apex to hstspreload.org — deliberate post-launch steps.
- **Admin image previews blocked by CORS** (`No 'Access-Control-Allow-Origin'`) —
  the Space needs a CORS policy. Run `make spaces-cors` then purge the CDN cache
  (§ DigitalOcean Spaces CORS). It is a bucket setting, not `config/cors.php`.
