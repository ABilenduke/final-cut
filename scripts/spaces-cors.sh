#!/usr/bin/env bash
# Apply (or inspect) the CORS policy on the DigitalOcean Spaces bucket that
# backs every Filament FileUpload (`public` disk → DO_SPACES_* in prod).
#
# WHY THIS EXISTS
#   Filament's FileUpload renders a preview of the ALREADY-SAVED image by
#   fetch()ing its CDN URL, and `->imageEditor()` reads the bytes into a
#   <canvas>. Both are cross-origin requests (admin.<domain> →
#   *.digitaloceanspaces.com), so the browser enforces CORS on the response.
#   If the Space has no CORS policy, the CDN returns 200 but no
#   `Access-Control-Allow-Origin` header and the browser blocks the read:
#
#     Access to fetch at '…cdn.digitaloceanspaces.com/…' from origin
#     'https://admin.<domain>' has been blocked by CORS policy: No
#     'Access-Control-Allow-Origin' header is present on the requested resource.
#
#   This is a BUCKET-side setting. It is NOT backend/config/cors.php (that
#   governs the Laravel API; the failing request never reaches Laravel) and it
#   is NOT nginx. See docs/runbooks/production-deploy.md § Spaces CORS.
#
# IDEMPOTENT
#   `put-bucket-cors` REPLACES the whole CORS configuration each call, so
#   re-running just reasserts the desired policy — safe to run repeatedly and
#   any time you add/change an allowed origin.
#
# REQUIREMENTS
#   - aws CLI on PATH (Spaces speaks the S3 API). DO_SPACES_KEY / DO_SPACES_SECRET
#     are used as the S3 credentials against DO_SPACES_ENDPOINT — no `aws
#     configure` needed.
#
# INPUTS (environment, or read from $ENV_FILE — default backend/.env;
#         already-exported env vars always win over the file):
#   DO_SPACES_KEY, DO_SPACES_SECRET   Spaces access key / secret
#   DO_SPACES_BUCKET                  Space (bucket) name, e.g. andrewbilendukecdn
#   DO_SPACES_REGION                  e.g. nyc3
#   DO_SPACES_ENDPOINT               regional endpoint, e.g. https://nyc3.digitaloceanspaces.com
#   Origins (in priority order):
#     SPACES_CORS_ORIGINS            explicit comma-separated allow list (overrides below)
#     FRONTEND_URL                   customer origin, e.g. https://finalcut.com
#     ADMIN_DOMAIN                   admin host, e.g. admin.finalcut.com → https://admin.finalcut.com
#   SPACES_CORS_MAX_AGE              preflight cache seconds (default 3600)
#
# USAGE
#   ./scripts/spaces-cors.sh            apply the policy
#   ./scripts/spaces-cors.sh --check    print the Space's live CORS policy, don't write
#   ENV_FILE=/path/.env ./scripts/spaces-cors.sh
#   SPACES_CORS_ORIGINS="https://a.com,https://b.com" ./scripts/spaces-cors.sh

set -euo pipefail

# The script lives in scripts/, so its parent is the repo root. Resolve and cd
# there so $ENV_FILE (default backend/.env) is found regardless of where the
# caller invoked from.
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

ENV_FILE="${ENV_FILE:-backend/.env}"
MODE="apply"

for arg in "$@"; do
  case "$arg" in
    --check|-c) MODE="check" ;;
    --help|-h)
      sed -n '2,47p' "$0" | sed 's/^# \{0,1\}//'
      exit 0
      ;;
    *) echo "Unknown argument: $arg (try --help)" >&2; exit 2 ;;
  esac
done

# Pull a single KEY's value out of the env file WITHOUT sourcing it (sourcing
# would execute any $(...)/backtick in a secret). Last assignment wins; trailing
# ` # inline comment`, surrounding quotes, and trailing whitespace are stripped.
read_env() {
  local key="$1"
  [ -f "$ENV_FILE" ] || return 0
  # A missing key is normal (optional vars like SPACES_CORS_MAX_AGE) — grep
  # exits 1, which pipefail would propagate and `set -e` would treat as a fatal
  # error in the `VAR="$(resolve …)"` caller. Trailing `|| true` makes "not
  # found" an empty result, not an abort.
  grep -E "^${key}=" "$ENV_FILE" 2>/dev/null | tail -n1 | cut -d= -f2- \
    | sed -e 's/[[:space:]][[:space:]]*#.*$//' \
          -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/" \
          -e 's/[[:space:]]*$//' || true
}

# Resolve a var from the environment first, then the env file.
resolve() {
  local var="$1"
  local current="${!var-}"
  if [ -n "$current" ]; then printf '%s' "$current"; else read_env "$var"; fi
}

DO_SPACES_KEY="$(resolve DO_SPACES_KEY)"
DO_SPACES_SECRET="$(resolve DO_SPACES_SECRET)"
DO_SPACES_BUCKET="$(resolve DO_SPACES_BUCKET)"
DO_SPACES_REGION="$(resolve DO_SPACES_REGION)"
DO_SPACES_ENDPOINT="$(resolve DO_SPACES_ENDPOINT)"
SPACES_CORS_MAX_AGE="$(resolve SPACES_CORS_MAX_AGE)"; SPACES_CORS_MAX_AGE="${SPACES_CORS_MAX_AGE:-3600}"

command -v aws >/dev/null 2>&1 || {
  echo "ERROR: the 'aws' CLI is required but not on PATH." >&2
  echo "       Install it (https://aws.amazon.com/cli/) or apply CORS via the" >&2
  echo "       DO console: Space → Settings → CORS Configurations." >&2
  exit 1
}

missing=()
for v in DO_SPACES_KEY DO_SPACES_SECRET DO_SPACES_BUCKET DO_SPACES_REGION DO_SPACES_ENDPOINT; do
  [ -n "${!v}" ] || missing+=("$v")
done
if [ "${#missing[@]}" -gt 0 ]; then
  echo "ERROR: missing required value(s): ${missing[*]}" >&2
  echo "       Set them in the environment or in $ENV_FILE." >&2
  exit 1
fi

# aws reads creds from these; we never call `aws configure`.
export AWS_ACCESS_KEY_ID="$DO_SPACES_KEY"
export AWS_SECRET_ACCESS_KEY="$DO_SPACES_SECRET"
export AWS_DEFAULT_REGION="$DO_SPACES_REGION"

aws_spaces() {
  aws --endpoint-url "$DO_SPACES_ENDPOINT" --region "$DO_SPACES_REGION" "$@"
}

if [ "$MODE" = "check" ]; then
  echo "Live CORS policy for Space '$DO_SPACES_BUCKET' (@ $DO_SPACES_ENDPOINT):"
  aws_spaces s3api get-bucket-cors --bucket "$DO_SPACES_BUCKET" \
    || echo "(no CORS policy set — run '$0' to apply one)"
  exit 0
fi

# ── Build the allowed-origin list ────────────────────────────────────────────
ORIGINS=()
EXPLICIT="$(resolve SPACES_CORS_ORIGINS)"
if [ -n "$EXPLICIT" ]; then
  IFS=',' read -r -a ORIGINS <<< "$EXPLICIT"
else
  FRONTEND_URL="$(resolve FRONTEND_URL)"
  ADMIN_DOMAIN="$(resolve ADMIN_DOMAIN)"
  [ -n "$FRONTEND_URL" ] && ORIGINS+=("$FRONTEND_URL")
  [ -n "$ADMIN_DOMAIN" ] && ORIGINS+=("https://${ADMIN_DOMAIN#https://}")
fi

# Normalise (trim, drop trailing slash, dedupe) and validate each origin is an
# http(s) absolute origin — no wildcards, no garbage interpolated into the policy.
CLEAN=()
for o in "${ORIGINS[@]}"; do
  o="$(printf '%s' "$o" | tr -d '[:space:]')"
  o="${o%/}"
  [ -z "$o" ] && continue
  case "$o" in
    http://*|https://*) ;;
    *) echo "ERROR: refusing non-http(s) origin: '$o'" >&2; exit 1 ;;
  esac
  # dedupe
  seen=""
  for c in "${CLEAN[@]:-}"; do [ "$c" = "$o" ] && seen=1 && break; done
  [ -z "$seen" ] && CLEAN+=("$o")
done

if [ "${#CLEAN[@]}" -eq 0 ]; then
  echo "ERROR: no allowed origins resolved." >&2
  echo "       Set SPACES_CORS_ORIGINS, or FRONTEND_URL + ADMIN_DOMAIN, in $ENV_FILE." >&2
  exit 1
fi

# Compose the AllowedOrigins JSON array.
origins_json=""
for o in "${CLEAN[@]}"; do
  origins_json+="${origins_json:+,}\"$o\""
done

CORS_TMP="$(mktemp)"
trap 'rm -f "$CORS_TMP"' EXIT
cat > "$CORS_TMP" <<JSON
{
  "CORSRules": [
    {
      "AllowedOrigins": [$origins_json],
      "AllowedMethods": ["GET", "HEAD"],
      "AllowedHeaders": ["*"],
      "ExposeHeaders": ["ETag"],
      "MaxAgeSeconds": $SPACES_CORS_MAX_AGE
    }
  ]
}
JSON

echo "Applying CORS to Space '$DO_SPACES_BUCKET' (@ $DO_SPACES_ENDPOINT)"
echo "  Allowed origins: ${CLEAN[*]}"
echo "  Methods: GET, HEAD   Max-Age: ${SPACES_CORS_MAX_AGE}s"
aws_spaces s3api put-bucket-cors --bucket "$DO_SPACES_BUCKET" \
  --cors-configuration "file://$CORS_TMP"

echo
echo "Applied. Live policy now:"
aws_spaces s3api get-bucket-cors --bucket "$DO_SPACES_BUCKET"

cat <<'NOTE'

NEXT: purge the CDN cache, or a previously-cached no-CORS response can serve for
up to `cache-control: max-age` (3600s). DO console → the Space → Settings →
Purge Cache, or:  doctl compute cdn flush <cdn-id> --files "*"
Then hard-reload the admin form and confirm the image preview renders.
NOTE
