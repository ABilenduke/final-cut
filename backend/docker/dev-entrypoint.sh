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

# Ensure the node_modules named volume root is owned by devuser so npm
# can write into it without root-owned permission errors.
if [ -d node_modules ]; then
    chown "$DEV_UID:$DEV_GID" node_modules 2>/dev/null || true
fi

# Pre-create the admin auth events log so the Fail2ban admin-login jail
# (which mounts the same shared `backend-logs` volume read-only) can boot
# without waiting for the first failed login. Without this, fail2ban
# crashes with "No file(s) found for glob /var/log/admin/admin-auth-events.log"
# on a freshly-created volume.
if [ -d storage/logs ]; then
    touch storage/logs/admin-auth-events.log
    chown "$DEV_UID:$DEV_GID" storage/logs/admin-auth-events.log
    chmod 0640 storage/logs/admin-auth-events.log
fi

exec "$@"
