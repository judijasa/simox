# Worker tag & cron deployment owned by the framework — Plan & Progress

Date: 2026-09-09
Repos: simox (this repo, consumer data + wrapper shrink);
       php_daas_framework (../php_daas_framework, upstream mechanism).

## Decision

Cron installation moves out of simox's deploy wrapper and becomes a framework
built-in: `pf-deploy.sh` now regenerates the git-ignored `.env` (gen-env) and
refreshes `/etc/simox/reuter.ini` (gen-reuter) on **every** prod host, and —
on hosts carrying the bare `worker` tag — installs the `cron-manifest` output
into `CRON_FILE` and restarts cron. `worker` becomes a framework built-in tag
next to `db` (bare flag; the `tag[:name]` grammar is unchanged). simox keeps
only its consumer-owned `web` step (Apache www-data traversal chmod) in
`bin/deploy/server-side-post-deploy.sh`.

This supersedes the consumer-owned `worker` step established by
`doc/plans/2026-08-31-deploy-step-tags.md` (that doc stays as the frozen
historical record of the earlier decision). simox now only carries the
`worker` token in `etc/machines.ini` and the `CRON_FILE`/`CRON_USER` values in
the committed `etc/deploy.conf`; the framework does the env + cron work on
every deploy.

See `php_daas_framework/doc/plans/2026-09-09-worker-cron-builtin.md` for the
framework-side mechanism (per-host order inside `deploy_to_host()`, local
`CRON_FILE` fail-fast, `CRON_NIX_BIN` default).

## Changes

### php_daas_framework (../php_daas_framework)

- [x] `bin/pf-deploy.sh` — built-in server steps after `DEPLOY_INIT_CMD`:
      gen-env + gen-reuter on every host, cron install on `worker` hosts;
      local + remote `CRON_FILE` guards. Landed as commit `cfd5728`,
      pushed to origin/main.
- [x] `bin/pf-roster` / `bin/cron-manifest` / `src/cron_manifest.php` —
      `worker` declared a built-in bare tag next to `db`; headers updated.
- [x] `etc/machines.ini.template`, `etc/deploy.conf.template`, `README.md`,
      `doc/system/ema.md` — ownership wording flipped (framework runs the
      env + cron steps; consumers keep only consumer-owned tags).
- [x] `doc/plans/2026-09-09-worker-cron-builtin.md` — mechanism plan doc.

### simox (this repo)

- [x] `composer.json` / `composer.lock` — pin bumped to
      `dev-main#cfd5728acd328d1faa5ee02376f559245f2c97ad`; `composer update`
      refreshed `vendor/` (framework CLIs now carry the built-in steps).
- [x] `bin/deploy.sh` — header: pf-deploy runs gen-env/gen-reuter on every
      host and cron install on `worker` hosts (framework built-ins); the
      wrapper's server-side step now covers only consumer-owned tags (`web`).
      Logic unchanged.
- [x] `bin/deploy/server-side-post-deploy.sh` — shrunk to the `web` step
      (`has_tag web` → `chmod o+x "$DEPLOY_TARGET_DIR"`); removed the
      gen-env/gen-reuter/cron blocks, the PATH/`CRON_NIX_BIN` exports and the
      `.env` sourcing; header rewritten (framework owns the env + cron steps).
- [x] `etc/deploy.conf` — `gen-env` comment names `pf-deploy`; `CRON_FILE`
      comment: installed by pf-deploy's worker step on every deploy, REQUIRED
      on `worker` hosts, unused elsewhere. Values unchanged.
- [x] `etc/machines.ini.template` — comment block: `db` (named) and `worker`
      (bare) are framework built-ins; `web` is simox's consumer-owned step.
- [x] `README.md` — machines.ini tags sentence, prod `.env` paragraph and
      deploy description: env regeneration, reuter.ini refresh and cron
      install happen inside `pf-deploy`; the post-deploy step covers only
      `web`.
- [x] `Makefile` — header + deploy-target comments reflect the new split
      (framework built-in steps; consumer post-deploy = `web`).
- [x] `doc/plans/2026-09-09-worker-cron-framework-owned.md` — this doc.

## Verification

- `composer update judijasa/php-daas-framework` — clean (1 update, no
  removals; PhantomJS already installed); `composer.lock` source/dist
  reference = `cfd5728acd328d1faa5ee02376f559245f2c97ad`; content-hash
  refreshed.
- `composer validate` — warnings only (commit-ref pins + sunra exact
  constraint, pre-existing conventions).
- `bash -n bin/deploy.sh bin/deploy/server-side-post-deploy.sh` — clean.
- `vendor/bin/pf-roster --list` / `--validate` — clean against the
  (empty-prod) `etc/machines.ini`.
- No live deploy (no prod roster yet) — the framework's built-in steps were
  sandbox-tested on the framework side (worker / plain / no-`CRON_FILE`
  cases) before the commit.

## Open items

- First real prod deploys are still pending: the private-config overlay
  (`simox_cnf` etc/machines.ini) must land on a host before `pf-deploy`'s
  built-in steps, so the `.env`/`reuter.ini`/cron sequence gets exercised on
  the first live host.
- `DEPLOY_TAGS` still travels host → server-side step even though only `web`
  remains — kept generic so a future simox-owned tag slots in without wrapper
  changes.
- `CRON_NIX_BIN` override (formerly exported by simox's wrapper) now defaults
  framework-side to `vendor/bin:nix-result-bin`; simox can still override via
  `etc/deploy.conf` if ever needed.
