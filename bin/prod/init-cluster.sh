#!/usr/bin/env bash
# Initialize the production MariaDB cluster (data dir owned by the app user).
# Backend for the Makefile target _prod-init-cluster (make prod-init).
# Usage: init-cluster.sh <db-data-dir> <prod-user>

set -euo pipefail

DB_DATA_DIR="$1"
PROD_USER="$2"

echo "Initializing raw MariaDB cluster structures..."
if [ ! -d "$DB_DATA_DIR" ]; then
    mariadb-install-db --datadir="$DB_DATA_DIR" --user="$PROD_USER"
else
    echo "    MariaDB cluster already initialized. Skipping."
fi
