#!/bin/bash

set -euo pipefail

if [[ ! -n $IN_NIX_SHELL ]]; then
  source /etc/environment  # SIMO_REPO_PATH, SIMO_LOG_PATH
fi

if [[ "$PWD" != "$SIMO_REPO_PATH" ]]
then
  echo "This command must be executed from the repository's root directory."
  exit
fi

deploy_repo_remotely() {
  # WARNING: This function is to be executed in a dev machine.

  REMOTE_USER="$PROD_USER"
  REMOTE_HOST="server"
  REMOTE_TARGET="/home/${REMOTE_USER}/apps/simox"

  # Preflight checks (local)

  if [ "$(git branch --show-current)" != "main" ]; then
    echo "ERROR: not on main branch"
    exit 1
  fi

  if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "ERROR: working tree is not clean"
    exit 1
  fi

  # Ensure local main is up to date with remote
  git fetch origin main

  LOCAL=$(git rev-parse main)
  REMOTE=$(git rev-parse origin/main)

  if [ "$LOCAL" != "$REMOTE" ]; then
    echo "ERROR: local main is not up to date with origin/main"
    echo "Local:  $LOCAL"
    echo "Remote: $REMOTE"
    exit 1
  fi

  REV=$(git rev-parse HEAD)
  echo "Deploying commit: $REV"

  # Deploy (atomic on remote)

  git archive "$REV" | ssh "$REMOTE_USER@$REMOTE_HOST" "
    set -e
    TMP_DIR=\$(mktemp -d)
    FINAL_DIR='$REMOTE_TARGET'
    BACKUP_DIR=\${FINAL_DIR}_backup_\$(date +%s)

    echo 'Unpacking to temp...'
    tar -x -C \"\$TMP_DIR\"

    mkdir -p /home/\"\$REMOTE_USER\"/apps

    if [ -d \"\$FINAL_DIR\" ]; then
      echo 'Creating backup...'
      mv \"\$FINAL_DIR\" \"\$BACKUP_DIR\"
    fi

    echo 'Activating new version...'
    mv \"\$TMP_DIR\" \"\$FINAL_DIR\"

    echo 'Deploy complete: $REV' > \"\$FINAL_DIR/.deploy_version\"
  "
}

echo "Updating server..."
deploy_repo_remotely
echo "Running production deployment via make prod-init"
ssh user@remote-server "cd \"\$REMOTE_TARGET\" && make prod-init"

# Optionally, you can also clear any caches or perform other post-deployment tasks

exit 0
