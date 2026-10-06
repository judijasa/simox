# Replica schema identity: instance vs database — Plan & Progress

Date: 2026-10-05
Repos: simox (this repo); php_daas_framework (upstream framework);
       ../ema (upstream, reference only).

## Decision

A read replica is a same-name copy: it serves the primary's schema under a
plain name (`simo`) shared with the primary, never a schema of its own. ema
drops the `replicate-rewrite-db` rewrite and re-keys the replica on the
primary's database name (`dbname` = the primary's schema, `replica_of` = the
primary's instance). This repo's packages still declare `dbname: 'simo0'` /
`'simo1'`, and its committed connection templates still read "the section
header IS the database name", so the replica build and the app layer keep
asking for a `simo0`/`simo1` schema instead of the `simo` schema the instances
serve. This repo corrects both packages (`dbname: 'simo'`), its per-database
grants, and the connection templates/docs to the instance/database split.

The mechanism is upstream
(`../ema/doc/plans/2026-10-05-replica-schema-identity.md`); the connector and
grants-substitution change is the framework's (see
`php_daas_framework/doc/plans/2026-10-05-replica-schema-identity.md`).

## Changes

### simox (this repo)

- [x] `srv/simo0-D03J4K6RM0K7X8E4/default.php` — `dbname: 'simo0'` →
      `dbname: 'simo'` (the shared schema name); keep `binlog: true`. Update
      the header comment to say the package is the `simo0` instance serving the
      `simo` schema.
- [x] `srv/simo1-D0L4SEWTLXQQSVJC/default.php` — `dbname: 'simo1'` →
      `dbname: 'simo'` (the shared schema name); keep `type: 'replica'` and
      `replica_of: 'simo0'`. Update the header comment to say the package is
      the `simo1` instance serving the `simo` schema.
- [x] `pkg/simo0.roles-D0TVLE3YJCFE1A8U` and
      `pkg/simo1.roles-D0A3HGW7BHWSVCUQ` (`default.php` + `upgrade.sql`) — the
      `{{dbname}}` grants now resolve to `simo` (the shared schema), not
      `simo0`/`simo1`; grant targets unchanged, comments reworded to
      per-instance.
- [x] `etc/reuter.ini.template` — the "section header IS the database name"
      wording → the header names the instance, `DBNAME` the schema; both
      `[simo0]` and `[simo1]` carry `DBNAME=simo` (the schema is not named
      after either instance).
- [x] `etc/deploy.conf.template` — the `DEPLOY_REUTER_INI` comment ("one
      `[<dbname>]` section per database … `simo0` primary and `simo1`
      replica") → one section per instance; both sections carry `DBNAME=simo`.
- [x] `doc/system/replica-bootstrap.md` — the "distinct database names"
      naming convention and the "After the build" section: the schema is a
      plain name (`<schema>`) shared by both instances; the `[<replica>]`
      section carries `DBNAME=<schema>`.
- [x] `README.md` — quick-setup "the `simo0` database" → the `simo0` instance
      (schema `simo`); "per-database grant packages" → per-instance grants.
- [x] Same-split terminology sweep — `pkg/roles-<GUID>` (`default.php` +
      `upgrade.sql`), `doc/system/service-accounts.md`,
      `doc/system/private-config.md`, `etc/ema.default.conf`: "per-database"
      grant package / connectivity section → per-instance; `[<dbname>]` section
      → `[<instance>]`.

## Open items

- **Per-instance grants** — `gen-service-accounts <name>` still locates the
  grants package by instance name (`pkg/<name>.roles-*`) but now fills
  `{{dbname}}` from the package's `dbname` (the schema) — the framework change
  above. This repo's `dbname` flip (`simo0`/`simo1` → `simo`) and that
  substitution must land together or the replica grants target the wrong
  schema.
- **`$dbname = 'simo1'` in the entry points** — `public/index.php` /
  `public/insight.php` pass `'simo1'` to `connectTo`, which now means the
  replica *instance*; the value stays correct, the variable name is stale.
- **Sandbox replica** — `ema sandbox` now refuses a `type=replica` package
  (tracked upstream: a replica has no schema of its own); sandbox the primary
  (`simo0`) instead.
