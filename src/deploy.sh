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
  REMOTE_TARGET="/home/deploy/git/simox"   # <-- set this on server

  # 1. Preflight checks (local)

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

  # 2. Deploy (atomic on remote)

  git archive "$REV" | ssh "$REMOTE_USER@$REMOTE_HOST" "
    set -e

    TMP_DIR=\$(mktemp -d)
    FINAL_DIR='$REMOTE_TARGET'
    BACKUP_DIR=\${FINAL_DIR}_backup_\$(date +%s)

    echo 'Unpacking to temp...'
    tar -x -C \"\$TMP_DIR\"

    if [ -d \"\$FINAL_DIR\" ]; then
      echo 'Creating backup...'
      mv \"\$FINAL_DIR\" \"\$BACKUP_DIR\"
    fi

    echo 'Activating new version...'
    mv \"\$TMP_DIR\" \"\$FINAL_DIR\"

    echo 'Deploy complete: $REV' > \"\$FINAL_DIR/.deploy_version\"
  "
}

deploy_website() {
  local SOURCE_DIR="./public/"
  local DEST_DIR="/var/www/html/simox"

  if [ ! -d "$SOURCE_DIR" ]; then
    echo "Error: Source directory '$SOURCE_DIR' not found." >&2
    return 1 # Use return instead of exit inside a function to avoid killing the whole script
  fi

  if [ ! -d "$DEST_DIR" ]; then
    sudo mkdir -p "$DEST_DIR"
    sudo chown -R "$PROD_USER":"$PROD_USER" "$DEST_DIR"
  fi

  echo "Checking for changes and deploying..."

  # Run rsync itemized changes mode
  # -a: archive mode (preserves symlinks, modification times, permissions, etc.)
  # -v: verbose (needed to parse if changes occurred)
  # --delete: drops files in DEST_DIR that no longer exist in SOURCE_DIR (optional, but recommended for clean deployments)
  # --out-format="%i %n": prints exactly what changed per file
  local RSYNC_OUT
  RSYNC_OUT=$(rsync -av --delete --out-format="%i %n" "$SOURCE_DIR" "$DEST_DIR")

  # Check if rsync actually transferred or modified anything
  # If the output contains itemized change flags, a deployment happened.
  # (An empty or purely structural output means zero file changes).
  if echo "$RSYNC_OUT" | grep -E '^([><+\*cstmd]).*' > /dev/null; then
    echo "Changes detected and applied."
    # echo "$RSYNC_OUT" | grep -E '^([><+\*cstmd]).*' # Optional: print exactly what changed
    
    # Only restart if changes exist
    echo "Restarting web server..."
    sudo systemctl restart apache2
  else
    echo "Destination is up to date. Skipping server restart."
  fi
}

deploy_cronjobs() {
  local MAINTENANCE="src/scripts/maintenance"
  local INDEXER="src/scripts/indexer"

  cron_cp() {
    local SOURCE="$1"
    local TARGET="$2"
    cp "$SOURCE" "$TARGET"
    chmod +x "$TARGET"
  }
  # run-parts (cron way to exec all files in a directory) adhere to a specific naming convention.
  # Avoid files with extensions; use myfile instead of myfile.sh. It also must be executable,
  # hence chmod + x myfile
  # You need to create /etc/cron.d/cron-5min and add the line
  # */5 * * * * root run-parts /etc/cron.5min
  mkdir -p /etc/cron.5min
  cron_cp "${MAINTENANCE}/memory_cleaning.sh" "/etc/cron.5min/simo_memory_cleaning"
  cron_cp "${MAINTENANCE}/trim_log_files.sh" "/etc/cron.monthly/simo_trim_log_files"
  cron_cp "${INDEXER}/main.sh" "/etc/cron.hourly/simo_main"

  sudo systemctl restart cron
}

# deploy_repo_remotely # conditioned this. make sure not executed in Makefile.
deploy_website
#deploy_cronjobs

# Optionally, you can also clear any caches or perform other post-deployment tasks

exit 0
