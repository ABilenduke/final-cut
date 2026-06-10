# Plan 01 (v5) — CI reliability: composer source fallback

**Step:** 5.1 · **Status:** ✅ Complete

## Diagnosis

Three E2E-job failures on 2026-06-10, all infrastructure: two Docker Hub
pull timeouts (rerun-only fix — registry-side) and one `composer install`
failure inside the backend image build whose log shows the actual defect:

> `git was not found in your PATH, skipping source download`

When a packagist **dist** download flakes, composer falls back to
installing from **source** — which requires git. None of the
composer-running build stages had it, so a single flaked dist download
failed the whole image build.

## Fix

`git` added to the three composer-running stages of `backend/Dockerfile`:
the `vendor` stage apk list, and root-stage `apk add --no-cache git` in
`e2e-seeder` and `development` (before the drop to `devuser`). Build-stage
only — the `production` runtime stage is untouched, so the prod image
gains nothing.

## Verification

Local `docker compose build backend` clean; rebuilt dev container serves
the suite (1305 passed — after the known boot-race `optimize:clear`);
`git --version` confirms availability in the dev stage. The real test is
CI itself over the coming runs.
