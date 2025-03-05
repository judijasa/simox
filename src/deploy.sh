#!/bin/bash

# If exec not using CRON, check if you're in repo's root dir
# Using SIMO_REPO_PATH instead of root_dir because git rev-parse only works within repo
# root_dir=$(git rev-parse --show-toplevel) # repo root directory path

source /etc/environment  # SIMO_REPO_PATH

if [[ "$PWD" != "$SIMO_REPO_PATH" ]]
then
  echo "This command must be executed from the repository's root directory."
  exit
fi

deploy_website() {
  # Define variables
  local SOURCE_DIR=$SIMO_REPO_PATH
  local DEST_DIR="/var/www/html/simo-express"
  local FILES_TO_DEPLOY=()

  # Ensure source directory exists
  if [ -d "$SOURCE_DIR/public" ]; then
      # Populate FILES_TO_DEPLOY with all files in the public directory
      mapfile -t FILES_TO_DEPLOY < <(find "$SOURCE_DIR/public" -type f)
  else
      echo "Error: Source directory 'public' not found."
      exit 1
  fi

  # Ensure destination directory exists
  mkdir -p "$DEST_DIR"

  # Copy files to destination directory
  for file in "${FILES_TO_DEPLOY[@]}"; do
      cp "$file" "$DEST_DIR"
  done

  # Optionally, you can also restart your web server to apply changes
  # For Apache:
  systemctl restart apache2

  # For Nginx:
  # systemctl restart nginx
}

deploy_cronjobs() {
  local MAINTENANCE="src/scripts/maintenance"
  local INDEXER="src/scripts/simo_indexer"

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

deploy_website
deploy_cronjobs

# Optionally, you can also clear any caches or perform other post-deployment tasks

exit 0
