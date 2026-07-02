#!/bin/bash

set -euo pipefail

flight_checks() {
  if [[ ! -n $IN_NIX_SHELL ]]; then
      echo "ERROR: This script must be run inside 'nix develop'"
      exit 1
  fi
  
  if [[ "$PWD" != "$SIMO_REPO_PATH" ]]
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

  if ping -c 1 -W 2 "${REMOTE_HOST}-as-root" &> /dev/null; then
    echo "Host ${REMOTE_HOST}-as-root is online."
  else
    echo "Host ${REMOTE_HOST}-as-root is unreachable."
    exit 1
  fi
}

deploy_repo_remotely() {
  local REMOTE_HOST="$1"
  local PROD_USER="$2"
  local REMOTE_TARGET_DIR="$3"

  REV=$(git rev-parse HEAD)
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
      VAR_DIR='/home/$PROD_USER/var'
      LOG_DIR=\"\$VAR_DIR/simox/log\"

      # Unpack inside the same parent base directory to ensure fast rename across the same mount point
      TMP_DIR=\$(mktemp -d -p \"\$BASE_DIR\")
      echo 'Unpacking to temp...' >&2
      tar -x -C \"\$TMP_DIR\"
      
      # Ensure permissions are set before pushing live
      mkdir -p \"\$LOG_DIR\"
      chown -R $PROD_USER:$PROD_USER \"\$TMP_DIR\" \"\$VAR_DIR\"

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

      # Piggyback: Check if nix is in the user's path or standard profile
      if su - "$PROD_USER" -c 'command -v nix' &>/dev/null; then
          NIX_INSTALLED="true"
      else
          NIX_INSTALLED="false"
      fi

      # Output previous hash and NIX_INSTALLED to stdout (separated by a space)
      if [ -s "\$LOG_FILE" ]; then
          echo "\$(tail -n 1 \"\$LOG_FILE\" | awk '{print \$NF}') \$NIX_INSTALLED"
      else
          echo "None \$NIX_INSTALLED"
      fi
  "
}

install_nix_remotely() {
  local REMOTE_HOST="$1"
  local PROD_USER="$2"
  echo "Installing Nix directly into the $PROD_USER account on $REMOTE_HOST..."
  if ! ssh "$PROD_USER@$REMOTE_HOST" "curl -L https://nixos.org/nix/install | sh -s -- --no-daemon"; then
      echo "Nix installation failed."
      return 1
  fi
  echo "Nix installed successfully."
}

deploy_nix_packages() {
  local REMOTE_HOST="$1"
  local PROD_USER="$2"

  echo "Building packages locally and pushing the pre-compiled closures to the server..."
  nix build
  nix copy --to ssh://$PROD_USER@$REMOTE_HOST ./result

  # Ship Nix store folder structure (i.e. the symlinks to nix/store)
  # Must be kept consistent with NIX_BIN value at etc/cron.d/orchestrator
  REMOTE_STORE_PATH=$(readlink -f ./result)
  ssh "root@$REMOTE_HOST" "
    NIX_BIN='/usr/local/simox/result/bin'
    mkdir -p \"\$NIX_BIN\"
    ln -sfn '$REMOTE_STORE_PATH' \"\$NIX_BIN\"
  "
}

deploy_composer_dependencies() {
  local PREVIOUS_HASH_DEPLOYED="$1"
  local CURRENT_HASH_DEPLOYED="$2"
  local COMPOSER_JSON="composer.json"
  local COMPOSER_LOCK="composer.lock"
  local DEPLOY_VENDOR=true
  if [ -n "$PREVIOUS_HASH_DEPLOYED" ] && git diff --quiet "$PREVIOUS_HASH_DEPLOYED" "$CURRENT_HASH_DEPLOYED" -- "$COMPOSER_JSON"; then
      DEPLOY_VENDOR=false
  fi

  if [ "$DEPLOY_VENDOR" = true ]; then
      echo "File $COMPOSER_LOCK has changed. Running composer install and system level updates in remote host..."
      # TO DO: Add minimal test for modified vendor/
      ssh "root@$REMOTE_HOST" "
          TARGET_FILE='vendor/phpcasperjs/phpcasperjs/src/Casper.php'
          cd '$REMOTE_TARGET_DIR'
          composer install && \\
          sed -i 's/private \$script = \x27\x27;/protected \$script = \x27\x27;/g' \"\$TARGET_FILE\"
      "
  else
      echo "File $COMPOSER_JSON has not changed between deployments. Skipping deployment of vendor/..."
      echo "Running system level updates in remote host..."
      ssh "root@$REMOTE_HOST" "cd '$REMOTE_TARGET_DIR'"
  fi
}

REMOTE_HOST="${1:?ERROR: Missing REMOTE_HOST argument. Usage: $0 <remote_host>}"
flight_checks
PROD_USER="${PROD_USER:?ERROR: PROD_USER environment variable is required}"
REMOTE_TARGET_DIR="/home/${PROD_USER}/apps/simox"
if ! OUTPUT=$(deploy_repo_remotely $REMOTE_HOST $PROD_USER $REMOTE_TARGET_DIR); then
    echo "Failed to deploy repository."
    exit 1
fi
read -r PREVIOUS_REV NIX_EXISTS <<< "$OUTPUT"
$NIX_EXISTS || install_nix_remotely $REMOTE_HOST $PROD_USER
deploy_nix_packages $REMOTE_HOST $PROD_USER  # keep it before deploying composer
deploy_composer_dependencies "$PREVIOUS_REV" "$REV"
make prod-init

# Here, you can also clear any caches or perform other post-deployment tasks
# Perhaps better to clear caches in src/scripts/maitenance cron jobs.

exit 0
