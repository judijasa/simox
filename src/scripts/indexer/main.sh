#!/bin/bash

if [ "$IS_CRON_JOB" = "true" ]; then
  cd $SIMO_REPO_PATH
else
  if [[ "$PWD" != "$SIMO_REPO_PATH" ]]
  then
    echo "This command must be executed from the repository's root directory."
    exit
  fi
fi

if [[ ! -n $IN_NIX_SHELL ]]; then
  source /etc/environment  # SIMO_REPO_PATH, SIMO_LOG_PATH
fi

# fix phpcasperjs bug
export OPENSSL_CONF=dev/null

# Generate LOG_FILE
SCRIPT_NAME=$(basename "$0")
DIR_PREFIX=$(dirname "$0" | tr '/' '_')
LOG_FILE="${SIMO_LOG_PATH}/${DIR_PREFIX}_${SCRIPT_NAME}.log"

if [ ! -f "$LOG_FILE" ]; then
  touch "$LOG_FILE"
fi

if [ -t 1 ]; then
  # running interactively: show output on screen and append to log.
  # `exec >`: redirect all commands in this script to...
  # `>(...)`: ...to the pipe inside parenthesis
  exec > >(tee -a "$LOG_FILE") 
  exec 2>&1
else
  # Non-interactive (cron, background, etc.): log only.
  exec >> "$LOG_FILE" 2>&1
fi

run() {
    local code="$1"

    printf '%s - Starting crawler...\n' "$(date '+%F %T')"

    php -r "$code"
    local rc=$?

    printf '%s - Finished crawling.\n' "$(date '+%F %T')"

    return "$rc"
}

run "require \"src/scripts/indexer/get_jobs.php\"; main();"

exit 0
