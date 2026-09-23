# Composer

How this repo consumes its PHP dependencies through Composer. This document
covers the **consumer side** — what `composer.json` declares and why. For the
framework side (what the `judijasa/php-daas-framework` Composer plugin installs,
and how the framework and `ema` packages deliver their `vendor/bin` CLIs), see
`../php_daas_framework/doc/system/composer.md` in the framework repo.

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
    "judijasa/php-daas-framework": "dev-main#fdb6ad37a73f719c946fec9f1e947ce534fc8fc0",
    "judijasa/ema": "dev-main#171228340b3bb31b6847ad6500edeca562aea426"
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
  `judijasa/ema` are VCS packages pinned to an exact commit (see Dependency
  pinning below).
- `repositories` — the two framework packages are not published on Packagist,
  so Composer must be told where their VCS sources live.

## Dependency pinning

Both external packages — `judijasa/php-daas-framework` and `judijasa/ema` —
are delivered exclusively via Composer (single code delivery path). Each is
pinned to a known-good commit in `composer.json` using the `dev-main#<hash>`
form, recorded in `composer.lock`. There is no Nix-side pin to keep in step
anymore: `flake.nix` supplies only the environment binaries (php + extensions,
composer, mariadb, bash, tmux, jq), not the code of either package.

To bump a package to a newer upstream commit, update its `require` entry in
`composer.json` and refresh the lock:

```bash
composer require "judijasa/php-daas-framework:dev-main#<hash>" \
                 "judijasa/ema:dev-main#<hash>"
```

The `#<hash>` suffix tells Composer to resolve `dev-main` to that exact commit,
recorded in `composer.lock`. Subsequent `composer install` runs (including on
the production server) always fetch that revision.

### When to bump

- After validating a new upstream version locally (`nix develop` + full test run).
- Bump each package independently — the nix env rev and each Composer code rev
  are decoupled, so the two packages no longer have to agree with each other.
- Commit the updated `composer.json` and `composer.lock` so the pinned
  revisions are tracked in git.
