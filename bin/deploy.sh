#!/usr/bin/env bash
# simox deploy — consumer entrypoint that wraps the framework
# `pf-deploy.sh` CLI (vendor/bin/pf-deploy.sh).
#
# Two halves, both driven from here:
#
#   1. simox-owned private config: bin/fetch-private-data materializes the real
#      etc/ files locally from the private config repo (the single source of
#      truth). The runtime files (reuter.ini and ema.conf) are shipped to each
#      host by the framework's DEPLOY_PRIVATE_FILES key; an absent name (e.g.
#      reuter.ini on a no-database bootstrap) is confirmed by the operator
#      before the framework ships. The deploy values themselves travel as
#      replayed environment, never as a prod file.
#
#   2. Framework `pf-deploy.sh`, a closed operation: it swaps the repo, copies
#      the nix closure, installs composer deps, ships DEPLOY_PRIVATE_FILES,
#      replays the deploy.conf environment to every remote step, runs idempotent
#      provisioning and — as built-in steps on every host — regenerates .env
#      (gen-env) and verifies DB connectivity (db-check, warn-only; reuter.ini
#      is private data, not regenerated), then installs the cron-manifest
#      output (cron jobs) on every host, scope-filtered by that host's tags.
#      `db` is the framework's built-in tag (every tag doubles as a cron
#      scope), so this wrapper forwards its args verbatim to it — resolving a
#      typed host name to that host's ZeroTier IP first, via the shared host
#      lookup, so the roster re-derivation below matches — then re-derives the
#      roster (ZeroTier IP → tags) from etc/machines.ini via the shared
#      pf-roster CLI and runs the consumer server-side post-deploy step
#      (bin/deploy/server-side-post-deploy.sh) on each host, passing that
#      host's tag list via DEPLOY_TAGS — it covers only the consumer-owned tags
#      (`web`).
#
# Usage (from the repo root, inside `nix develop`):
#   bin/deploy.sh                 # every prod host
#   bin/deploy.sh <host>          # a single prod host (in the roster), as its
#                                 # short name or its ZeroTier IP
set -euo pipefail

# Run from the repo root (vendor/bin/pf-deploy.sh and etc/* are relative to it).
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

# Which host(s) this run targets: the first non-flag positional arg (if any),
# else every prod host. The framework CLI resolves and validates any host and
# deploys to exactly this set.
wanted=""
for arg in "$@"; do
  [[ "$arg" == -* ]] || { wanted="$arg"; break; }
done

# 1. Private config (simox-owned): materialize the real etc/ files locally
#    (idempotent). Shipping + env replay happen inside the framework deploy.
bin/fetch-private-data "$REPO_ROOT"

# 1b. Deploy-time confirmation: DEPLOY_PRIVATE_FILES names the private files the
#     framework ships into etc/ on each host. A name absent from etc/ after
#     materialization is either intentional (e.g. no database provisioned yet)
#     or a missed materialization — ask the operator instead of shipping a
#     partial set silently. Read the value in a subshell so deploy.conf's
#     exports do not leak into this shell (pf-deploy.sh must discover them
#     itself to build its replay environment).
shipped="$( . ./etc/deploy.conf >/dev/null 2>&1; printf '%s' "${DEPLOY_PRIVATE_FILES:-}" )"
missing=""
# shellcheck disable=SC2086
for _f in $shipped; do
    [ -f "etc/$_f" ] || missing="${missing:+$missing }$_f"
done
if [ -n "$missing" ]; then
    printf 'deploy: private file(s) absent from etc/ (not shipped):%s\n' "$missing" >&2
    printf 'Continue (e.g. no database provisioned yet)? [y/N] ' >&2
    answer=""
    read -r answer || true
    case "$answer" in
        [yY]|[yY][eE][sS]) ;;
        *) echo 'deploy: aborted — absent private file(s) not confirmed.' >&2; exit 1 ;;
    esac
fi

# 2. Target host in the form the roster is keyed by (its ZeroTier IP).
#    The shared host lookup takes either spelling, so a name typed above is
#    resolved here — after step 1 (it reads the materialized etc/machines.ini
#    and etc/hosts) and before anything is deployed, so an unknown name or a
#    host outside the roster aborts the whole run rather than skipping a step
#    later. pf-host prints the reason itself.
if [ -n "$wanted" ]; then
  if ! wanted="$(vendor/bin/pf-host "$wanted")"; then
    exit 1
  fi
fi

# 3. Framework deploy: swap, nix, composer, ship DEPLOY_PRIVATE_FILES, replay
#    deploy.conf env, idempotent provisioning.
vendor/bin/pf-deploy.sh "$@"

# 4. Deploy config (deploy-machine private data; DEPLOY_TARGET_DIR for the
#    remote post-deploy step).
set -a
. ./etc/deploy.conf
set +a

# 5. Shared roster parse (framework pf-roster CLI): "zerotier-ip=tags" per
#    line, keyed the same way as the resolved `wanted` above.
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
  matched=""
  while IFS='=' read -r host tags; do
    [ -n "$host" ] || continue
    if [ "$host" = "$wanted" ]; then
      post_deploy_one "$host" "$tags"
      matched=1
      break
    fi
  done < <(read_prod_roster)
  # `wanted` is already a roster key (step 2), so this cannot happen; fail
  # loudly rather than let the post-deploy step go missing.
  if [ -z "$matched" ]; then
    echo "deploy: '$wanted' is missing from the roster output;" \
      "aborting instead of skipping its post-deploy step" >&2
    exit 1
  fi
else
  while IFS='=' read -r host tags; do
    [ -n "$host" ] || continue
    post_deploy_one "$host" "$tags"
  done < <(read_prod_roster)
fi
