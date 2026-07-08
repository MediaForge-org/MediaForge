#!/usr/bin/env bash
# Runs once on first Postgres init: creates the parallel test database and
# enables the required extensions on both databases.
set -euo pipefail

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-SQL
    CREATE DATABASE mediaforge_test OWNER $POSTGRES_USER;
SQL

for db in "$POSTGRES_DB" mediaforge_test; do
    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$db" <<-SQL
        CREATE EXTENSION IF NOT EXISTS pg_trgm;
        CREATE EXTENSION IF NOT EXISTS btree_gist;
        CREATE EXTENSION IF NOT EXISTS vector;
SQL
done
