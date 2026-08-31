# Deploy CLI rename — Plan & Progress

## Decision

Rename the deploy entrypoints so the framework CLI carries the `pf-` prefix
and the consumer wrapper takes the plain `deploy.sh` name:

- framework `bin/deploy` → `bin/pf-deploy.sh` (matches the existing
  `bin/pf-provision.sh`);
- consumer `bin/deploy-wrapper.sh` → `bin/deploy.sh`;
- consumer post-deploy `bin/deploy/post-nix.sh` → `bin/deploy/post-pf-deploy.sh`
  (it runs after the whole framework deploy, not just the nix copy).

The framework CLI is Composer-delivered, so the wrapper's call and the
framework's `composer.json` `bin` list change together. After the framework
rename lands, simox must `composer update` (or reinstall) to regenerate
`vendor/bin/pf-deploy.sh` before the wrapper's call resolves.

## Changes

### simox (this repo)
- [x] `bin/deploy-wrapper.sh` → `bin/deploy.sh` (rename). In the renamed file:
      header + usage comments; `vendor/bin/deploy` → `vendor/bin/pf-deploy.sh`;
      post-deploy call `bin/deploy/post-nix.sh` → `bin/deploy/post-pf-deploy.sh`;
      `deploy-wrapper:` stderr prefix → `deploy:`.
- [x] `bin/deploy/post-nix.sh` → `bin/deploy/post-pf-deploy.sh` (rename);
      update header comment (`bin/deploy-wrapper.sh` → `bin/deploy.sh`).
- [x] `Makefile` — `deploy:` target `bin/deploy-wrapper.sh` → `bin/deploy.sh`;
      header comments (`vendor/bin/deploy`, `bin/deploy/post-nix.sh`).
- [x] `README.md` — `bin/deploy-wrapper.sh` → `bin/deploy.sh`;
      `bin/deploy/post-nix.sh` → `bin/deploy/post-pf-deploy.sh`; name the
      framework CLI `vendor/bin/pf-deploy.sh` where it is described.

### php_daas_framework (../php_daas_framework)
- [x] `bin/deploy` → `bin/pf-deploy.sh` (rename); update self-references
      (`deploy:` error prefixes, usage header).
- [x] `composer.json` — `"bin/deploy"` → `"bin/pf-deploy.sh"`.
- [x] `README.md` — `bin/deploy` references (usage, CLI list, deploy section).
- [x] `doc/system/ema.md` — `vendor/bin/deploy` → `vendor/bin/pf-deploy.sh`.
- [x] `Makefile` — header comment (`phprun/deploy/...`).

## Open items

- The framework repo is outside the write workspace — edit via shell (stage
  content under a temp dir, then `cp`), as noted in
  `doc/plans/2026-08-14-deploy-relocation.md`.
- Order of operations: rename + `composer.json` in the framework first, then
  `composer update` + rename in simox, so the wrapper's call matches the
  delivered bin name.
- Step composition question (should `gen-env`/`gen-reuter`/`cron-manifest` be
  folded into `deploy` or stay wrapper-composed) is tracked separately in
  `doc/plans/2026-08-31-deploy-step-composition.md`; it does not block these
  renames.
