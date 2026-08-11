#!/usr/bin/env bash
# Generate the production .env at the repo root with absolute paths.
# Called by deploy.sh on every remote deploy and manually via
# `make prod-gen-env` (run as root on the prod server).
#
# The deployed repo directory is replaced on each deploy, so .env must be
# regenerated every time — and it MUST be complete before cron is installed:
# cron runs `phprun` from the repo root, the nix wrapper sources this file,
# and if a key is missing the framework silently falls back to its defaults
# (e.g. EMA_TARGET=local -> wrong DB section in production). Hence the
# fail-fast checks below.
# Usage: gen-env.sh [target-dir]   (defaults to $PWD)

set -euo pipefail

TARGET_DIR="${1:-$PWD}"
ENV_FILE="$TARGET_DIR/.env"

{
    printf '# Production environment (git-ignored, regenerated on every deploy).
'
    printf 'export REPO_PATH=/srv/apps/simox
'
    printf 'export REPO_LOG=/var/log/simox
'
    printf 'export REUTER_INI=/etc/simox/reuter.ini
'
    printf 'export EMA_TARGET=prod
'
} > "$ENV_FILE"

# Deploy-time guard: fail loudly instead of silently misrouting prod cron
# jobs to the framework's default (local) configuration.
for _need in 'export REPO_PATH=/srv/apps/simox' 'export REPO_LOG=/var/log/simox' 'export REUTER_INI=/etc/simox/reuter.ini' 'export EMA_TARGET=prod'; do
    grep -qxF "$_need" "$ENV_FILE" || {
        echo "ERROR: $ENV_FILE is missing required line: $_need" >&2
        exit 1
    }
done

echo "    Wrote $ENV_FILE (prod: EMA_TARGET=prod)"
