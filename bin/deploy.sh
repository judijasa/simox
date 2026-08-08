#!/usr/bin/env bash

# This script has two purposes:
# 1. Continuous development deployment and
# 2. Initial deployment ("only-once ops")
# Usage:
#   deploy <target_host>
# For initial deployment:
#   deploy --init <target_host>
#
# Notes:
# - Processes that belong to initial deployment,
#   but are not part of continuous development are meant
#   to be in the Makefile file, under the target prod-init.
#   They are called in this script through `make prod-init`
#   whenever the --init option is used while calling deploys.sh
# - Processes that can be required for continuous deployment are
#   written directly in the deploy.sh file instead of in Makefile.
# - Deployment of nix packages is based on local building to reduce
#   resources requirements on the server side; it is executed
#   only if client and server have x86_64 architecture.
#   If in the future you want to support multiple architectures,
#   build remotely instead of using cross-compilation, in order to
#   the script simple (readability is a project priority).
#   Another reason to restrict to x86_64 is because one PHP Composer
#   dependency (Casper crawler) supports only two architectures and
#   x86_64 is one of them.

set -euo pipefail

flight_checks() {
  if [[ ! -n $IN_NIX_SHELL ]]; then
      echo "ERROR: This script must be run inside 'nix develop'"
      exit 1
  fi
  
  if [[ "$PWD" != "$SIMOX_REPO_PATH" ]]
  then
    echo "This command must be executed from the repository's root directory."
    exit 1
  fi

  if [ "$(git branch --show-current)" != "main" ]; then
    echo "ERROR: not on main branch"
    exit 1
  fi

  # Fetch the latest remote state without merging
  git fetch origin main 2>/dev/null
  local LOCAL_REPO_STATE=$(git rev-parse main)
  local REMOTE_REPO_STATE=$(git rev-parse origin/main)
  if [ "$LOCAL_REPO_STATE" != "$REMOTE_REPO_STATE" ]; then
    echo "ERROR: local main is not up to date with origin/main"
    echo "Local:  $LOCAL_REPO_STATE"
    echo "Remote: $REMOTE_REPO_STATE"
    exit 1
  fi

  if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "ERROR: working tree is not clean"
    exit 1
  fi

  if ping -c 1 -W 2 "${REMOTE_HOST}" &> /dev/null; then
    echo "Host ${REMOTE_HOST} is online."
  else
    echo "Host ${REMOTE_HOST} is unreachable."
    exit 1
  fi
}

deploy_repo_remotely() {
  local REMOTE_HOST="$1"
  local PROD_USER="$2"
  local REMOTE_TARGET_DIR="$3"
  local REV="$4"

  echo "Deploying commit: $REV" >&2

  # Deploy (atomic on remote)
  git archive "$REV" | ssh "root@$REMOTE_HOST" "
      set -e     
      
      if ! id \"$PROD_USER\" &>/dev/null; then
          echo \"User $PROD_USER doesn't exist. Create and setup ssh access to it.\" >&2
          exit 1
      fi

      # Define directories based on structural paths
      BASE_DIR=\$(dirname '$REMOTE_TARGET_DIR')
      FINAL_DIR='$REMOTE_TARGET_DIR'
      BACKUP_DIR=\"\${FINAL_DIR}_backup\"
      LOG_DIR='/var/log/simox'

      # Unpack inside the same parent base directory to ensure fast rename across the same mount point
      mkdir -p \"\$BASE_DIR\"
      TMP_DIR=\$(mktemp -d -p \"\$BASE_DIR\")
      echo 'Unpacking to temp...' >&2
      tar -x -C \"\$TMP_DIR\"
      
      # Ensure permissions are set before pushing live
      mkdir -p \"\$LOG_DIR\"
      chown -R $PROD_USER:$PROD_USER \"\$TMP_DIR\"
      chown $PROD_USER:$PROD_USER \"\$LOG_DIR\"

      # Clear out any previous backup directory
      rm -rf \"\$BACKUP_DIR\"

      # Near-Atomic Swap: Move current to backup, and instantly place the new one
      if [ -d \"\$FINAL_DIR\" ]; then
          echo 'Moving current codebase to backup...' >&2
          mv \"\$FINAL_DIR\" \"\$BACKUP_DIR\"
      fi

      echo 'Activating new repository codebase...' >&2
      mv \"\$TMP_DIR\" \"\$FINAL_DIR\"

      # Handle logging and capture old version output for stdout
      LOG_FILE=\"\$LOG_DIR/deploy_version.log\"
      touch \"\$LOG_FILE\"
      chown $PROD_USER:$PROD_USER \"\$LOG_FILE\"
      
      # Append current deployment info
      echo \"\$(date +'%Y-%m-%d %H:%M:%S %Z'): $REV\" >> \"\$LOG_FILE\"
      echo \"Deploy complete: $REV\" > \"\$FINAL_DIR/.deploy_version\"
      chown $PROD_USER:$PROD_USER \"\$FINAL_DIR/.deploy_version\"

      # Piggyback: Update cron jobs from #[CronJob] attributes in source
      echo 'Updating cron jobs...' >&2
      SIMOX_REPO_PATH=\"\$FINAL_DIR\" php \"\$FINAL_DIR/bin/update-cron-manifest\" > /etc/cron.d/simo-orchestrator
      chmod 644 /etc/cron.d/simo-orchestrator
      systemctl restart cron || systemctl restart crond
      echo 'Cron jobs updated.' >&2

      # Piggyback: Check if nix daemon is running (multi-user install)
      if systemctl is-active --quiet nix-daemon; then
        NIX_INSTALLED='true'
      else
        NIX_INSTALLED='false'
      fi

      # Output previous hash, NIX_INSTALLED, and arch to stdout (separated by spaces)
      if [ -s \"\$LOG_FILE\" ]; then
          echo \"\$(tail -n 1 \"\$LOG_FILE\" | awk '{print \$NF}') \$NIX_INSTALLED \$(uname -m)\"
      else
          echo \"None \$NIX_INSTALLED \$(uname -m)\"
      fi
  "
}

install_nix_remotely() {
  local REMOTE_HOST="$1"
  local PROD_USER="$2"
  echo "Installing Nix (multi-user) on $REMOTE_HOST..."
  if ! ssh "root@$REMOTE_HOST" "
    set -e
    curl -L https://nixos.org/nix/install | sh -s -- --daemon --yes
    mkdir -p /etc/nix
    echo 'trusted-users = root $PROD_USER' >> /etc/nix/nix.conf
    systemctl restart nix-daemon
  "; then
      echo "Nix installation failed."
      return 1
  fi
  echo "Nix installed successfully."
}

deploy_nix_packages() {
  # - Shipping binaries instead of bulding from server is convenient
  #   if server is hardware limited, as it needs build resources:
  #   compilers, -dev packages, 20GB of disk, etc.
  # - Ship Nix store folder structure (i.e. the symlinks to nix/store)
  # - Keep /usr/local/simox/result/ root owned. This because
  #   PROD_USER only needs to read/exec Nix binaries and if
  #   PROD_USER writes here, it could inject malicious executables.
  # - Keep the store gcroot at /nix/var/nix/gcroots/simox (root-owned).
  #   With one user per project, a project-named path under /home is
  #   redundant, so the root lives in the system gcroot dir instead.
  local REMOTE_HOST="$1"
  local PROD_USER="$2"
  local REMOTE_TARGET_DIR="$3"

  ssh "root@$REMOTE_HOST" "
    set -e
    mkdir -p '/usr/local/simox' '/nix/var/nix/gcroots'
    ln -sf /nix/var/nix/profiles/default/bin/nix-store /usr/local/bin/nix-store
  "

  if ! nix eval ".#packages.x86_64-linux.default" &>/dev/null; then
    echo "WARNING: No nix package for x86_64-linux in flake. Skipping nix package deployment."
    return 0
  fi

  local REMOTE_STORE_PATH
  echo "Building packages locally..."
  nix build
  echo "Copying nix closure to remote..."
  nix copy --to "ssh://$PROD_USER@$REMOTE_HOST" ./result || return 1
  REMOTE_STORE_PATH=$(readlink -f ./result)
  rm -f result

  echo "Registering nix store root on remote..."
  ssh "root@$REMOTE_HOST" "
    /nix/var/nix/profiles/default/bin/nix-store --add-root /nix/var/nix/gcroots/simox --realise $REMOTE_STORE_PATH
    ln -sfn '$REMOTE_STORE_PATH' '/usr/local/simox/result'
    # Legacy cleanup: the gcroot used to live in the prod user's home.
    rm -f '/home/$PROD_USER/.nix-gcroots/simox'
    rmdir '/home/$PROD_USER/.nix-gcroots' 2>/dev/null || true
  "
}

deploy_composer_dependencies() {
  local REMOTE_HOST="$1"
  local PROD_USER="$2"
  local REMOTE_TARGET_DIR="$3"
  local PREVIOUS_HASH_DEPLOYED="$4"
  local CURRENT_HASH_DEPLOYED="$5"
  local COMPOSER_JSON="composer.json"
  local COMPOSER_LOCK="composer.lock"
  local DEPLOY_VENDOR=$(git_target_changed $PREVIOUS_HASH_DEPLOYED $CURRENT_HASH_DEPLOYED $COMPOSER_JSON)

  if [ "$DEPLOY_VENDOR" = "true" ] || [ "$INIT" = "true" ]; then
      echo "File $COMPOSER_LOCK has changed. Running composer install and system level updates in remote host..."
      # TO DO: Add minimal test for modified vendor/
      ssh "$PROD_USER@$REMOTE_HOST" "
          export PATH='/usr/local/simox/result/bin':\$PATH
          cd \\\"$REMOTE_TARGET_DIR\\\" && composer install
      "
  else
      echo "File $COMPOSER_JSON has not changed between deployments. Skipping deployment of vendor/..."
  fi
}

git_target_changed() {
  local PREVIOUS_HASH_DEPLOYED="$1"
  local CURRENT_HASH_DEPLOYED="$2"
  local TARGET="$3"  # file (or directory) relative path

  if [ -n "$PREVIOUS_HASH_DEPLOYED" ] && git diff --quiet "$PREVIOUS_HASH_DEPLOYED" "$CURRENT_HASH_DEPLOYED" -- "$TARGET"; then
    echo "false"
  else
    echo "true"
  fi
}

deploy_website() {
  local REMOTE_HOST="$1"
  local REMOTE_TARGET_DIR="$2"

  # Apache serves directly from the deployed repo's public/ subdirectory.
  # The repo dir is recreated on each deploy, so www-data traversal must be restored each time.
  # One-time setup (manual): chmod o+x /srv /srv/apps (Debian/AppArmor needs no relabeling);
  # configure Apache vhost DocumentRoot to $REMOTE_TARGET_DIR/public, and set
  # SetEnv PHPRUN_REUTER_INI <path-to-reuter.ini> in the vhost.
  ssh "root@$REMOTE_HOST" "chmod o+x '$REMOTE_TARGET_DIR'"
}

INIT=false
ARGS=()

while [[ "$#" -gt 0 ]]; do
  case "$1" in
    --init) INIT=true ;;
    --) shift; ARGS+=("$@"); break ;; # Stop parsing flags
    -*) echo "Unknown option: $1"; exit 1 ;;
    *) ARGS+=("$1") ;;
  esac
  shift
done

main() {
  local INIT="$INIT"
  local REMOTE_HOST="${1:?ERROR: Missing REMOTE_HOST argument. Usage: $0 <remote_host>}"
  flight_checks
  local PROD_USER="${PROD_USER:?ERROR: PROD_USER environment variable is required}"
  local REMOTE_TARGET_DIR="/srv/apps/simox"
  local REV=$(git rev-parse HEAD)

  if ! OUTPUT=$(deploy_repo_remotely $REMOTE_HOST $PROD_USER $REMOTE_TARGET_DIR $REV); then
    echo "Failed to deploy repository."
    exit 1
  fi
  read -r PREVIOUS_REV NIX_EXISTS REMOTE_ARCH <<< "$OUTPUT"
  if [ "$REMOTE_ARCH" != "x86_64" ] || [ "$(uname -m)" != "x86_64" ]; then
    echo "ERROR: Both local ($(uname -m)) and remote ($REMOTE_ARCH) must be x86_64."
    exit 1
  fi
  [ "$NIX_EXISTS" != "true" ] && install_nix_remotely "$REMOTE_HOST" "$PROD_USER" || true
  deploy_nix_packages "$REMOTE_HOST" "$PROD_USER" "$REMOTE_TARGET_DIR"  # keep it before deploying composer
  deploy_composer_dependencies "$REMOTE_HOST" "$PROD_USER" "$REMOTE_TARGET_DIR" "$PREVIOUS_REV" "$REV"
  deploy_website "$REMOTE_HOST" "$REMOTE_TARGET_DIR"
  if [ "$INIT" = "true" ]; then
    # prod-init is for execute only once workflows in prod server
    make prod-init
  fi

  # Here, you can also clear any caches or perform other post-deployment tasks
  # Perhaps better to clear caches in src/scripts/maintenance cron jobs.
}
main "${ARGS[@]}"
exit 0
