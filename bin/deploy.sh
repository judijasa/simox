#!/usr/bin/env bash
# simox deploy — consumer entrypoint that wraps the framework
# `pf-deploy.sh` CLI (vendor/bin/pf-deploy.sh).
#
# The framework `pf-deploy.sh` is a closed operation: it swaps the repo, copies the
# nix closure, installs composer deps and (with --init) runs one-time
# provisioning — it invokes no consumer hooks. This wrapper forwards its args
# verbatim to it, then re-derives the [prod] roster (host → tags) from
# etc/machines.ini via the shared pf-roster CLI and runs the consumer
# server-side post-deploy step (bin/deploy/server-side-post-deploy.sh) on each
# host, passing that host's tag list via DEPLOY_TAGS.
#
# Usage (from the repo root, inside `nix develop`):
#   bin/deploy.sh                 # every [prod] host
#   bin/deploy.sh <host>          # a single prod host (in [prod])
#   bin/deploy.sh --init [host]   # + one-time provisioning
set -euo pipefail

# Run from the repo root (vendor/bin/pf-deploy.sh and etc/* are relative to it).
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

# 1. Framework deploy: swap, nix, composer, optional --init provisioning.
vendor/bin/pf-deploy.sh "$@"

# 2. Project-static deploy config (DEPLOY_TARGET_DIR for the remote step).
set -a
. ./etc/deploy.conf
set +a

# 3. Shared roster parse (framework pf-roster CLI): "host=tags" per line.
read_prod_roster() {
  vendor/bin/pf-roster --list
}

# 4. Which host(s) get the post-deploy step: the first non-flag positional arg
#    (if any), else every [prod] host. The framework CLI has already validated
#    any host and deployed to exactly this set.
wanted=""
for arg in "$@"; do
  [[ "$arg" == -* ]] || { wanted="$arg"; break; }
done

post_deploy_one() {
  local host="$1"
  local tags="$2"
  ssh "root@$host" "cd '$DEPLOY_TARGET_DIR' && DEPLOY_TAGS='$tags' bin/deploy/server-side-post-deploy.sh"
}

if [ -n "$wanted" ]; then
  while IFS='=' read -r host tags; do
    [ -n "$host" ] || continue
    if [ "$host" = "$wanted" ]; then
      post_deploy_one "$host" "$tags"
      break
    fi
  done < <(read_prod_roster)
else
  while IFS='=' read -r host tags; do
    [ -n "$host" ] || continue
    post_deploy_one "$host" "$tags"
  done < <(read_prod_roster)
fi
