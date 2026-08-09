#!/usr/bin/env bash
# Create .env (git-ignored) with the runtime configuration for this machine:
#   - PHPRUN_* + EMA_TARGET=local, derived from the SIMOX_* exports of the
#     nix develop shell (dev paths);
#   - DBUSER mapped for this machine in etc/dev-machines.ini (needed for
#     remote access to prod; skipped with a warning when the mapping does
#     not exist).
# Backend for the Makefile target _dev-init-local-env (make dev-init).
# Always regenerates the file: a stale .env would silently misconfigure
# phprun (the nix wrapper sources it from the CWD at runtime).

set -euo pipefail

# Derived from the dev shell (flake.nix shellHook). Missing SIMOX_* means we
# are not inside `nix develop`; the Makefile target is guarded by
# _dev-assert-nix, but fail loudly here too.
: "${SIMOX_REPO_PATH:?SIMOX_REPO_PATH is not set - run inside 'nix develop'}"
: "${SIMOX_LOG_PATH:?SIMOX_LOG_PATH is not set - run inside 'nix develop'}"

{
    printf '# Machine-specific environment (git-ignored).
'
    printf 'export PHPRUN_REPO_PATH=%s
' "$SIMOX_REPO_PATH"
    printf 'export PHPRUN_LOG_PATH=%s
' "$SIMOX_LOG_PATH"
    printf 'export PHPRUN_REUTER_INI=%s/etc/reuter.ini
' "$SIMOX_REPO_PATH"
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

echo "    Created .env (dev: PHPRUN_REPO_PATH=$SIMOX_REPO_PATH, EMA_TARGET=local)"
