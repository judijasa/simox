#!/usr/bin/env sh

set -euo pipefail

# Assert the nix (flake.lock) and Composer (composer.lock) resolvers agree on
# the pinned commit for every shared package, whenever either lockfile is
# staged. Regenerating the counterpart lock alone does not guarantee agreement
# (a `dev-main` Composer constraint resolves to current main HEAD, independent
# of the flake rev), so rev equality is the authoritative gate.
#
# Packages absent from composer.lock (e.g. ema before php_daas_framework starts
# requiring it) are skipped.

# Bail unless one of the lockfiles is staged.
staged=$(git diff --cached --name-only)
if ! printf '%s\n' "$staged" | grep -qx 'flake.lock' \
    && ! printf '%s\n' "$staged" | grep -qx 'composer.lock'; then
    exit 0
fi

flake_rev() {
    # $1: flake.lock node name
    jq -r --arg node "$1" '.nodes[$node].locked.rev // empty' flake.lock
}

composer_ref() {
    # $1: Composer package name
    jq -r --arg name "$1" \
        '.packages[]? | select(.name == $name) | .source.reference // empty' \
        composer.lock
}

fail=0

check() {
    node="$1"
    package="$2"
    flake=$(flake_rev "$node")
    ref=$(composer_ref "$package")

    # Dormant row: package not yet in composer.lock — skip silently.
    if [ -z "$ref" ]; then
        return
    fi

    if [ "$flake" != "$ref" ]; then
        echo "lock-sync: mismatch for $package"
        echo "  flake.lock ($node):        $flake"
        echo "  composer.lock ($package): $ref"
        echo "  Fix: pin Composer to the flake rev and regenerate:"
        echo "    composer require \"$package:dev-main#$flake\""
        fail=1
    fi
}

check php_daas_framework judijasa/php-daas-framework
check ema judijasa/ema

exit $fail
