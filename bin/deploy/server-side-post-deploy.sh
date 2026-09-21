#!/usr/bin/env bash
# Consumer server-side post-deploy step (simox data): run on each prod host
# by the deploy entrypoint (bin/deploy.sh) AFTER the framework `pf-deploy.sh`
# CLI has completed its built-in server steps (regenerating .env via gen-env and
# verifying DB connectivity via db-check — warn-only — on every host, and
# installing the cron-manifest output on hosts tagged `worker`; the private
# etc/ files were shipped earlier by the framework's DEPLOY_PRIVATE_FILES). It
# runs ON the prod server (not locally), hence the "server-side" name.
#
# Only consumer-owned tag steps remain here:
#   web -> restore Apache www-data traversal on the freshly-swapped repo dir
#          (chmod o+x $PWD), and install the nix-built php-fpm (pool config +
#          systemd unit) so the website runs the flake-pinned PHP, not the
#          system mod_php.
#
# The wrapper passes this host's `tag[:name]` tokens from etc/machines.ini via
# DEPLOY_TAGS (comma-separated), plus the deploy.conf values the php-fpm render
# needs (DEPLOY_REUTER_INI, DEPLOY_LOG_DIR, DEPLOY_NIX_RESULT_DIR). `db` (named)
# and `worker` (bare) are the framework's built-in tags and are handled by
# pf-deploy itself — this script must not re-run them.

set -euo pipefail

# The deployed repo root is the CWD (bin/deploy.sh cds there before running
# this step) and IS the deploy target dir.
DEPLOY_TARGET_DIR="$PWD"

# This host's tag list (comma-separated), passed by the wrapper. Defaults to
# empty so the script is safe to run standalone.
DEPLOY_TAGS="${DEPLOY_TAGS:-}"

# Deploy values replayed by the wrapper (bin/deploy.sh) from etc/deploy.conf.
# Empty when run standalone; the `web` step fails loudly if a web host lacks
# them, mirroring the framework's fail-fast gen-env guard.
DEPLOY_REUTER_INI="${DEPLOY_REUTER_INI:-}"
DEPLOY_LOG_DIR="${DEPLOY_LOG_DIR:-}"
DEPLOY_NIX_RESULT_DIR="${DEPLOY_NIX_RESULT_DIR:-}"

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

    echo "    Installing nix-built php-fpm (pool config + systemd unit)..."
    for _v in DEPLOY_REUTER_INI DEPLOY_LOG_DIR DEPLOY_NIX_RESULT_DIR; do
        if [ -z "${!_v:-}" ]; then
            echo "ERROR: $_v is required on a 'web' host (missing from the replayed deploy.conf)." >&2
            exit 1
        fi
    done

    mkdir -p /etc/simox
    sed \
        -e "s|@REUTER_INI@|$DEPLOY_REUTER_INI|g" \
        -e "s|@PHP_FPM_LOG@|$DEPLOY_LOG_DIR/php-fpm.log|g" \
        etc/php-fpm-simox.conf.template > /etc/simox/php-fpm-simox.conf
    sed \
        -e "s|@PHP_FPM_BIN@|$DEPLOY_NIX_RESULT_DIR/result/bin/php-fpm|g" \
        etc/php-fpm-simox.service.template > /etc/systemd/system/php-fpm-simox.service

    systemctl daemon-reload
    systemctl enable php-fpm-simox.service
    systemctl restart php-fpm-simox.service
fi
