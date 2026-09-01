#!/usr/bin/env bash
# Consumer server-side post-deploy step (simox data): run on each prod host
# by the deploy entrypoint (bin/deploy.sh) AFTER the framework `pf-deploy.sh`
# CLI has copied the nix closure and composer deps (and, for --init, after
# provisioning). It runs ON the prod server (not locally), hence the
# "server-side" name. At this point the framework CLIs (gen-env, gen-reuter,
# cron-manifest, phprun) are Composer-delivered under
# $DEPLOY_TARGET_DIR/vendor/bin, and php + the environment binaries live
# under $DEPLOY_NIX_RESULT_DIR/result/bin.
#
# The wrapper passes this host's `tag[:name]` tokens from etc/machines.ini via
# DEPLOY_TAGS (comma-separated). Tag-gated steps:
#   worker  -> install the cron-manifest output (cron jobs)
#   web     -> restore Apache www-data traversal on the freshly-swapped repo
#              dir (chmod o+x $DEPLOY_TARGET_DIR)
# gen-env and gen-reuter run on every host, as before.
#
# .env regeneration must complete before cron is installed: cron runs
# `phprun` from the repo root, the phprun wrapper sources .env, and a missing
# key would silently fall back to the framework defaults (e.g. EMA_MODE=
# dev -> wrong DB section in production). Hence gen-env runs first and
# fails loudly on a partial write.

set -euo pipefail

# Runtime config (REPO_PATH) + project-static deploy config (PROD_USER,
# DEPLOY_*, CRON_FILE) — the deployed repo root is the CWD (bin/deploy.sh
# cds there before running this step).
if [[ -f .env ]]; then
    set -a
    . ./.env
    set +a
fi
set -a
. ./etc/deploy.conf
set +a

# This host's tag list (comma-separated), passed by the wrapper. Defaults to
# empty so the script is safe to run standalone.
DEPLOY_TAGS="${DEPLOY_TAGS:-}"

has_tag() {
    local tag="$1"
    local taglist="${DEPLOY_TAGS:-}"
    local tok
    for tok in ${taglist//,/ }; do
        [[ "$tok" == "$tag" ]] && return 0
    done
    return 1
}

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

if has_tag worker; then
    echo "    Updating cron jobs from #[CronJob]/#[Agent] attributes..."
    cron-manifest > "$CRON_FILE"
    chmod 644 "$CRON_FILE"
    systemctl restart cron || systemctl restart crond
    echo "    Cron jobs installed to $CRON_FILE."
fi

if has_tag web; then
    echo "    Restoring Apache www-data traversal on the repo dir..."
    chmod o+x "$DEPLOY_TARGET_DIR"
fi
