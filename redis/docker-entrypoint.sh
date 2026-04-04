#!/bin/sh
set -e

ACL_FILE="/etc/redis/users.acl"

if [ -z "$REDIS_PASSWORD" ]; then
  echo "ERROR: REDIS_PASSWORD environment variable is required" >&2
  exit 1
fi

# Write ACL file with the actual password from env
cat > "$ACL_FILE" <<EOF
user default off
user ${REDIS_USERNAME:-app} on >${REDIS_PASSWORD} ~* &* +@all -@admin -@dangerous
EOF

echo "Redis ACL configured for user: ${REDIS_USERNAME:-app}"

# Copy TLS certs to a writable location and set permissions for the redis user
if [ -f /tls/server.key ] && [ -f /tls/server.pem ] && [ -f /tls/ca.pem ]; then
  cp /tls/server.key /tls/server.pem /tls/ca.pem /etc/redis/tls/
  chown redis:redis /etc/redis/tls/*
  chmod 600 /etc/redis/tls/server.key
  chmod 644 /etc/redis/tls/server.pem /etc/redis/tls/ca.pem
  echo "TLS certificates configured"
else
  echo "ERROR: Missing required TLS files in /tls/. Need server.key, server.pem, and ca.pem" >&2
  exit 1
fi

exec redis-server /etc/redis/redis.conf
