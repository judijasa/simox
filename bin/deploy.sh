#!/usr/bin/env bash
# simox deploy — consumer entrypoint that wraps the framework
# `pf-deploy.sh` CLI (vendor/bin/pf-deploy.sh).
#
# The deploy is two halves, both driven from here:
#
#   1. simox-owned private config. The private config repo is the source of
#      truth and prod hosts have no git: bin/fetch-private-data materializes the
#      real etc/ files locally, bin/deploy-private-config ships the two runtime
#      files (deploy.conf, reuter.ini) whole to each target host's stable
#      DEPLOY_PRIVATE_CONFIG_DIR, and the hook those files name in
#      DEPLOY_PRE_PROVISION_CMD (bin/deploy/inject-private-config.sh) copies
#      them into the freshly swapped etc/ on the host.
#
#   2. Framework `pf-deploy.sh`, a closed operation: it swaps the repo, copies
#      the nix closure, installs composer deps, sources the real etc/deploy.conf
#      (running the hook above before anything needs it), runs idempotent
#      provisioning and — as built-in steps on every host — regenerates .env
#      (gen-env) and verifies DB connectivity (db-check, warn-only; reuter.ini
#      is private data, not regenerated); on hosts tagged `worker` it also
#      installs the cron-manifest output (cron jobs). `db` and `worker` are the
#      framework's built-in tags, so this wrapper forwards its args verbatim to
#      it, then re-derives the [prod] roster (host → tags) from etc/machines.ini
#      via the shared pf-roster CLI and runs the consumer server-side post-deploy
#      step (bin/deploy/server-side-post-deploy.sh) on each host, passing that
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

# 1. Private config (simox-owned): materialize the real etc/ files locally (both
#    steps are idempotent), then ship the two runtime files to the stable
#    per-app dir on each target host.
bin/fetch-private-data "$REPO_ROOT"
if [ -n "$wanted" ]; then
  bin/deploy-private-config "$wanted"
else
  bin/deploy-private-config
fi

# 2. Framework deploy: swap, nix, composer, idempotent provisioning (it sources
#    etc/deploy.conf and runs the DEPLOY_PRE_PROVISION_CMD hook on each host).
vendor/bin/pf-deploy.sh "$@"

# 3. Deploy config (private data, restored on the host by
#    bin/deploy/inject-private-config.sh; DEPLOY_TARGET_DIR for the remote step).
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
