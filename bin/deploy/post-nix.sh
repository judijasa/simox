#!/usr/bin/env bash
# Consumer post-nix hook for the framework `deploy` CLI (consumer data:
# which mechanisms to run at which point of the deploy flow).
#
# Runs on the remote, as root, AFTER the deploy CLI has copied the nix
# closure and composer deps (and, for --init, after provisioning). At this
# point the framework CLIs (gen-env, cron-manifest) and nix php are available
# under $DEPLOY_NIX_RESULT_DIR/result/bin. The swap-time post-swap.sh hook
# runs earlier, before nix is copied — anything needing the nix result
# belongs here.
#
# .env regeneration must complete before cron is installed: cron runs
# `phprun` from the repo root, the nix wrapper sources .env, and a missing
# key would silently fall back to the framework defaults (e.g. EMA_TARGET=
# local -> wrong DB section in production). Hence gen-env runs first and
# fails loudly on a partial write.

set -euo pipefail

# Runtime config (REPO_PATH) + project-static deploy config (PROD_USER,
# DEPLOY_*, CRON_FILE) — the deployed repo root is the CWD (the deploy CLI
# cds there before invoking the hook).
if [[ -f .env ]]; then
    set -a
    . ./.env
    set +a
fi
set -a
. ./etc/deploy.conf
set +a

# Framework CLIs + nix php live in the deployed nix result.
export PATH="$DEPLOY_NIX_RESULT_DIR/result/bin:$PATH"

echo "    Regenerating production .env..."
gen-env "$PWD"

echo "    Refreshing /etc reuter.ini [prod] connectivity..."
gen-reuter "$REUTER_INI"

echo "    Updating cron jobs from #[CronJob]/#[Agent] attributes..."
cron-manifest > "$CRON_FILE"
chmod 644 "$CRON_FILE"
systemctl restart cron || systemctl restart crond
echo "    Cron jobs installed to $CRON_FILE."
