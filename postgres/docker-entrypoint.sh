#!/bin/sh
set -e

# Copy TLS certs to a writable location and set permissions for the postgres user
if [ -f /tls/server.key ] && [ -f /tls/server.pem ] && [ -f /tls/ca.pem ]; then
  cp /tls/server.key /tls/server.pem /tls/ca.pem /etc/postgresql/tls/
  chown postgres:postgres /etc/postgresql/tls/*
  chmod 600 /etc/postgresql/tls/server.key
  chmod 644 /etc/postgresql/tls/server.pem /etc/postgresql/tls/ca.pem
  echo "TLS certificates configured"
else
  echo "ERROR: Missing required TLS files in /tls/. Need server.key, server.pem, and ca.pem" >&2
  exit 1
fi

# When running postgres, inject config file flags; otherwise forward as-is
# (e.g. docker compose run postgres psql, bash, etc.)
if [ "${1:-postgres}" = "postgres" ]; then
  set -- postgres \
    -c config_file=/etc/postgresql/postgresql.conf \
    -c hba_file=/etc/postgresql/pg_hba.conf
fi

exec docker-entrypoint.sh "$@"
