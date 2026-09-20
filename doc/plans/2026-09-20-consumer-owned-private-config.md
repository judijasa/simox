# Private config injection owned by simox — Plan & Progress

Date: 2026-09-20
Repos: simox

## Decision

simox owns the full fetch → ship → inject pipeline (the framework now only
sources real `etc/` files and provides a `DEPLOY_PRE_PROVISION_CMD` hook). Dev
materializes real files (copy, hardcoded 6-file list) into `etc/`; prod ships
the 2 runtime files to `DEPLOY_PRIVATE_CONFIG_DIR` and injects them via the hook.

## Changes

- [ ] `bin/fetch-private-data` — new: read `.private-source`, clone/fetch
      `PRIVATE_DATA_GIT`@`PRIVATE_DATA_REF` → `var/private-data`, copy these 6
      files into `etc/` (hardcoded): `deploy.conf reuter.ini machines.ini
      team.ini hosts host-hardening.php`; error on missing `reuter.ini` /
      `deploy.conf`; idempotent (overwrite).
- [ ] `bin/deploy-private-config` — new: ship `deploy.conf` + `reuter.ini`
      (whole, via `git archive HEAD`) to `DEPLOY_PRIVATE_CONFIG_DIR` on each
      `[prod]` host; roster via `vendor/bin/pf-roster`.
- [ ] `bin/deploy/inject-private-config.sh` — new host-side copy of
      `$DEPLOY_PRIVATE_CONFIG_DIR/{deploy.conf,reuter.ini}` into `etc/` (real
      files).
- [ ] `bin/deploy.sh` — orchestrate: fetch → ship → `vendor/bin/pf-deploy.sh`.
- [ ] `Makefile` / dev-init wiring — dev-init uses the simox-owned injector.
- [ ] `etc/deploy.conf.template`, `.private-source.example`, `README.md`,
      `doc/system/private-config.md` — reword to simox ownership.
- [ ] `composer.json` / `composer.lock` — bump pin to the new framework commit;
      refresh `vendor/bin`.

## Open items

- none
