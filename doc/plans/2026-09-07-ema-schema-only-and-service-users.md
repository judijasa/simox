# Adapt to ema schema-only model — Plan & Progress

Date: 2026-09-07
Repos: simox (this repo); php_daas_framework (upstream framework);
       ../ema (upstream, reference only).

## Decision

ema dropped user/grants emission and the `ema init db` / `ema init tables`
verbs. The `simo` database is now built with the new surface:
`srv/simo-D03J4K6RM0K7X8E4` carries `$db = array('dbname' => 'simo', ...)` +
`$dependencies = array('simo-C196A24801D24B16')`, and its `upgrade.sql` is
DDL-only (`{{dbname}}`/`{{charset}}`/`{{collation}}`). Users and grants are
consumer policy: the framework's `gen-service-users` creates
`admin`/`reader`/`public`, and the per-table `public` SELECT grants stay in
the `pkg/` packages (applied by ema).

`public` is the anonymous any-host read account, so its grants move from
`'public'@'{{servername}}'` to `'public'@'%'` (`{{servername}}` is no longer
filled). `admin`/`reader` are pinned to `SERVERNAME` (default `localhost`) by
`gen-service-users`.

See `php_daas_framework/doc/plans/2026-09-07-ema-schema-only-and-service-users.md`
for the framework-side mechanism.

## Changes

### simox (this repo)

- [x] `srv/simo-D03J4K6RM0K7X8E4/default.php` — new `$db` + `$dependencies = array('simo-C196A24801D24B16')`.
- [x] `srv/simo-D03J4K6RM0K7X8E4/upgrade.sql` — DDL-only; dropped the buggy user/grants block (missing opening quote on `reader` and hardcoded `simo.*`).
- [x] `pkg/*/upgrade.sql` (21 files) — public grants `'public'@'{{servername}}'` → `'public'@'%'`.
- [x] `.env` — `EMA_MODE=dev` → `EMA_TARGET=sandbox`.
- [x] `README.md` — `ema sandbox srv/simo-D03J4K6RM0K7X8E4` replaces `ema init db simo` + `ema init tables ...`; `EMA_TARGET`.
- [x] `etc/reuter.ini.template` — dev-sandbox path + `EMA_TARGET` comment.
- [x] `etc/deploy.conf` — `ema create srv/<name>-<GUID>` comment.
- [x] `bin/deploy/server-side-post-deploy.sh` — `EMA_TARGET` comment.

## Open items

- Dev `.env` still points `REUTER_INI` at `var/reuter.local.ini`
  (`init-local-env.sh`), while `ema sandbox` writes
  `var/sandbox/simo-d03j4k6rm0k7x8e4/reuter.ini`. Running `gen-service-users`
  against the sandbox and pointing the app layer at it is pending the
  framework's dev-wiring decision.
- `admin`/`reader` host pin: `SERVERNAME` defaults to `localhost`; if app
  servers reach `simo` over ZeroTier, add `SERVERNAME=<db-host-zerotier-ip>`
  (or `%`) to the prod section before `gen-service-users`.
