#!/usr/bin/env sh

set -euo pipefail

# Check if composer.json is staged
if git diff --cached --name-only | grep -qx "composer.json"; then
    echo "composer.json is staged. Performing tasks..."
    echo "Removing old composer.lock and vendor/..."
    rm composer.lock
    rm -r vendor
    echo "Running composer install..."
    composer install
    echo "Staging new composer.lock..."
    git add composer.lock
    echo "Patching vendor/phpcasperjs/phpcasperjs/src/Casper.php..."
    TARGET_FILE="vendor/phpcasperjs/phpcasperjs/src/Casper.php"
    sed -i 's/private $script = \x27\x27;/protected $script = \x27\x27;/g' $TARGET_FILE
fi
