# Config default files — Plan & Progress

Date: 2026-10-01
Repos: simox (this repo); private config repo; php_daas_framework (upstream);
ema (upstream)

## Decision

simox commits `etc/dev.default.conf` and `etc/ema.default.conf` carrying its
convention values, so quick setup no longer requires materializing private
config. The git-ignored override files become optional deltas; the reader
mechanism (default → override, with loud validation) is tracked in the upstream
repos' own plans.

Values this repo owns as defaults:

- `etc/dev.default.conf` — `DBUSER=simox`, `SSL_DIR=~/.simox/ssl`;
- `etc/ema.default.conf` — `[default] ssl-ca = /etc/simox/ssl/ca.crt`.

`bin/fetch-private-data` no longer requires `dev.conf`/`ema.conf` (they become
optional overrides); `deploy.conf`'s `DEPLOY_PRIVATE_FILES` drops `ema.conf` —
the committed default rides with the swapped repo directory. `reuter.ini` stays
a required private file (per-database endpoints, no default).

## Changes

### simox (this repo)

- [x] `etc/dev.conf.template` → `etc/dev.default.conf` — commit the real dev
      values (`DBUSER=simox`, `SSL_DIR=~/.simox/ssl`) as consumed defaults; the
      `.template` suffix stays reserved for copy-me shapes (`reuter.ini`, etc.).
- [x] `etc/ema.conf.template` → `etc/ema.default.conf` — commit
      `[default] ssl-ca = /etc/simox/ssl/ca.crt` as the consumed default.
- [x] `.gitignore` — keep ignoring `/etc/dev.conf` and `/etc/ema.conf` (the
      overrides); the `.default.conf` files are tracked.
- [x] `bin/fetch-private-data` — move `dev.conf` and `ema.conf` out of
      `REQUIRED_FILES` into `OPTIONAL_FILES` (now optional overrides);
      `deploy.conf` and `reuter.ini` stay required.
- [x] `etc/deploy.conf.template` — `DEPLOY_PRIVATE_FILES="reuter.ini"` (drop
      `ema.conf`; the default rides with the repo), comment updated.
- [x] `Makefile` — `_dev-init-local-env` comment (values relayed from the
      committed default or the override, not only the materialized file).
- [ ] `composer.json` + `composer.lock` — re-pin `judijasa/php-daas-framework`
      and `judijasa/ema` to the commits carrying the reader changes (after they
      land upstream); verify no old hash remains.
- [x] (doc) — `doc/system/private-config.md` (required/optional file table),
      `doc/system/deploy.md` (`DEPLOY_PRIVATE_FILES`), `doc/system/machine-certs.md`
      (`SSL_DIR` default/override semantics), `README.md` (quick-test wording).

### private config repo

- [x] `dev.conf` — become an optional override (keep only non-default values;
      may shrink to a comment if it matches the default).
- [x] `ema.conf` — become an optional override (same).
- [x] `deploy.conf` — `DEPLOY_PRIVATE_FILES="reuter.ini"`, comment updated.
- [x] `README.md` — required/optional file table updated.

### php_daas_framework / ema (upstream)

Tracked in their own plans:
`../php_daas_framework/doc/plans/2026-10-01-dev-conf-default.md` and
`../ema/doc/plans/2026-10-01-ema-conf-default.md`.

## Open items

- **Mechanism landing first** — the reader changes must land upstream before
  this repo's `.default.conf` files are consumed; pin the new commits.
- **Override trimming** — decide whether the private `dev.conf`/`ema.conf`
  shrink to comments or keep explicit (redundant-with-default) values.
- **Deploy override list** — confirm `reuter.ini` is the only remaining
  `DEPLOY_PRIVATE_FILES` entry.
