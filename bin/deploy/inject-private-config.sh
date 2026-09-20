#!/usr/bin/env bash
# inject-private-config — restore simox's private etc/ files on a prod host.
#
# This is the hook etc/deploy.conf names in DEPLOY_PRE_PROVISION_CMD: the
# framework pf-deploy.sh runs it as root, in the deployed repo root, right after
# the repo swap + composer install and before anything sources etc/deploy.conf
# (the swap replaces the repo dir, wiping etc/, where the repo carries only
# templates). The deploy machine's deploy.conf environment is replayed for the
# hook, so DEPLOY_* values are in scope even though the host has no deploy.conf
# of its own yet.
#
# It copies the two files bin/deploy-private-config shipped to
# DEPLOY_PRIVATE_CONFIG_DIR into etc/ as real files — the only private files a
# host needs: deploy.conf for the provisioning and server steps that source it,
# and reuter.ini at DEPLOY_REUTER_INI (which points at etc/reuter.ini).
# machines.ini, team.ini, hosts and host-hardening.php never reach a host.
#
# Runs on the host, as root, from the deployed repo root.

set -euo pipefail

if [[ ! -f etc/deploy.conf.template ]]; then
    echo "inject-private-config: no etc/deploy.conf.template here — run from the deployed repo root." >&2
    exit 1
fi

if [[ -z "${DEPLOY_PRIVATE_CONFIG_DIR:-}" ]]; then
    echo "inject-private-config: DEPLOY_PRIVATE_CONFIG_DIR is not set." >&2
    echo "  The hook expects the deploy machine's deploy.conf environment (see DEPLOY_PRE_PROVISION_CMD in etc/deploy.conf.template)." >&2
    exit 1
fi

if [[ ! -d "$DEPLOY_PRIVATE_CONFIG_DIR" ]]; then
    echo "inject-private-config: $DEPLOY_PRIVATE_CONFIG_DIR not found on this host." >&2
    echo "  bin/deploy-private-config (deploy machine) ships the private files there before the deploy." >&2
    exit 1
fi

for f in deploy.conf reuter.ini; do
    src="$DEPLOY_PRIVATE_CONFIG_DIR/$f"
    if [[ ! -f "$src" ]]; then
        echo "inject-private-config: $src not found (shipped by bin/deploy-private-config)." >&2
        exit 1
    fi
    rm -f "etc/$f"
    install -m 0644 "$src" "etc/$f"
    # Keep the swapped tree uniformly owned by the app user (pf-deploy chowns the
    # fresh repo to it); PROD_USER is replayed from the deploy machine.
    if [[ -n "${PROD_USER:-}" ]]; then
        chown "$PROD_USER:$PROD_USER" "etc/$f"
    fi
    echo "    Restored etc/$f from $DEPLOY_PRIVATE_CONFIG_DIR."
done
