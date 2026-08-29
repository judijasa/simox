#!/usr/bin/env sh

set -eu

# Non-mutating advisory check (runs on shell entry): warn if nix (flake.lock)
# and Composer (composer.lock) disagree on the php_daas_framework pin.
# The hard commit-time gate is hooks/pf/pre-commit-lock-sync.sh.

[ -f flake.lock ] || exit 0
[ -f composer.lock ] || exit 0

flake_rev=$(jq -r '.nodes.php_daas_framework.locked.rev // empty' flake.lock)
composer_ref=$(jq -r '.packages[]? | select(.name == "judijasa/php-daas-framework") | .source.reference // empty' composer.lock)

if [ -n "$flake_rev" ] && [ -n "$composer_ref" ] && [ "$flake_rev" != "$composer_ref" ]; then
    echo "WARNING: php-daas-framework pin drift (flake vs composer):" >&2
    echo "  flake.lock:     $flake_rev" >&2
    echo "  composer.lock:  $composer_ref" >&2
    echo "  Fix: composer require \"judijasa/php-daas-framework:dev-main#$flake_rev\"" >&2
fi
