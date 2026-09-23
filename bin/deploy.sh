#!/usr/bin/env bash
# simox deploy — consumer entrypoint that wraps the framework
# `pf-deploy.sh` CLI (vendor/bin/pf-deploy.sh).
#
# Two halves, both driven from here:
#
#   1. simox-owned private config: bin/fetch-private-data materializes the real
#      etc/ files locally from the private config repo (the single source of
#      truth). The one runtime file (reuter.ini) is shipped to each host by the
#      framework's DEPLOY_PRIVATE_FILES key; the deploy values themselves travel
#      as replayed environment, never as a prod file.
#
#   2. Framework `pf-deploy.sh`, a closed operation: it swaps the repo, copies
#      the nix closure, installs composer deps, ships DEPLOY_PRIVATE_FILES,
#      replays the deploy.conf environment to every remote step, runs idempotent
#      provisioning and — as built-in steps on every host — regenerates .env
#      (gen-env) and verifies DB connectivity (db-check, warn-only; reuter.ini
#      is private data, not regenerated), then installs the cron-manifest
#      output (cron jobs) on every host, scope-filtered by that host's tags.
#      `db` is the framework's built-in tag (every tag doubles as a cron
#      scope), so this wrapper forwards its args verbatim to it, then
#      re-derives the [prod] roster (host → tags) from etc/machines.ini via the
#      shared pf-roster CLI and runs the consumer server-side post-deploy step
#      (bin/deploy/server-side-post-deploy.sh) on each host, passing that
#      host's tag list via DEPLOY_TAGS — it covers only the consumer-owned tags
#      (`web`).
#
# Usage (from the repo root, inside `nix develop`):
#   bin/deploy.sh                 # every [prod] host
#   bin/deploy.sh <host>          # a single prod host (in [prod])
set -euo pipefail

# Run from the repo root (vendor/bin/pf-deploy.sh and etc/* are relative to it).
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

# Which host(s) this run targets: the first non-flag positional arg (if any),
# else every [prod] host. The framework CLI has already validated any host and
# deploys to exactly this set.
wanted=""
for arg in "$@"; do
  [[ "$arg" == -* ]] || { wanted="$arg"; break; }
done

# 1. Private config (simox-owned): materialize the real etc/ files locally
#    (idempotent). Shipping + env replay happen inside the framework deploy.
bin/fetch-private-data "$REPO_ROOT"

# 2. Framework deploy: swap, nix, composer, ship DEPLOY_PRIVATE_FILES, replay
#    deploy.conf env, idempotent provisioning.
vendor/bin/pf-deploy.sh "$@"

# 3. Deploy config (deploy-machine private data; DEPLOY_TARGET_DIR for the
#    remote post-deploy step).
set -a
. ./etc/deploy.conf
set +a

# 4. Shared roster parse (framework pf-roster CLI): "host=tags" per line.
read_prod_roster() {
  vendor/bin/pf-roster --list
}

post_deploy_one() {
  local host="$1"
  local tags="$2"
  # Replay the deploy.conf values the `web` step needs (php-fpm render) into
  # the remote step, mirroring how the framework replays deploy.conf env.
  ssh "root@$host" "cd '$DEPLOY_TARGET_DIR' && \
    DEPLOY_TAGS='$tags' \
    DEPLOY_REUTER_INI='$DEPLOY_REUTER_INI' \
    DEPLOY_LOG_DIR='$DEPLOY_LOG_DIR' \
    DEPLOY_NIX_RESULT_DIR='$DEPLOY_NIX_RESULT_DIR' \
    bin/deploy/server-side-post-deploy.sh"
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
