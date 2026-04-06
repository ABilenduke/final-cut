#!/bin/sh
# Development-only entrypoint.
# Runs as root to fix bind-mount ownership for storage directories
# that may have been created by a previous container running as a
# different UID (e.g. www-data). Then exec's the CMD (php-fpm),
# which starts its master as root and drops workers to devuser
# via the FPM pool config.
#
# This script is NOT used in production — it only exists in the
# development stage of the Dockerfile.

set -e

DEV_UID="${DEV_UID:-1000}"
DEV_GID="${DEV_GID:-1000}"

# Fix storage dirs that the app needs to write to.
# Only touches files not already owned by devuser, keeping this fast on repeat starts.
for dir in storage bootstrap/cache; do
    if [ -d "$dir" ]; then
        find "$dir" ! -user "$DEV_UID" -exec chown "$DEV_UID:$DEV_GID" {} + 2>/dev/null || true
    fi
done

exec "$@"
