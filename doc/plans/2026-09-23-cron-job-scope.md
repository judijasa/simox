# Cron job scope: maintenance on every host — Plan & Progress

Date: 2026-09-23
Repos: php_daas_framework (../php_daas_framework, upstream mechanism);
       simox (this repo, consumer data + attribute changes).

## Decision

The framework makes `#[CronJob]` carry a **required** `scope` argument (no
default; the value matches the full `tag[:name]` grammar plus a reserved
`host` = every host), and `cron-manifest` becomes scope-filtered per host and
fails loudly when `scope` is omitted. Mechanism details live in
`php_daas_framework/doc/plans/2026-09-23-cron-job-scope.md`; this doc tracks
only the consumer-side changes.

simox's three maintenance agents in `src/scripts/maintenance/maintenance.php`
are host-local (`dbTarget: null`; they act on local RAM, the local nix store,
and local `REPO_LOG`), so `trim_log_files` and `nix_store_gc` get
`scope: 'host'` and run on **every** prod host — resolving the gap where the
`web`/replica host (`simo1`) never runs `trim_log_files`/`nix_store_gc`, so
its `php-fpm.log` + `deploy_version.log` and nix store grow unbounded.
`memory_cleaning` gets `scope: 'cloud'`. The data jobs (`get_jobs`,
`pipeline`) get an explicit `scope: 'worker'` (their crons are still commented
out; the scope is added so they are correct when re-enabled).

Log scope: `php-fpm.log` (`/var/log/simox/php-fpm.log`) and
`deploy_version.log` already land in `REPO_LOG`; once maintenance runs on the
web host, `trim_log_files` covers both. Apache access/error logs stay under
`/var/log/apache2/*` and remain system-`logrotate`-managed — out of scope.

## Changes

### simox (this repo)

- [x] `composer.json` / `composer.lock` — bump `judijasa/php-daas-framework`
      pin to the commit that lands the framework scope mechanism.
- [x] `src/scripts/maintenance/maintenance.php` — add `scope: 'host'` to
      `trim_log_files` and `nix_store_gc`; add `scope: 'cloud'` to
      `memory_cleaning`.
- [x] `src/scripts/indexer/get_jobs.php` + `src/scripts/pipeline/pipeline.php`
      — add explicit `scope: 'worker'` to the commented-out `#[CronJob]`
      lines so they are correct when re-enabled.
- [x] `hooks/check-php-attributes.php` — extend to require `#[CronJob]`
      declares `scope` (mirrors the framework hook).
- [x] `etc/machines.ini.template` — comment block: `tag[:name]` tokens double
      as cron scopes (`worker`/`web`/`db:<name>`); `host`-scoped jobs run on
      every host.
- [x] `etc/deploy.conf.template` — `CRON_FILE` comment: installed on every
      host now (scope-filtered), required when the repo declares any
      `#[CronJob]`.
- [x] `bin/deploy.sh` — header wording: cron install is framework-owned and
      scope-filtered every-host (no longer `worker`-only).
- [x] `bin/deploy/server-side-post-deploy.sh` — header wording: same.
- [x] `README.md` — deploy description wording (cron = scope-filtered
      every-host, framework-owned).
- [x] `doc/plans/2026-09-23-cron-job-scope.md` — this doc.

## Open items

- **`memory_cleaning`** — kept now as `scope: 'cloud'` (best-effort
  kill-switch on `simo1`). The redesign is out of scope for the scope
  mechanism.
- Log trimming relies on `trim_log_files` running as `root` (cron runs as
  `CRON_USER=root`); `REPO_LOG` is `simox`-owned, so trimming is already
  privileged — unchanged.
- No live deploy until the framework mechanism lands and the pin is bumped.
