#!/usr/bin/env bash
# Consumer post-deploy step (simox data): run on each prod host by the deploy
# wrapper (bin/deploy-wrapper.sh) AFTER the framework `deploy` CLI has copied
# the nix closure and composer deps (and, for --init, after provisioning). At
# this point the framework CLIs (gen-env, gen-reuter, cron-manifest, phprun)
# are Composer-delivered under $DEPLOY_TARGET_DIR/vendor/bin, and php + the
# environment binaries live under $DEPLOY_NIX_RESULT_DIR/result/bin.
#
# .env regeneration must complete before cron is installed: cron runs
# `phprun` from the repo root, the phprun wrapper sources .env, and a missing
# key would silently fall back to the framework defaults (e.g. EMA_MODE=
# dev -> wrong DB section in production). Hence gen-env runs first and
# fails loudly on a partial write.

set -euo pipefail

# Runtime config (REPO_PATH) + project-static deploy config (PROD_USER,
# DEPLOY_*, CRON_FILE) — the deployed repo root is the CWD (the deploy wrapper
# cds there before running this step).
if [[ -f .env ]]; then
    set -a
    . ./.env
    set +a
fi
set -a
. ./etc/deploy.conf
set +a

# Framework CLIs (gen-env, gen-reuter, cron-manifest, phprun) are now
# Composer-delivered ($DEPLOY_TARGET_DIR/vendor/bin); the nix result still
# ships php + the environment binaries. vendor/bin goes first so the CLIs
# resolve there, and the nix bin keeps php on PATH for the wrappers
# (phprun/cron-manifest both invoke `php`).
export PATH="$DEPLOY_TARGET_DIR/vendor/bin:$DEPLOY_NIX_RESULT_DIR/result/bin:$PATH"

# Cron entries need both phprun (vendor/bin) and php (nix result bin) on
# PATH; CRON_NIX_BIN is emitted by cron-manifest as the crontab `NIX_BIN=`
# env assignment, prepended to each entry's PATH.
export CRON_NIX_BIN="$DEPLOY_TARGET_DIR/vendor/bin:$DEPLOY_NIX_RESULT_DIR/result/bin"

echo "    Regenerating production .env..."
gen-env "$PWD"

echo "    Refreshing /etc reuter.ini [prod] connectivity..."
gen-reuter "$REUTER_INI"

echo "    Updating cron jobs from #[CronJob]/#[Agent] attributes..."
cron-manifest > "$CRON_FILE"
chmod 644 "$CRON_FILE"
systemctl restart cron || systemctl restart crond
echo "    Cron jobs installed to $CRON_FILE."
