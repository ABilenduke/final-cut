#!/bin/sh
# Development-only entrypoint.
# Runs as root to fix ownership on the /app/.nuxt named volume that
# Docker creates as root:root by default, then drops to devuser to
# exec the CMD (deno task dev).
#
# Without this, Nuxt cannot write nuxt.json, the Nitro cache, or build
# output to /app/.nuxt and dev startup fails with EACCES.
#
# This script is NOT used in production — it only exists in the
# development stage of the Dockerfile.

set -e

DEV_UID="${DEV_UID:-1000}"
DEV_GID="${DEV_GID:-1000}"

# Fix ownership on the .nuxt volume. The !-user filter keeps repeat
# starts cheap — only entries not already owned by devuser are touched.
if [ -d /app/.nuxt ]; then
    find /app/.nuxt ! -user "$DEV_UID" -exec chown "$DEV_UID:$DEV_GID" {} + 2>/dev/null || true
fi

exec runuser -u devuser -- "$@"
