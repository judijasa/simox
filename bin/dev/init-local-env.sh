#!/usr/bin/env bash
# Create .env (git-ignored) with the DBUSER mapped for this machine in
# etc/dev-machines.ini. Only needed for remote access to prod; skipped with a
# warning when the mapping does not exist.
# Backend for the Makefile target _dev-init-local-env (make dev-init).

set -euo pipefail

if [ -f .env ]; then
    echo ".env already exists. Skipping."
    exit 0
fi

if [ ! -f etc/dev-machines.ini ]; then
    echo "WARNING: etc/dev-machines.ini not found. Skipping .env creation (needed for remote access only)."
    exit 0
fi

_dbuser=$(grep "^$(hostname)=" etc/dev-machines.ini | cut -d= -f2)
if [ -z "$_dbuser" ]; then
    echo "WARNING: hostname '$(hostname)' not found in etc/dev-machines.ini. Skipping .env creation (needed for remote access only)."
    exit 0
fi

printf '# Machine-specific environment (git-ignored).
export DBUSER=%s
' "$_dbuser" > .env
echo "    Created .env with DBUSER=$_dbuser"
