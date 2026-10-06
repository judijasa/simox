#!/usr/bin/env bash
# Consumer provisioning extra (simox data): Apache/www-data traversal.
# Runs as root on the remote via `deploy` (DEPLOY_INIT_CMD), after the
# framework's generic provisioning (bin/pf-provision.sh, which asserts the
# app user and creates permanent dirs but no longer provisions MariaDB — DB
# instances are created by `ema create`). Only web traversal is consumer-owned.
# DEPLOY_TARGET_DIR is supplied by the replayed deploy.conf environment.
set -euo pipefail
# One-time setup: Apache's www-data must traverse /srv and the deploy parent
# dir to reach the repo (the repo dir itself is re-chmodded by deploy).
chmod o+x /srv "$(dirname "$DEPLOY_TARGET_DIR")"
echo "Provisioning extras complete."
