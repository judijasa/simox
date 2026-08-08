#!/usr/bin/env bash
# Provision permanent system directories owned by the app user.
# Backend for the Makefile target _prod-create-dirs (make prod-init).
# Usage: create-dirs.sh <log-dir> <db-data-dir> <prod-user>

set -euo pipefail

PROD_LOG_DIR="$1"
PROD_DB_DATA_DIR="$2"
PROD_USER="$3"

echo "Creating permanent system logging and storage directories..."
mkdir -p "$PROD_LOG_DIR" "$PROD_DB_DATA_DIR"
chown -R "$PROD_USER:$PROD_USER" "$PROD_LOG_DIR" "$PROD_DB_DATA_DIR"
echo "Creating deploy parent directory..."
mkdir -p /srv/apps
chown "$PROD_USER:$PROD_USER" /srv/apps
chmod o+x /srv /srv/apps
