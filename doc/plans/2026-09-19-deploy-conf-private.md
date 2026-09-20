# deploy.conf as private data — Plan & Progress

Date: 2026-09-19
Repos: simox (this repo, consumer data); simox_cnf (../simox_cnf, private
       config); php_daas_framework (../php_daas_framework, upstream
       mechanism).

## Decision

`etc/deploy.conf` stops being committed project-static config and becomes
private data like every other operational file: the real file lives in
`simox_cnf`, this repo commits only `etc/deploy.conf.template` (mirroring the
current production values — they carry no secrets), and the framework's
`fetch-private-data` injects it as a symlink into `etc/deploy.conf`. The public
history no longer discloses the deployment target, and that target now reaches
prod through the private channel.

The deploy flow *sources* `deploy.conf` in four places — the consumer
`bin/deploy.sh`, the framework's `pf-provision.sh`, the server steps and
`cron-manifest`, plus `fetch-private-data` itself — so this is a mechanism
change, not just a data move: the framework ships the file with `reuter.ini`
(`deploy-private-config`) and injects it right after the repo swap, before
anything sources it. See
`php_daas_framework/doc/plans/2026-09-19-deploy-conf-private.md` for the
mechanism side; this doc tracks the consumer side only.

## Config model

| File | Public template | Private data | Ships to prod? |
|---|---|---|---|
| `etc/deploy.conf` | `etc/deploy.conf.template` | deployment target (paths, the app-user name, the cron target) | yes — with `reuter.ini` |
| `etc/reuter.ini` | `etc/reuter.ini.template` | per-database connectivity sections | yes — with `deploy.conf` |

Both are injected by `fetch-private-data` (dev from `.private-source`; prod from
the stable per-app dir `DEPLOY_PRIVATE_CONFIG_DIR`), and both must be in the
resolved source: `fetch-private-data` aborts loudly when one is missing. A
project that commits its own `deploy.conf` instead is unaffected — the injection
is skipped while a real file sits at `etc/deploy.conf`.

## Changes

### php_daas_framework (../php_daas_framework)

- [x] `bin/fetch-private-data` — `deploy.conf` wired (first in the file list)
      and required unless the repo carries a committed copy. Tracked in
      `php_daas_framework/doc/plans/2026-09-19-deploy-conf-private.md`.
- [x] `bin/deploy-private-config` — ships `deploy.conf` alongside `reuter.ini`
      when the private repo has it.
- [x] `bin/pf-deploy.sh` — injects the private config right after the repo swap
      (passing `DEPLOY_PRIVATE_CONFIG_DIR` in the environment), before
      `pf-provision.sh` and the server steps source `etc/deploy.conf`.
- [x] `bin/gen-env`, `bin/pf-provision.sh`, `bin/cron-manifest` — header
      comments: `etc/deploy.conf` is injected private data, not committed.

### simox (this repo)

- [x] `etc/deploy.conf` — removed (was committed); the real file now lives in
      `simox_cnf/deploy.conf`.
- [x] `etc/deploy.conf.template` — new; mirrors the current production values,
      with the private-data contract in its header (a real file at
      `etc/deploy.conf` shadows the injected data).
- [x] `.gitignore` — ignore `/etc/deploy.conf`.
- [x] `.private-source.example` — tracked files now include `deploy.conf`, which
      ships to prod with `reuter.ini`.
- [x] `.private-source` — the git-ignored pointer on this machine, re-synced
      from the example (comments only; the URL is unchanged).
- [x] `bin/deploy.sh` — header comment: `etc/deploy.conf` is injected private
      data.
- [x] `doc/system/private-config.md` — "Ships to prod?" table; two private files
      reach a host; the two-step prod delivery.
- [x] `README.md` — the prod `.env` is projected from the injected
      `etc/deploy.conf`.
- [x] `doc/plans/2026-09-19-deploy-conf-private.md` — this doc.

### simox_cnf (../simox_cnf)

- [x] `deploy.conf` — new; the real deployment target, moved from
      `simox/etc/deploy.conf` with its header reworded to the private-data
      contract.
- [x] `README.md` — `deploy.conf` row in the file table (ships to prod with
      `reuter.ini`); consumption and Deployment sections.

## Open items

- **composer pin** — `composer.json` pins the framework by commit; the pin must
  move to the commit carrying the mechanism change before this repo can deploy
  with a private `deploy.conf`, and `vendor/bin` must be refreshed.
- **`simox_cnf/deploy.conf` has to be committed and pushed** before
  `make dev-init` can inject it: the git-ignored clone in `var/private-data` is
  the only source, and a loose working-tree copy is never used.
- **Running without private data** — `deploy` cannot run until the template is
  copied to `etc/deploy.conf`; that real file then shadows the injected data
  (loud warning, never overwritten), so it must be removed before switching back
  to private config.
