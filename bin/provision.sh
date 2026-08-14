#!/usr/bin/env bash
# One-time production provisioning (consumer data).
#
# Runs as root on the remote via the framework `deploy --init` flow
# (DEPLOY_INIT_CMD in etc/deploy.conf), from the deployed repo root — the
# repo directory already exists at this point (created by the deploy swap).
# Replaces the former Makefile prod-init targets (assert-user, create-dirs,
# init-cluster); provisioning is machine-state setup, so it stays in the
# consumer as data rather than becoming framework mechanism.
#
# Usage: provision.sh   (root; config from etc/deploy.conf)

set -euo pipefail

set -a
. ./etc/deploy.conf
set +a

# Simox-specific provisioning state (not part of the shared deploy config).
PROD_DB_DATA_DIR="/var/lib/simox/mariadb/data"

# 1. Assert the provisioning system user exists.
echo "Asserting that system user '$PROD_USER' exists..."
if ! id -u "$PROD_USER" >/dev/null 2>&1; then
    echo "ERROR: System user '$PROD_USER' does not exist on this host."
    echo "Please provision the user before running this deployment."
    exit 1
fi

# 2. Create permanent system dirs owned by the app user, plus the deploy
#    parent dir (traversal bits for Apache's www-data).
echo "Creating permanent system logging and storage directories..."
mkdir -p "$DEPLOY_LOG_DIR" "$PROD_DB_DATA_DIR"
chown -R "$PROD_USER:$PROD_USER" "$DEPLOY_LOG_DIR" "$PROD_DB_DATA_DIR"
echo "Creating deploy parent directory..."
mkdir -p "$(dirname "$DEPLOY_TARGET_DIR")"
chown "$PROD_USER:$PROD_USER" "$(dirname "$DEPLOY_TARGET_DIR")"
chmod o+x /srv "$(dirname "$DEPLOY_TARGET_DIR")"

# 3. Initialize the raw MariaDB cluster structures (data dir owned by the
#    app user). No --auth-root-authentication-method flag here: prod uses
#    unix_socket auth, unlike the dev sandbox.
echo "Initializing raw MariaDB cluster structures..."
if [ ! -d "$PROD_DB_DATA_DIR" ]; then
    mariadb-install-db --datadir="$PROD_DB_DATA_DIR" --user="$PROD_USER"
else
    echo "    MariaDB cluster already initialized. Skipping."
fi

echo "Provisioning complete."
