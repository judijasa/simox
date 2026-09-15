# Private-config deploy — Plan & Progress

Date: 2026-09-15
Repos: simox (this repo, consumer data); simox_cnf (../simox_cnf, private
       config); php_daas_framework (../php_daas_framework, upstream
       mechanism).

## Decision

Adapt to the framework's `0a01503` commit, which moves the deployment of a
consumer's private operational config into the framework as a generic
`bin/deploy-private-config` CLI — replacing simox_cnf's own `bin/deploy.sh`.
Prod ships `reuter.ini` — whole, from the private repo's committed content via
`git archive <ref>` — to each host's stable per-app dir
(`DEPLOY_PRIVATE_CONFIG_DIR`), and nothing else; `machines.ini` and `team.ini`
stay dev/deploy-time. The framework's `fetch-private-data` drops the
`PRIVATE_DATA_SOURCE` and loose-file `etc/` fallbacks in favour of a single
`PRIVATE_DATA_GIT` git mode, and `db-check` derives the instance set from the
host's own `mariadb@*` units instead of the `machines.ini` roster.

simox keeps only the *data*: the `DEPLOY_PRIVATE_CONFIG_DIR` path in the
committed `deploy.conf`, the git-only `.private-source.example`, and the
template/doc wording. See
`php_daas_framework/doc/plans/2026-09-14-framework-private-config-deploy.md`
for the framework-side mechanism.

## Changes

### php_daas_framework (../php_daas_framework)

- [x] `bin/deploy-private-config` + `fetch-private-data`/`db-check`/
      `pf-deploy.sh` rework — tracked in
      `php_daas_framework/doc/plans/2026-09-14-framework-private-config-deploy.md`.

### simox (this repo)

- [x] `composer.json` / `composer.lock` — pin bump to `0a01503` (picks up
      `bin/deploy-private-config`).
- [x] `.private-source.example` — drop `PRIVATE_DATA_SOURCE` and the loose-file
      `etc/` fallback; document the single `PRIVATE_DATA_GIT` (+
      `PRIVATE_DATA_REF`) mechanism and that `reuter.ini` is the only file
      shipped to prod.
- [x] `etc/deploy.conf` — add `DEPLOY_PRIVATE_CONFIG_DIR` (the stable per-app
      private dir on the host).
- [x] `etc/machines.ini.template` — roster readers are `pf-deploy.sh` +
      `deploy-private-config` (not `db-check`); `db:<name>` names a database,
      verified by `db-check` via the host's `mariadb@*` units.
- [x] `doc/system/private-config.md` — "Ships to prod?" column; `reuter.ini`
      is the only file that leaves the private repo for a host; two-step prod
      delivery (`deploy-private-config` + `fetch-private-data`).
- [x] `README.md` — `db:<name>` wording, `.private-source` git-only,
      `fetch-private-data`/`db-check` built-in steps.
- [x] `Makefile`, `bin/deploy.sh`, `bin/deploy/server-side-post-deploy.sh` —
      header comments: `fetch-private-data` is a built-in per-host step.
- [x] `doc/plans/2026-09-15-private-config-deploy.md` — this doc.

### simox_cnf (../simox_cnf)

- [x] `bin/deploy.sh` — removed (replaced by the framework
      `deploy-private-config`).
- [x] `README.md` — "Ships to prod?" column; git-only consumption; Deployment
      section rewritten to the framework split. Tracked in
      `simox_cnf/doc/plans/2026-09-15-private-config-deploy.md`.

## Open items

- **Framework commit not yet pushed** — `0a01503` is local-only in
  `../php_daas_framework` (origin/main is `d16ec3a`); `composer install` on a
  fresh checkout fails until it is pushed. The lock is written in the github
  shape (source + dist) for when it lands.
- **Prod has no git** — the two-step delivery assumes prod holds no
  `.private-source` and resolves `DEPLOY_PRIVATE_CONFIG_DIR` (framework open
  item).
- **`mariadb@<db>` unit name == db name** — `db-check`'s unit enumeration
  depends on it (framework open item).
