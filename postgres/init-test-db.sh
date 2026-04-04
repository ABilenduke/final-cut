#!/bin/bash
set -e

# Create the test database using the same user as the main database
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE ${POSTGRES_TEST_DB:-final_cut_test};
EOSQL
