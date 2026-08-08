#!/usr/bin/env bash
# Merge the repository's etc/hosts entries into the system /etc/hosts,
# replacing the previous simox-managed block (delimited by the given tags).
# Backend for the Makefile target _dev-update-hosts (make dev-init).
# Usage: update-hosts.sh <tag-begin> <tag-end>

set -euo pipefail

TAG_BEGIN="$1"
TAG_END="$2"

echo "Syncing repository hosts to /etc/hosts..."
payload=$(grep -v '^[[:space:]]*#' etc/hosts | grep -v '^[[:space:]]*$') || true
if [ -z "$payload" ]; then
    echo "No valid hosts found in etc_hosts_local."
    exit 0
fi
tmp_hosts=$(mktemp)

sed "/$TAG_BEGIN/,/$TAG_END/d" /etc/hosts > "$tmp_hosts"
echo "$TAG_BEGIN" >> "$tmp_hosts"
echo "$payload" >> "$tmp_hosts"
echo "$TAG_END" >> "$tmp_hosts"
echo "Applying changes (atomically) to /etc/hosts (requires sudo)..."
sudo cp "$tmp_hosts" /etc/hosts
rm -f "$tmp_hosts"
echo "Successfully synced!"
