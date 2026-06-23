#!/bin/bash

# Function to trim log file if it exceeds max size
trim_log_file() {
    local LOG_FILE="$1"
    local MAX_SIZE="$2"

    if [[ ! -f "$LOG_FILE" ]]; then
        echo "Error: Log file does not exist."
        return 1
    fi

    if [[ -z "$MAX_SIZE" || "$MAX_SIZE" -le 0 ]]; then
        echo "Error: Invalid maximum file size."
        return 1
    fi

    # Get file size in bytes
    local FILE_SIZE=$(stat -c%s "$LOG_FILE")

    if (( FILE_SIZE > MAX_SIZE )); then
        echo "$(date '+%Y-%m-%d %H:%M:%S') - Trimming $LOG_FILE..."
        local TMP_FILE=$(mktemp)
        # Keep the second half of the file
        tail -n $(($(wc -l < "$LOG_FILE") / 2)) "$LOG_FILE" > "$TMP_FILE"
        mv "$TMP_FILE" "$LOG_FILE"
        echo "$(date '+%Y-%m-%d %H:%M:%S') - Trimmed $LOG_FILE to half its original size."
    fi
}

if [[ ! -n $IN_NIX_SHELL ]]; then
  source /etc/environment  # SIMO_REPO_PATH, SIMO_LOG_PATH
fi
LOG_FILE="${SIMO_LOG_PATH}/maintenance.log"
MAX_SIZE=500000  # .5MB
trim_log_file "$LOG_FILE" "$MAX_SIZE"

LOG_FILE="${SIMO_LOG_PATH}/indexer.log"
MAX_SIZE=1000000  # 1MB
trim_log_file "$LOG_FILE" "$MAX_SIZE"

exit 0
