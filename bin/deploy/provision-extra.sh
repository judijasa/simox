#!/usr/bin/env bash
# Consumer provisioning extra (simox data): Apache/www-data traversal.
# Runs as root on the remote via `deploy --init` (DEPLOY_INIT_CMD), after the
# framework's generic provisioning (bin/provision.sh).
set -euo pipefail
set -a
. ./etc/deploy.conf
set +a
# One-time setup: Apache's www-data must traverse /srv and the deploy parent
# dir to reach the repo (the repo dir itself is re-chmodded by deploy).
chmod o+x /srv "$(dirname "$DEPLOY_TARGET_DIR")"
echo "Provisioning extras complete."
