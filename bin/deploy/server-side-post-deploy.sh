#!/usr/bin/env bash
# Consumer server-side post-deploy step (simox data): run on each prod host
# by the deploy entrypoint (bin/deploy.sh) AFTER the framework `pf-deploy.sh`
# CLI has completed its built-in server steps (linking reuter.ini via
# fetch-private-data, regenerating .env via gen-env and verifying DB
# connectivity via db-check — warn-only — on every host, and installing the
# cron-manifest output on hosts tagged `worker`). It runs ON
# the prod server (not locally), hence the "server-side" name.
#
# Only consumer-owned tag steps remain here:
#   web -> restore Apache www-data traversal on the freshly-swapped repo dir
#          (chmod o+x $DEPLOY_TARGET_DIR)
#
# The wrapper passes this host's `tag[:name]` tokens from etc/machines.ini via
# DEPLOY_TAGS (comma-separated). `db` (named) and `worker` (bare) are the
# framework's built-in tags and are handled by pf-deploy itself — this script
# must not re-run them.

set -euo pipefail

# Project-static deploy config (DEPLOY_TARGET_DIR) — the deployed repo root is
# the CWD (bin/deploy.sh cds there before running this step).
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

if has_tag web; then
    echo "    Restoring Apache www-data traversal on the repo dir..."
    chmod o+x "$DEPLOY_TARGET_DIR"
fi
