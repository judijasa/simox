# Deploy env + framework private-file shipping (simox side) — Plan & Progress

Date: 2026-09-20
Repos: simox (this repo); php_daas_framework (../php_daas_framework, upstream)

## Decision

Adopt the framework's new transport (`doc/plans/2026-09-20-deploy-env-and-private-files.md`):
`deploy.conf` stays on the deploy machine (its values travel as replayed env,
never as a prod file), and `reuter.ini` is the only file shipped to prod — via
`DEPLOY_PRIVATE_FILES="reuter.ini"`. simox deletes its own ship + inject tooling
(`bin/deploy-private-config`, `bin/deploy/inject-private-config.sh`) and the
`DEPLOY_PRIVATE_CONFIG_DIR` key; `bin/fetch-private-data` stays as the
materialize step.

## Changes

### simox (this repo)

- [x] `etc/deploy.conf.template` — drop `DEPLOY_PRIVATE_CONFIG_DIR` and
      `DEPLOY_PRE_PROVISION_CMD`; add `DEPLOY_PRIVATE_FILES="reuter.ini"`.
- [x] `etc/machines.ini.template`, `etc/reuter.ini.template`,
      `.private-source.example` — drop the ship/inject references (`pf-deploy.sh`
      alone reads the roster; `reuter.ini` ships via `DEPLOY_PRIVATE_FILES`; no
      `inject-private-config.sh` restore).
- [x] `bin/deploy.sh` — drop the `bin/deploy-private-config` ship step (keep
      `bin/fetch-private-data`); reword the header.
- [x] `bin/deploy-private-config`, `bin/deploy/inject-private-config.sh` —
      delete.
- [x] `bin/deploy/provision-extra.sh`, `bin/deploy/server-side-post-deploy.sh` —
      stop sourcing `etc/deploy.conf` on the host (env replay / `$PWD`).
- [x] `bin/fetch-private-data` — reword the header comment (ship is now the
      framework's `DEPLOY_PRIVATE_FILES`).
- [x] `Makefile` — reword the header comment.
- [x] `README.md`, `doc/system/private-config.md` — rewrite delivery (`deploy.conf`
      is deploy-machine env; `reuter.ini` ships via `DEPLOY_PRIVATE_FILES`).
- [x] `doc/plans/2026-09-20-deploy-env-and-private-files.md` — this doc.
- [ ] `composer.json` / `composer.lock` — bump the framework pin once upstream
      commits (lock-sync).

## Open items

- `composer.json`/`composer.lock` bump + `composer install` pending the upstream
  framework commit (not performed here).
