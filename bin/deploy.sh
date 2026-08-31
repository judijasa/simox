#!/usr/bin/env bash
# simox deploy — consumer entrypoint that wraps the framework
# `pf-deploy.sh` CLI (vendor/bin/pf-deploy.sh).
#
# The framework `pf-deploy.sh` is a closed operation: it swaps the repo, copies the
# nix closure, installs composer deps and (with --init) runs one-time
# provisioning — it invokes no consumer hooks. This wrapper forwards its args
# verbatim to it, then re-derives the [prod] roster from etc/machines.ini and
# runs the consumer post-deploy step (bin/deploy/post-pf-deploy.sh) on each host.
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

# 3. Re-derive the [prod] roster (one host per line), mirroring the
#    framework's own roster read.
read_prod_roster() {
  php -r '
    $cnf = parse_ini_file($argv[1], true, INI_SCANNER_RAW);
    if (!$cnf || !isset($cnf["prod"])) {
      fwrite(STDERR, "deploy: no [prod] section in etc/machines.ini\n");
      exit(1);
    }
    foreach ($cnf["prod"] as $host => $db) {
      echo $host, PHP_EOL;
    }
  ' ./etc/machines.ini
}

# 4. Which host(s) get the post-deploy step: the positional host argument (if
#    any), else every [prod] host. The framework CLI has already validated any
#    host and deployed to exactly this set.
wanted=""
for arg in "$@"; do
  case "$arg" in
    --init) ;;
    -*) ;;
    *) wanted="$arg" ;;
  esac
done

post_deploy_one() {
  local host="$1"
  ssh "root@$host" "cd '$DEPLOY_TARGET_DIR' && bin/deploy/post-pf-deploy.sh"
}

if [ -n "$wanted" ]; then
  post_deploy_one "$wanted"
else
  while IFS= read -r host; do
    [ -n "$host" ] || continue
    post_deploy_one "$host"
  done < <(read_prod_roster)
fi
