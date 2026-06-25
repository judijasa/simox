#!/bin/bash

set -euo pipefail

if [[ ! -n $IN_NIX_SHELL ]]; then
		echo "ERROR: This script must be run inside 'nix develop'"
		exit 1
fi

if [[ "$PWD" != "$SIMO_REPO_PATH" ]]
then
  echo "This command must be executed from the repository's root directory."
  exit 1
fi


deploy_repo_remotely() {
  REMOTE_BASE_DIR="/home/${PROD_USER}/apps/"
  REMOTE_TARGET="${REMOTE_BASE_DIR}/simox"
  REMOTE_HOST="$1"  # Use $HOME/.ssh to config connections

  # Checks...
  if ping -c 1 -W 2 "$REMOTE_HOST" &> /dev/null; then
      echo "Host is online."
  else
      echo "Host is unreachable."
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

  git archive "$REV" | ssh "$PROD_USER@$REMOTE_HOST" "
    set -e
    TMP_DIR=\$(mktemp -d)
    FINAL_DIR='$REMOTE_TARGET'
    LOG_DIR=\"\$HOME/var/simox/log\"

    echo 'Unpacking to temp...'
    tar -x -C \"\$TMP_DIR\"

    mkdir -p \"\$REMOTE_BASE_DIR\"

    echo 'Activating new version...'
    mv \"\$TMP_DIR\" \"\$FINAL_DIR\"

    mkdir -p \"\$LOG_DIR\" && touch \"\$LOG_DIR/deploy_version.log\"
    echo '\$(date +"%Y-%m-%d %H:%M:%S %Z"): $REV' >> \"\$LOG_DIR/deploy_version.log\"
  "
}

deploy_repo_remotely

echo "Building packages locally and pushing the pre-compiled closures to the server..."
nix build
nix copy --to ssh://$PROD_USER@$REMOTE_HOST ./result

# Ship Nix store folder structure (i.e. the symlinks to nix/store)
# Must be kept consistent with NIX_BIN value at etc/cron.d/orchestrator
REMOTE_STORE_PATH=$(readlink -f ./result)
ssh $PROD_USER@$REMOTE_HOST "ln -sfn $REMOTE_STORE_PATH /usr/local/simox/result"

echo "Running system level updates..."
ssh "$PROD_USER"@"$REMOTE_HOST" "cd \"\$REMOTE_TARGET\" && make prod-init"

# Here, you can also clear any caches or perform other post-deployment tasks
# Perhaps better to clear caches in src/scripts/maitenance cron jobs.

exit 0
