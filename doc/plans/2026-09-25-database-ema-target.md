# Resolve the app layer to sandbox vs prod via EMA_TARGET — Plan & Progress

Date: 2026-09-25
Repos: simox (this repo); php_daas_framework (upstream framework);
       ../ema (upstream, reference only).

## Decision

Adopt the framework's new mode-aware app layer. simox's web entry points
(`public/index.php`, `public/insight.php`) call
`Database::connectAs($dbname, 'simox')` unconditionally, which is prod-only.
The framework adds `Database::connectTo()` (not `connect`: PHP 8.4's static
`PDO::connect` forbids a same-named override in the `Database extends PDO`
subclass), so they call `Database::connectTo($dbname, 'simox')`, and the same
code hits the local sandbox under `EMA_TARGET=sandbox` (dev/agent) and the prod
service account under `EMA_TARGET=prod`.

Both entry points hardcode `$dbname = 'simo1'`, so in sandbox mode
`connectTo('simo1', …)` resolves a single `var/sandbox/simo1-*/reuter.ini` by
dbname — which means a `simo1` sandbox must be built once with
`ema sandbox srv/simo1-<GUID>` before the web layer runs in sandbox mode.

No change to simox's prod `etc/reuter.ini` content or service-account
provisioning: prod resolution (`REUTER_INI` → `etc/reuter.ini`, `[simo1]`
section, `SIMOX_PASSWORD`) is unchanged.

See `php_daas_framework/doc/plans/2026-09-25-database-ema-target.md` for the
framework-side mechanism.

## Changes

### simox (this repo)

- [x] `public/index.php` — `Database::connectAs($dbname, 'simox')` → `Database::connectTo($dbname, 'simox')` (line 112).
- [x] `public/insight.php` — same (line 81).
- [x] `composer.json` — bump `judijasa/php-daas-framework` pin (`dev-main#29b3fc0…` → `dev-main#5029ade…`, the commit that adds `Database::connectTo`) and `judijasa/ema` (`dev-main#fd340e2…` → `dev-main#0d45c95…`, verb-scoped `EMA_TARGET`); `composer update`.
- [x] `etc/reuter.ini.template` — usage line `connectAs` → `connectTo`; dev-sandbox comment: the app layer now resolves the sandbox ini by dbname under `EMA_TARGET=sandbox` (prod via `REUTER_INI`, unchanged).
- [x] `doc/system/deploy.md` — environment contract (`REUTER_INI`/`EMA_TARGET`/`.env`): `EMA_TARGET` now selects the app layer's DB too; dev `.env` no longer sets `REUTER_INI`.
- [x] `README.md` — note the mode switch: the entry points now call `Database::connectTo`, so `EMA_TARGET=sandbox` resolves a `simo1` sandbox.
- [x] `doc/system/add-database.md` — replace the retired `ema drop` reference (deletion is now a deliberate `DROP DATABASE` over `ema mariadb`).

## Open items

- Sandbox resolution requires exactly one `simo1-*` sandbox. Build it with
  `ema sandbox srv/simo1-<GUID>` before running the web layer in sandbox mode;
  a second `simo1` sandbox makes the resolver error and ask for the full
  `<name>-<GUID>`.
- Dev `.env` regeneration: the framework's `init-local-env.sh` change has
  landed (bumped above); re-run `make dev-init` to drop the stale `REUTER_INI`
  line from simox's `.env`.
