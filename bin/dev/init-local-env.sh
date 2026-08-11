#!/usr/bin/env bash
# Create .env (git-ignored) with the full machine configuration:
#   - REPO_PATH/REPO_LOG/REUTER_INI + EMA_TARGET: runtime config consumed
#     by phprun and the framework's Database class (framework contract);
#   - MYSQL_*/PROD_USER: derived from the repo root (pure path arithmetic
#     on $PWD); consumed by the dev shell hook (flake.nix) and the Makefile;
#   - DBUSER: mapped for this machine in etc/dev-machines.ini (needed for
#     remote access to prod; skipped with a warning when the mapping does
#     not exist).
# Backend for the Makefile target _dev-init-local-env (make dev-init).
# Always regenerates the file: a stale .env would silently misconfigure
# phprun (it loads the repo-root .env from the CWD at runtime).

set -euo pipefail

# Derived from the repo root. The nix shell exports nothing anymore: these
# values are pure path arithmetic, owned by dev-init.
REPO_PATH="$PWD"
REPO_VAR="$REPO_PATH/var"
REPO_LOG="$REPO_VAR/log"
MYSQL_BASE_DIR="$REPO_VAR/mariadb"
MYSQL_DATA_DIR="$MYSQL_BASE_DIR/data"
MYSQL_UNIX_PORT="$MYSQL_BASE_DIR/mysql.sock"
MYSQL_PID_FILE="$MYSQL_BASE_DIR/mysql.pid"
PROD_USER="simox"

{
    printf 'export REPO_PATH=%s
' "$REPO_PATH"
    printf 'export REPO_LOG=%s
' "$REPO_LOG"
    printf 'export MYSQL_BASE_DIR=%s
' "$MYSQL_BASE_DIR"
    printf 'export MYSQL_DATA_DIR=%s
' "$MYSQL_DATA_DIR"
    printf 'export MYSQL_UNIX_PORT=%s
' "$MYSQL_UNIX_PORT"
    printf 'export MYSQL_PID_FILE=%s
' "$MYSQL_PID_FILE"
    printf 'export PROD_USER=%s
' "$PROD_USER"
    printf 'export REUTER_INI=%s/etc/reuter.ini
' "$REPO_PATH"
    printf 'export EMA_TARGET=local
'

    if [ ! -f etc/dev-machines.ini ]; then
        echo "WARNING: etc/dev-machines.ini not found. Skipping DBUSER (needed for remote access only)." >&2
    else
        _dbuser=$(grep "^$(hostname)=" etc/dev-machines.ini | cut -d= -f2)
        if [ -z "$_dbuser" ]; then
            echo "WARNING: hostname '$(hostname)' not found in etc/dev-machines.ini. Skipping DBUSER (needed for remote access only)." >&2
        else
            printf 'export DBUSER=%s
' "$_dbuser"
        fi
    fi
} > .env

echo "    Created .env (dev: REPO_PATH=$REPO_PATH, EMA_TARGET=local)"
