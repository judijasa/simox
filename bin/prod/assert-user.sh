#!/usr/bin/env bash
# Assert that the provisioning system user exists before deployment.
# Backend for the Makefile target _prod-assert-user (make prod-init).
# Usage: assert-user.sh <prod-user>

set -euo pipefail

PROD_USER="$1"

echo "Asserting that system user '$PROD_USER' exists..."
if ! id -u "$PROD_USER" >/dev/null 2>&1; then
    echo "ERROR: System user '$PROD_USER' does not exist on this host."
    echo "Please provision the user before running this deployment."
    exit 1
fi
