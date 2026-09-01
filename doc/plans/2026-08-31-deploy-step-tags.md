# Deploy step tags — Plan & Progress

Date: 2026-08-31
Repos: simox (this repo), php_daas_framework (../php_daas_framework)

## Decision

`pf-deploy.sh` ships the repo to **every** `[prod]` host in `etc/machines.ini`
(or the single `wanted` host, when targeted) — independent of tags. Tags do
not gate the code ship; they gate **specific deploy steps** layered on top of
it.

`[prod]` values become `tag[:name]` tokens instead of a bare database list. A
tag names a deploy step; an optional `:name` names a resource within that
step. `db` is the framework's single built-in tag (MariaDB instance +
`gen-reuter` connectivity); `web` (restore Apache www-data traversal) and
`worker` (install the cron-manifest output) are simox's steps. Any other tag
is consumer-owned and carried through generically.

simox runs a single website, so `web` and `worker` are **bare flags** (no
name); `db` stays named (`db:<name>`, the database name). The `web` step
restores Apache www-data traversal on the freshly-swapped repo dir (`chmod
o+x $DEPLOY_TARGET_DIR`) — the `deploy_website()` helper removed from the
framework (which has no website) and now consumer-owned. `cron-manifest` was
previously a every-host step; it is now the `worker` step, tag-gated like
`web`. `gen-env` and `gen-reuter` stay every-host steps.

Target selection is unchanged: no positional host → every `[prod]` host; one
positional host → that host. The wrapper keeps composing post-deploy (no
framework hook); its single-host restriction is a one-liner (first non-flag
arg), fixing the current "last non-flag wins" loop. The remote per-host step
is renamed `bin/deploy/server-side-post-deploy.sh` to make its execution
location explicit — `post-pf-deploy` hid that it runs **on the prod server**,
not locally.

## Config model

```
[prod]
# 10.147.x.10=db:simo, web, worker
```

- Value: comma-separated `tag[:name]` tokens; bare `tag` = boolean flag,
  `tag:name` = named resource. Empty value = code-only host (no tagged step).
- Uniqueness is per tag and applies to named tokens: a given `tag:name` appears
  on exactly one server (`db:simo` and `web:simo` are independent). Bare flags
  carry no name, hence no uniqueness constraint.
- `#`-prefixed lines (the templates' comment style) are ignored — PHP's
  `parse_ini_file` only skips `;` comments, so the shared `pf-roster` CLI
  filters them (commits `8caff75`).
- `[dev]` is unchanged (`hostname=dbuser`).

## Mechanism & data ownership

- Framework ships the code to all hosts (no tag logic in the ship); owns the
  `db` step (`pf-provision.sh` MariaDB + `gen-reuter`) and per-tag uniqueness
  via the shared `pf-roster` CLI (`--list`/`--db-servers`/`--validate`).
- Consumer owns tag meanings beyond `db` (`web`, `worker`, …) and its per-host
  server-side step (`bin/deploy/server-side-post-deploy.sh`), which runs the
  tag-gated steps from the `DEPLOY_TAGS` env list passed by the wrapper.
- `db` semantics unchanged: one MariaDB instance per host serving all its
  `db:` names; `gen-reuter` writes one `reuter.ini` section per `db:` name.
- `etc/machines.ini` is git-ignored (absent from the deployed repo), so the
  wrapper reads tags locally (via `vendor/bin/pf-roster --list`) and passes
  them to the remote step as `DEPLOY_TAGS`. `etc/deploy.conf` is git-tracked
  and ships with the repo, so the server-side step can source it.

## Changes

### simox (this repo)
- [x] `bin/deploy.sh` — replace the `wanted` `case` loop with a first-non-flag-arg
      one-liner; read each host's tags (host → tags via `vendor/bin/pf-roster
      --list`) and pass them to the server-side step as `DEPLOY_TAGS`.
- [x] `bin/deploy/post-pf-deploy.sh` → `bin/deploy/server-side-post-deploy.sh`
      (rename); add the `web` step (`chmod o+x "$DEPLOY_TARGET_DIR"`) gated on
      the `web` tag; move the cron install to a `worker` tag-gated step.
- [x] `composer.json` — pin `judijasa/php-daas-framework` to the roster commits
      (`8caff75`, incl. the `#`-comment fix); `composer update` regenerates
      `vendor/bin/pf-roster`.
- [x] `etc/machines.ini.template` — new `tag[:name]` `[prod]` format + comments.
- [x] `README.md` — machines.ini `[prod]` description (`ip=simo, analytics` →
      `tag[:name]`); `bin/deploy/post-pf-deploy.sh` →
      `bin/deploy/server-side-post-deploy.sh`.
- [x] `doc/plans/2026-08-31-deploy-step-tags.md` — this plan.

### php_daas_framework (../php_daas_framework)
- [x] `bin/pf-roster` (new) — shared roster CLI: `--list` (host=tags),
      `--db-servers` (name=host for `db:` tokens), `--validate` (per-tag
      uniqueness); skips `#`-commented lines. Composer `bin` entry.
- [x] `bin/pf-deploy.sh` — `read_prod_roster()`/`main` parse `tag[:name]` via
      `pf-roster --list`; `IS_DB_HOST` = host has a `db:` token; uniqueness via
      `pf-roster --validate`; header comments.
- [x] `bin/gen-reuter` — `db_servers()` collects `db:` names only (via
      `pf-roster --db-servers`).
- [x] `bin/pf-provision.sh` — header comment wording ("non-empty entry" →
      "db tag").
- [x] `etc/machines.ini.template` — new `tag[:name]` `[prod]` format + comments.
- [x] `etc/deploy.conf.template` — comment wording.
- [x] `README.md` — machines.ini `[prod]` references.
- [x] `doc/system/ema.md` — machines.ini `[prod]` references.

## Open items

- **Wrapper → remote step tag passing** — resolved: env `DEPLOY_TAGS`
  (comma-separated tag list) into `server-side-post-deploy.sh`, mirroring
  `DEPLOY_PROVISION_DB=1`.
- **Scope of existing config steps** — resolved: `gen-env` and `gen-reuter`
  stay every-host steps; the cron install (`cron-manifest`) becomes simox's
  `worker` step, tag-gated like `web`.
- **Roster reuse** — resolved: shared `pf-roster` CLI (generic tag queries),
  consumed by `pf-deploy.sh`, `gen-reuter` and the simox wrapper; delivered by
  Composer to `vendor/bin/pf-roster`.
- Framework repo is outside the write workspace — edit via shell (stage under
  a temp dir, then `cp`), as in `doc/plans/2026-08-14-deploy-relocation.md`.
  Landed as commits `b42fd4e` (roster) + `8caff75` (`#`-comment fix), pushed
  to origin/main.
- `DEPLOY_INIT_CMD` stays the only framework consumer hook; no
  `DEPLOY_POST_CMD` is added — the wrapper composes post-deploy.
- Migration: `etc/machines.ini` is git-ignored; existing instances must be
  hand-edited to the `tag[:name]` form (no automatic migration).
- Order of operations: framework changes land first, then `composer update` in
  simox, then the wrapper/template/docs (mirrors
  `doc/plans/2026-08-31-deploy-cli-rename.md`). Completed.
