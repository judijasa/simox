#!/bin/bash

# This script has two purposes:
# 1. Continuous development deployment and
# 2. Initial deployment ("only-once ops")
# Usage:
#   deploy <target_host>
# For initial deployment:
#   deploy --init <target_host>

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
      if su - \"$PROD_USER\" -c 'command -v nix' &>/dev/null; then
          NIX_INSTALLED='true'
      else
          NIX_INSTALLED='false'
      fi

      # Output previous hash and NIX_INSTALLED to stdout (separated by a space)
      if [ -s \"\$LOG_FILE\" ]; then
          echo \"\$(tail -n 1 \"\$LOG_FILE\" | awk '{print \$NF}') \$NIX_INSTALLED\"
      else
          echo \"None \$NIX_INSTALLED\"
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
  # - Shipping binaries instead of bulding from server is convenient
  #   if server is hardware limited, as it needs build resources:
  #   compilers, -dev packages, 20GB of disk, etc.
  # - Ship Nix store folder structure (i.e. the symlinks to nix/store)
  # - Keep it consistent with NIX_BIN value at etc/cron.d/orchestrator.
  # - Keep /usr/local/simox/result/ root owned. This because
  #   PROD_USER only needs to read/exec Nix binaries and if
  #   PROD_USER writes here, it could inject malicious executables.
  local REMOTE_HOST="$1"
  local PROD_USER="$2"

  echo "Building packages locally and pushing the pre-compiled closures to the server..."
  nix build
  nix copy --to "ssh://$PROD_USER@$REMOTE_HOST" ./result || return 1

  local REMOTE_STORE_PATH
  REMOTE_STORE_PATH=$(readlink -f ./result)
  ssh "root@$REMOTE_HOST" "
    mkdir -p '/usr/local/simox'
    nix-store --add-root /usr/local/simox/result --realise "$REMOTE_STORE_PATH"
  "
  rm -f result
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

  if [ "$DEPLOY_VENDOR" = "true" || "$INIT" = "true" ]; then
      echo "File $COMPOSER_LOCK has changed. Running composer install and system level updates in remote host..."
      # TO DO: Add minimal test for modified vendor/
      ssh "root@$REMOTE_HOST" "
          TARGET_FILE='vendor/phpcasperjs/phpcasperjs/src/Casper.php'
          cd \"$REMOTE_TARGET_DIR\"
          composer install && \\
          sed -i 's/private \$script = \x27\x27;/protected \$script = \x27\x27;/g' \"\$TARGET_FILE\"
          chown -R $PROD_USER:$PROD_USER \"$REMOTE_TARGET_DIR/vendor\"
      "
  else
      echo "File $COMPOSER_JSON has not changed between deployments. Skipping deployment of vendor/..."
  fi
}

git_target_changed() {
  local PREVIOUS_HASH_DEPLOYED="$1"
  local CURRENT_HASH_DEPLOYED="$2"
  local TARGET="$3"  # file (or directory) path

  if [ -n "$PREVIOUS_HASH_DEPLOYED" ] && git diff --quiet "$PREVIOUS_HASH_DEPLOYED" "$CURRENT_HASH_DEPLOYED" -- "$TARGET"; then
    echo "false"
  else
    echo "true"
  fi
}

deploy_website() {
  local REMOTE_HOST="$1"
  local PROD_USER="$2"
  local REMOTE_TARGET_DIR="$3"
  local PREVIOUS_HASH_DEPLOYED="$4"
  local CURRENT_HASH_DEPLOYED="$5"
  local PUBLIC_DIR="${REMOTE_TARGET_DIR}/public"
  local DEPLOY_WEB=$(git_target_changed $PREVIOUS_HASH_DEPLOYED $CURRENT_HASH_DEPLOYED $PUBLIC_DIR)

  if [ "$DEPLOY_WEB" = "true" || "$INIT" = "true" ]; then
    ssh "root@$REMOTE_HOST" "
      SOURCE_DIR=\"$PUBLIC_DIR\"
      DEST_DIR='/var/www/html/simox'
      echo 'Checking for website changes and deploying...'
      if [ ! -d \"\$SOURCE_DIR\" ]; then
          echo \"Error: Source directory \$SOURCE_DIR not found.\"
          exit 1
      fi
      mkdir -p \"\$DEST_DIR\"
      echo 'Checking for changes and deploying...'
      RSYNC_OUT=\$(rsync -av --delete --chown=$PROD_USER:$PROD_USER --out-format=\"%i %n\" \"\$SOURCE_DIR\" \"\$DEST_DIR\")
      if echo \"\$RSYNC_OUT\" | grep -E '[><+*cstmd]' > /dev/null; then
          echo 'Changes detected and applied. Restarting web server...'
          systemctl restart apache2
      else
          echo 'Websites are up to date. Skipping server restart.'
      fi
    "
  fi
}

deploy_cron_jobs() {
  local REMOTE_HOST="$1"
  local PROD_USER="$2"
  local SOURCE_BASE_DIR="$3"

  # Keep /etc/simo-cron.* root-owned. They will be used by Cron deamon, which runs as root.
  ssh "root@$REMOTE_HOST" "
    REPO_ORCHEST=\"$SOURCE_BASE_DIR/etc/cron.d/orchestrator\"
    SYS_ORCHEST='/etc/cron.d/simo-orchestrator'
    CHANGES_DETECTED=0

    echo 'Syncing cron configurations...'

    mkdir -p '/etc/simo-cron.5min' '/etc/simo-cron.monthly' '/etc/simo-cron.hourly'
    declare -A CRON_MAP=( \
      ['src/scripts/maintenance/memory_cleaning.sh']='/etc/simo-cron.5min' \
      ['src/scripts/maintenance/trim_log_files.sh']='/etc/simo-cron.monthly' \
      ['src/scripts/indexer/main.sh']='/etc/simo-cron.hourly')
    for SRC_REL in \"\${!CRON_MAP[@]}\"; do
      if [ ! -f \"$SOURCE_BASE_DIR/\$SRC_REL\" ]; then
          echo \"Error: Source file \$SRC_REL missing on remote!\"
          exit 1
      fi
    done
    for SRC_REL in \"\${!CRON_MAP[@]}\"; do
      SRC=\"$SOURCE_BASE_DIR/\$SRC_REL\"
      DIR=\"\${CRON_MAP[\$SRC_REL]}\"
      BASE=\$(basename \"\$SRC\")
      TARGET=\"\$DIR/\$BASE\"
      if [ ! -f \"\$TARGET\" ] || ! cmp -s \"\$SRC\" \"\$TARGET\"; then
          CHANGES_DETECTED=1
      fi
    done
    if [ ! -f \"\$SYS_ORCHEST\" ] || ! cmp -s \"\$REPO_ORCHEST\" \"\$SYS_ORCHEST\"; then
        CHANGES_DETECTED=1
    fi
    if [ \$CHANGES_DETECTED -eq 1 ]; then
        echo 'Changes detected in cron specifications. Deploying updates...'
        for SRC_REL in \"\${!CRON_MAP[@]}\"; do
          SRC=\"$SOURCE_BASE_DIR/\$SRC_REL\"
          DIR=\"\${CRON_MAP[\$SRC_REL]}\"
          BASE=\$(basename \"\$SRC\")
          TARGET=\"\$DIR/simo_\${BASE%.sh}\"
          install -m 755 -o \"$PROD_USER\" -g \"$PROD_USER\" \"\$SRC\" \"\$TARGET\"
        done
        install -m 644 -o root -g root \"\$REPO_ORCHEST\" \"\$SYS_ORCHEST\"
        echo 'Restarting cron daemon...'
        systemctl restart cron  || systemctl restart crond
    else
        echo 'Cron systems are up to date. Skipping deployment and restart.'
    fi
  "
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
  local REMOTE_HOST="${2:?ERROR: Missing REMOTE_HOST argument. Usage: $0 <remote_host>}"
  flight_checks
  local PROD_USER="${PROD_USER:?ERROR: PROD_USER environment variable is required}"
  local REMOTE_TARGET_DIR="/home/${PROD_USER}/apps/simox"
  local REV=$(git rev-parse HEAD)

  if ! OUTPUT=$(deploy_repo_remotely $REMOTE_HOST $PROD_USER $REMOTE_TARGET_DIR $REV); then
    echo "Failed to deploy repository."
    exit 1
  fi
  read -r PREVIOUS_REV NIX_EXISTS <<< "$OUTPUT"
  [ "$NIX_EXISTS" != "true" ] && install_nix_remotely "$REMOTE_HOST" "$PROD_USER" || exit 1
  deploy_nix_packages "$REMOTE_HOST" "$PROD_USER"  # keep it before deploying composer
  deploy_composer_dependencies "$REMOTE_HOST" "$PROD_USER" "$REMOTE_TARGET_DIR" "$PREVIOUS_REV" "$REV"
  deploy_website "$REMOTE_HOST" "$PROD_USER" "$REMOTE_TARGET_DIR" "$PREVIOUS_REV" "$REV"
  deploy_cron_jobs "$REMOTE_HOST" "$PROD_USER" "$REMOTE_TARGET_DIR"
  if [ "$INIT" = "true" ]; then
    # prod-init is for execute only once workflows in prod server
    make prod-init
  fi

  # Here, you can also clear any caches or perform other post-deployment tasks
  # Perhaps better to clear caches in src/scripts/maintenance cron jobs.
}

main "${ARGS[@]}"
exit 0
