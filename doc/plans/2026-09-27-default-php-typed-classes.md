# Typed PHP config classes — Plan & Progress

Date: 2026-09-27
Repos: simox (this repo), php_daas_framework (upstream, `../php_daas_framework`), ema (upstream, `../ema`).

## Decision

Migrate this repo's `default.php` files from the flat `$db`/`$dependencies`
arrays to instances of ema's typed `Ema\Config\*` value classes
(`DatabaseConfig`, `PackageConfig`, `RolesConfig`). ema is already a Composer
dependency (`judijasa/ema: dev-main`); the classes arrive via ema's new PSR-4
autoload mapping (see `../ema/doc/plans/2026-09-27-default-php-typed-classes.md`),
so no new dependency or tooling is introduced here — this repo already runs
PHPStan and Composer. This repo changes only the config *shape* its packages
author; nothing here reads `default.php` itself — the framework's
`gen-grants`/`gen-service-accounts` do, and they move to the returned object
in the framework's own repo (see
`../php_daas_framework/doc/plans/2026-09-27-default-php-typed-classes.md`).

One relocation lands with this migration: the roles/grants packages move
from `srv/` to `pkg/` — the shared `roles-D0YRR7WII6V1XDZR` plus the two
per-database grants packages. They are manifests, not database definitions
(they carry no `$db`), and `pkg/` is where ema's manifest shape lives, so
`srv/` is left holding database definitions only. This repo's README, its
`doc/system/` pages, the `etc/` templates and the `simo0` package comment
follow the move; the framework's `gen-*` CLIs glob `pkg/` for them.

## Current state

- 30 `default.php` files: 5 `srv/` + 25 `pkg/`. Today they author
  `$db = array(...)` and/or `$dependencies = array(...)`;
  `srv/roles-D0YRR7WII6V1XDZR/default.php` also assigns
  `$sources`/`$accounts`/`$allowlist`, which the framework's
  `gen-service-accounts` reads. The three `roles`/`*.roles` packages carry
  no `$db` at all — they are manifests, not database definitions.
- PHPStan is already wired: `phpstan.dist.neon` (committed, `level: 5`,
  `paths: .`). Pre-commit runs a scoped `phpstan` hook (`pass_filenames: true`,
  `files: \.php$`) on staged `.php` files; pre-push runs `phpstan-full` over the
  whole tree.
- ema is installed as a Composer dependency pinned to a commit
  (`judijasa/ema: dev-main#16d11d9…`) that predates the `Ema\Config\*` PSR-4
  mapping; `judijasa/php-daas-framework` is pinned to `dev-main#2e8f4a0…`,
  whose `gen-*` CLIs still glob `srv/` for the roles packages and read the
  flat arrays. Both pins must move — ema to `7066419`, the framework to
  `34b31043` — before the classes resolve and the relocated packages are found.

## Changes

### simox (this repo)

- [x] `srv/roles-D0YRR7WII6V1XDZR/`, `srv/simo0.roles-D0TVLE3YJCFE1A8U/`, `srv/simo1.roles-D0A3HGW7BHWSVCUQ/` — relocate to `pkg/` (they carry no `$db`; `srv/` now requires a `DatabaseConfig`), so they can become `PackageConfig` manifests.
- [x] `README.md`, `doc/system/{add-database,deploy,private-config,service-accounts}.md`, `etc/{deploy.conf,machines.ini,reuter.ini,team.ini}.template`, `srv/simo0-D03J4K6RM0K7X8E4/default.php` — the roles-package paths (`srv/roles-<GUID>` → `pkg/roles-<GUID>`, `srv/<name>.roles-<GUID>` → `pkg/<name>.roles-<GUID>`); database-package paths (`srv/<name>-<GUID>`) and the `/srv/apps/...` host paths stay.
- [x] `srv/simo0-D03J4K6RM0K7X8E4/default.php`, `srv/simo1-D0L4SEWTLXQQSVJC/default.php` — migrate to `return new Ema\Config\DatabaseConfig(...)`.
- [x] `pkg/roles-D0YRR7WII6V1XDZR/default.php`, `pkg/simo0.roles-D0TVLE3YJCFE1A8U/default.php`, `pkg/simo1.roles-D0A3HGW7BHWSVCUQ/default.php` — migrate to `return new Ema\Config\PackageConfig(...)`, the shared declaration as a nested `RolesConfig`.
- [x] the 25 `pkg/*/default.php` files — `activity_monitor-A170276C72D14319`, `cursorseq-A8FEE4C088E5419C`, `simo-C196A24801D24B16`, `municipio-WK095781S7NA146R`, `nivel-F39B6ACCF1574A6E`, `alternativa-EO8GVJ26MTZ5UI21`, `convocatoria-CP5LH0NIV4WY9J5B`, `denominacion-5R9BK7DS01O7A23G`, `departamento-BD672733D7AA455F`, `dependencia-J31Y7UXN1J2K9W4L`, `documento-B4389BGTHWELGSQP`, `empleo-9A645583F8F24B28`, `empleo_funcion-S9N4FY21N92CQDTS`, `empleo_requisito-9KS537SIXRO89TUK`, `empleo_snapshot-00B5FEB6B8B84570`, `empleo_vacante-AGC76TYMHR1LD9PT`, `entidad-67REHGMCNAEUY2R1`, `estudio-LZ4GPR10TNN5SO2L`, `experiencia-QZ7HAJ00VTP9UI2D`, `funcion-12ZLRQNT99GB3W8Z`, `otros-AU3QOR84CPX3YW95`, `requisito-7SACLUK8T6IU4BP3`, `tipo_entidad-ZKL6SSAAC65UIKYI`, `vacante-WZ0T87MRR815E49Y`, `vw_empleo-DAF75B74DD6A469D` — migrate `default.php` to `return new Ema\Config\PackageConfig(...)`.
- [x] `composer.json` — move the `judijasa/ema` pin to `dev-main#706641944b98f6196200f901100bd7b1442e5100` and the `judijasa/php-daas-framework` pin to `dev-main#34b31043f34bcd9a84db5b73088311f39f1036c4`; `composer update` alone will not move a commit pin.
- [x] `phpstan.dist.neon` — resolve `Ema\Config\*` via Composer autoload (after the pin bump) and confirm the `default.php` files are covered.
- [x] `.pre-commit-config.yaml` — confirm the scoped `phpstan` hook passes staged `default.php` filenames (it already matches `\.php$`).

## Open items

- **Db-less `srv/` packages** — resolved: the three roles/grants packages
  move to `pkg/`, ema's manifest root, so every `srv/` file can return a
  `DatabaseConfig`. The framework's `gen-service-accounts` keeps reading this
  repo's declaration; it now reads it from the returned `RolesConfig`
  (`sources`/`accounts`/`allowlist`) and globs `pkg/` for the packages.
- **Relocation and pin bump land together** — the relocated packages are only
  found by a framework that globs `pkg/`; the pinned
  `judijasa/php-daas-framework` commit still globs `srv/`, so the pin bump
  above is a prerequisite, not a follow-up.
- **`{{dbname}}` is not an ema concept** — the relocated grants packages keep
  their placeholder SQL, substituted by the framework's `gen-*` CLIs. No
  `srv/` database may list a roles package in `$dependencies`, or ema would
  apply the literal `{{dbname}}`. None does today: `simo0` depends on
  `simo-C196A24801D24B16` only, and `simo1` carries no `$dependencies`.
- **Transition window** — resolved: ema has no dual shape. A legacy `$db` array
  is a hard error (`must return an \Ema\Config\DatabaseConfig`), so all 30 files
  flip in a single change set.
- **Timing** — resolved: `Ema\Config\*` and the PSR-4 mapping landed in ema
  `72ccdcd`, and the `pkg/` glob + returned-object reader landed in
  `judijasa/php-daas-framework` `d80f26f` (both pushed to `main`); the pin
  bump above is this repo's first step. `main` has since gained one trivial
  commit (`34b31043`, a pre-push-gate output quietening), so the pin lands there.
- **ema `RolesConfig::$allowlist` type** — resolved: ema now types `$allowlist`
  as `array<int, string>` (ema `7066419`, committed + pushed); this repo's pin
  is re-pinned to `7066419`, so PHPStan is clean.
