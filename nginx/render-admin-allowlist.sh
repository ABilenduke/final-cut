# Render the Layer-1 admin IP allowlist block from ADMIN_IP_ALLOWLIST.
# Sourced from the nginx container entrypoint in both docker-compose.yml
# and docker-compose.prod.yml so the validation logic stays in ONE place
# and a prod-overlay regression cannot reintroduce nginx directive
# injection.
#
# Contract:
#   - APP_ENV=local                       → empty (allow all in dev)
#   - APP_ENV=anything, list non-empty    → "allow CIDR; ... deny all;"
#   - APP_ENV=anything, list empty        → "deny all;" (fail closed)
#
# Each entry is validated against an IPv4(/CIDR) regex BEFORE being
# interpolated into nginx config. Globbing is disabled (`set -f`) while
# iterating to neutralise wildcard patterns that could match filesystem
# paths if the env var were maliciously crafted. Any invalid entry forces
# a fail-closed `deny all;` block — better to lock everyone out (Layer 2
# will surface the misconfig in logs) than to inject unintended nginx
# directives.
#
# The Laravel AdminIpAllowlist middleware enforces the same posture at
# Layer 2 so a misrendered template here never silently exposes the
# admin panel.
#
# Inputs:  $APP_ENV, $ADMIN_IP_ALLOWLIST
# Output:  $ADMIN_ALLOWLIST_BLOCK exported in the caller's environment.

if [ "$APP_ENV" = "local" ]; then
  ADMIN_ALLOWLIST_BLOCK=""
elif [ -z "$ADMIN_IP_ALLOWLIST" ]; then
  ADMIN_ALLOWLIST_BLOCK="deny all;"
else
  ADMIN_ALLOWLIST_BLOCK=""
  ADMIN_ALLOWLIST_VALID=true
  set -f
  IFS=','
  for cidr in $ADMIN_IP_ALLOWLIST; do
    cidr=$(printf '%s' "$cidr" | tr -d '[:space:]')
    [ -z "$cidr" ] && continue
    # IPv4 or IPv4/CIDR (mask 0-32). Anything else (semicolons,
    # newlines, globs, IPv6) fails the match and falls through to
    # the validation guard below.
    if ! printf '%s\n' "$cidr" | grep -Eq '^([0-9]{1,3}\.){3}[0-9]{1,3}(/([0-9]|[12][0-9]|3[0-2]))?$'; then
      ADMIN_ALLOWLIST_VALID=false
      echo "ERROR: ADMIN_IP_ALLOWLIST entry rejected (not a valid IPv4 / IPv4/CIDR): $cidr" >&2
      break
    fi
    # Per-octet bounds check — the regex matches 0-999 per octet,
    # so e.g. "999.0.0.0" passes the regex but is invalid.
    ip_part=${cidr%%/*}
    OLD_IFS="$IFS"
    IFS='.'
    # shellcheck disable=SC2086
    set -- $ip_part
    IFS="$OLD_IFS"
    if [ "$#" -ne 4 ] \
      || [ "$1" -gt 255 ] || [ "$2" -gt 255 ] \
      || [ "$3" -gt 255 ] || [ "$4" -gt 255 ]; then
      ADMIN_ALLOWLIST_VALID=false
      echo "ERROR: ADMIN_IP_ALLOWLIST octet out of range: $cidr" >&2
      break
    fi
    ADMIN_ALLOWLIST_BLOCK="$ADMIN_ALLOWLIST_BLOCK allow $cidr;"
  done
  unset IFS
  set +f
  if [ "$ADMIN_ALLOWLIST_VALID" = "true" ] && [ -n "$ADMIN_ALLOWLIST_BLOCK" ]; then
    ADMIN_ALLOWLIST_BLOCK="$ADMIN_ALLOWLIST_BLOCK deny all;"
  else
    # Validation failed (or nothing matched): refuse to inject any
    # `allow` lines — fall back to fail-closed.
    ADMIN_ALLOWLIST_BLOCK="deny all;"
  fi
fi
export ADMIN_ALLOWLIST_BLOCK
