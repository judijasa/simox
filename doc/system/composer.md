# Composer

How this repo consumes its PHP dependencies through Composer. This document
covers the **consumer side** — what `composer.json` declares and why. For the
framework side (what the `judijasa/php-daas-framework` Composer plugin installs,
and how the framework and `ema` packages deliver their `vendor/bin` CLIs), see
the framework's
[doc/system/composer.md](https://github.com/judijasa/php_daas_framework/blob/main/doc/system/composer.md).

## composer.json

```json
{
  "license": "MIT",
  "minimum-stability": "dev",
  "prefer-stable": true,
  "config": {
    "allow-plugins": {
      "judijasa/php-daas-framework": true
    }
  },
  "require": {
    "sunra/php-simple-html-dom-parser": "1.5.2",
    "judijasa/php-daas-framework": "dev-main",
    "judijasa/ema": "dev-main"
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/judijasa/php_daas_framework"
    },
    {
      "type": "vcs",
      "url": "https://github.com/judijasa/ema"
    }
  ]
}
```

Consumer-relevant keys:

- `license` — this repo's own license (MIT); unrelated to the dependencies'.
- `minimum-stability` / `prefer-stable` — the two framework packages are
  consumed from their `dev-main` branch (a `dev` stability level), so
  `minimum-stability` must be `dev`; `prefer-stable: true` keeps every other
  package on a stable release instead of a dev branch.
- `config.allow-plugins` — allow-lists the `judijasa/php-daas-framework`
  Composer plugin, the only plugin this repo runs (Composer refuses to run a
  plugin that is not allow-listed).
- `require` — the dependencies. `sunra/php-simple-html-dom-parser` is a regular
  Packagist package pinned to a stable tag; `judijasa/php-daas-framework` and
  `judijasa/ema` are VCS packages consumed from `dev-main`, either floating or
  frozen to a commit (see Dependency updates below).
- `repositories` — the two framework packages are not published on Packagist,
  so Composer must be told where their VCS sources live.

## Dependency updates

Both external packages — `judijasa/php-daas-framework` and `judijasa/ema` —
are delivered exclusively via Composer (single code delivery path). There is no
Nix-side pin to keep in step anymore: `flake.nix` supplies only the environment
binaries (php + extensions, composer, mariadb, bash, tmux, jq), not the code of
either package.

`composer.json` may reference them in either form, and this repo has no policy
mandating one:

- `dev-main` — floats on the upstream branch.
- `dev-main#<hash>` — frozen to an exact commit.

The tradeoff: a hash holds the revision fixed until it is re-pinned, while a
floating requirement follows upstream on the next update. Either way
`composer.lock` records the resolved revision, and every `composer install`
(dev bootstrap, production deploy) installs exactly that revision — so
"latest" is a property of the update step, not of the requirement form.

### Update to the latest

Re-resolve the requirements against upstream and refresh the lock:

```bash
composer update judijasa/php-daas-framework judijasa/ema
```

A floating requirement moves to upstream `main` HEAD; a frozen one does not
move, because the hash is the constraint.

### Re-pin to a specific commit

Rewrite the requirement to an exact commit, which must already be pushed — the
`#<hash>` suffix is resolved from the package's remote:

```bash
composer require "judijasa/php-daas-framework:dev-main#<hash>" \
                 "judijasa/ema:dev-main#<hash>"
```

This rewrites `composer.json` and `composer.lock` and refreshes `vendor/`.

Upstream documentation cited from these docs (the framework's and `ema`'s
`doc/system/*.md`) is linked at upstream `main`, while the runtime follows the
revision recorded in `composer.lock`. When the two disagree, the lock wins — if
a documented behaviour seems to be missing, read the same document at the locked
revision.

### When to update

- After validating a new upstream version locally (`nix develop` + full test run).
- Update each package independently — the nix env rev and each Composer code rev
  are decoupled, so the two packages no longer have to agree with each other.
- Commit the updated `composer.json` and `composer.lock` so the resolved
  revisions are tracked in git.
