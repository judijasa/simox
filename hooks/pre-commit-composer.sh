#!/usr/bin/env sh
set -euo pipefail

# Only act when composer.json is staged.
git diff --cached --name-only | grep -qx 'composer.json' || exit 0

# No --strict: simox's composer.json intentionally pins judijasa/* by
# commit-ref (dev-main#<sha>), which composer flags as a permanent warning. A
# stale lock is an ERROR, so it fails the gate regardless; --strict would
# escalate that warning into a false block.
if composer validate --no-check-all --no-check-publish --no-check-version \
        --check-lock; then
    exit 0
fi

cat >&2 <<'EOF2'
composer.lock is out of date with composer.json.

Run:  composer update --minimal-changes   # re-lock, no bumps; adds/removes as needed
Then: git add composer.lock
EOF2
exit 1
