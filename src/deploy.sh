#!/bin/bash

# If exec not using CRON, check if you're in repo's root dir
root_dir=$(git rev-parse --show-toplevel) # repo root directory path
if [[ "$PWD" != $root_dir ]]
then
  echo "This command must be executed from the repository's root directory."
  exit
fi

# Define variables
SOURCE_DIR=$root_dir
DEST_DIR="/var/www/html/simo-express"
FILES_TO_DEPLOY=()

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

# Optionally, you can also clear any caches or perform other post-deployment tasks
