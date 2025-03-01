#!/bin/bash

# This is meant to be executed every 5min
# Kill memory intensive processes if exceed threshold (> 90%)
# If above threshold but no process found, reboot.

logFile="$SIMO_REPO_PATH/log/maintenance.log"

THRESHOLD=90
USED_RAM=$(free | awk '/Mem:/ {printf "%.0f", $3/$2 * 100}')

if [ ! -f "$logFile" ]; then
  touch "$logFile"
fi

if [ "$USED_RAM" -ge "$THRESHOLD" ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - RAM usage at ${USED_RAM}%, exceeding threshold. Finding and killing top memory consumer..." | tee -a $logFile
    TOP_PID=$(ps -eo pid,%mem,cmd --sort=-%mem | awk 'NR==2 {print $1}')
    if [ -n "$TOP_PID" ]; then
        echo "Killing process $TOP_PID"
        kill -9 "$TOP_PID"
    else
        echo "$(date '+%Y-%m-%d %H:%M:%S') - No process found, rebooting as last resort..." | tee -a $logFile
        sudo reboot
    fi
fi
