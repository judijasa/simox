#!/bin/bash

set -euo pipefail

deploy_repo_remotely() {
  REMOTE_BASE_DIR="/home/${PROD_USER}/apps/"
  REMOTE_TARGET="${REMOTE_BASE_DIR}/simox"
  local REMOTE_HOST="$1"


  # Fetch the latest remote state without merging
  git fetch origin main

  LOCAL_REPO_STATE=$(git rev-parse main)
  REMOTE_REPO_STATE=$(git rev-parse origin/main)

  if [ "$LOCAL_REPO_STATE" != "$REMOTE_REPO_STATE" ]; then
    echo "ERROR: local main is not up to date with origin/main"
    echo "Local:  $LOCAL_REPO_STATE"
    echo "Remote: $REMOTE_REPO_STATE"
    exit 1
  fi

  # REV will always be HEAD
  # To revert prod server changes: revert commits and re-deploy
  REV=$(git rev-parse HEAD)
  echo "Deploying commit: $REV"

  # Deploy (atomic on remote)
  return $(git archive "$REV" | ssh "$REMOTE_HOST-as-root" "
      set -e     
      
      useradd -m -s /bin/bash $PROD_USER 2>/dev/null || echo "User $PROD_USER already exists. Proceeding..." >&2

      TMP_DIR=\$(mktemp -d)
      FINAL_DIR='$REMOTE_TARGET'
      BACKUP_DIR='${REMOTE_TARGET}_backup'
      LOG_DIR='/home/$PROD_USER/var/simox/log'

      echo 'Unpacking to temp...' >&2
      tar -x -C \"\$TMP_DIR\"

      if [ -d \"\$FINAL_DIR\" ]; then
          echo 'Creating backup...' >&2
          rm -rf \"\$BACKUP_DIR\"
          mv \"\$FINAL_DIR\" \"\$BACKUP_DIR\"
      fi

      echo 'Overriding repo codebase...' >&2
      mv \"\$TMP_DIR\" \"\$FINAL_DIR\"
      mkdir -p \"\$LOG_DIR\"
      chown -R $PROD_USER:$PROD_USER \"\$FINAL_DIR\" \"/home/$PROD_USER/var\"

      LOG_FILE=\"\$LOG_DIR/deploy_version.log\"
      touch \"\$LOG_FILE\"
      chown $PROD_USER:$PROD_USER \"\$LOG_FILE\"
      
      if [ -f \"\$LOG_FILE\" ]; then
          tail -n 1 \"\$LOG_FILE\" | awk '{print \$NF}' || echo 'None'
      fi

      echo \"\$(date +'%Y-%m-%d %H:%M:%S %Z'): $REV\" >> \"\$LOG_FILE\"
      echo \"Deploy complete: $REV\" > \"\$FINAL_DIR/.deploy_version\"
  ")
}

REMOTE_HOST="$1"  # Use ~/.ssh to config connections

# Checks...
if [[ ! -n $IN_NIX_SHELL ]]; then
		echo "ERROR: This script must be run inside 'nix develop'"
		exit 1
fi

if [[ "$PWD" != "$SIMO_REPO_PATH" ]]
then
  echo "This command must be executed from the repository's root directory."
  exit 1
fi

if ping -c 1 -W 2 "${REMOTE_HOST}-as-root" &> /dev/null; then
  echo "Host ${REMOTE_HOST}-as-root is online."
else
  echo "Host ${REMOTE_HOST}-as-root is unreachable."
  exit 1
fi

if [ "$(git branch --show-current)" != "main" ]; then
  echo "ERROR: not on main branch"
  exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "ERROR: working tree is not clean"
  exit 1
fi

PREVIOUS_HASH=$(deploy_repo_remotely "$REMOTE_HOST")

echo "Building packages locally and pushing the pre-compiled closures to the server..."
nix build
nix copy --to ssh://$REMOTE_HOST ./result

# Ship Nix store folder structure (i.e. the symlinks to nix/store)
# Must be kept consistent with NIX_BIN value at etc/cron.d/orchestrator
REMOTE_STORE_PATH=$(readlink -f ./result)
ssh $PROD_USER@$REMOTE_HOST "ln -sfn $REMOTE_STORE_PATH /usr/local/simox/result"

COMPOSER_LOCK="composer.lock"
DEPLOY_VENDOR=true
if [ -n "$PREVIOUS_HASH" ] && git diff --quiet "$PREVIOUS_HASH" "$REV" -- "$COMPOSER_JSON"; then
    DEPLOY_VENDOR=false
fi

if [ "$DEPLOY_VENDOR" = true ]; then
    echo "File $COMPOSER_LOCK has changed. Running composer install and system level updates in remote host..."
    # TO DO: Add minimal test for modified vendor/
    ssh "$REMOTE_HOST-as-root" "
        TARGET_FILE='vendor/phpcasperjs/phpcasperjs/src/Casper.php'
        cd '$REMOTE_TARGET'
        composer install && \\
        sed -i 's/private \$script = \x27\x27;/protected \$script = \x27\x27;/g' \"\$TARGET_FILE\"
        make prod-init
    "
else
    echo "File $COMPOSER_JSON has not changed between deployments. Skipping deployment of vendor/..."
    echo "Running system level updates in remote host..."
    ssh "$REMOTE_HOST-as-root" "cd '$REMOTE_TARGET' && make prod-init"
fi

# Here, you can also clear any caches or perform other post-deployment tasks
# Perhaps better to clear caches in src/scripts/maitenance cron jobs.

exit 0
