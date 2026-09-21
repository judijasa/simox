# php-fpm for the web server — Plan & Progress

Date: 2026-09-20
Repos: simox (this repo)

## Decision

The nix closure (`prod-dependencies`) is already shipped to every `[prod]` host
and consumed via PATH by the CLI path (cron, composer, ema, mariadb). The one
gap is the website: the `web` host serves `public/index.php` through the
system `libapache2-mod-php`, a second, unpinned PHP that can drift from the
flake's PHP 8.4 + `ext-*` contract. Close that gap by pointing the web server
at the nix-built `php-fpm` (already in the closure) instead of mod_php.

php-fpm is a web-only SAPI daemon — it is *not* a general dependency-delivery
mechanism, so this change is scoped to the `web` tag. The `worker`/`db`/CLI
paths keep consuming the closure via PATH exactly as today. The framework is
untouched: this is consumer-owned, like the existing `web` traversal step.

Out of scope: the Apache vhost rewrite (mod_php → `proxy:fcgi`) stays a manual
one-time operator step (Apache provisioning is already manual — see README);
the deploy step installs/manages only the php-fpm pool config and systemd unit.

## Mechanism & data ownership

- Two committed templates — `etc/php-fpm-simox.conf.template` (pool config,
  `@REUTER_INI@`/`@PHP_FPM_LOG@` placeholders) and
  `etc/php-fpm-simox.service.template` (systemd unit, `@PHP_FPM_BIN@`
  placeholder). They are public config, shipped by `git archive` like the rest
  of the repo.
- The `web` step in `bin/deploy/server-side-post-deploy.sh` renders them with
  the replayed `etc/deploy.conf` values (`DEPLOY_REUTER_INI`, `DEPLOY_LOG_DIR`,
  `DEPLOY_NIX_RESULT_DIR` — all existing keys) into stable system paths
  (`/etc/simox/php-fpm-simox.conf`, `/etc/systemd/system/php-fpm-simox.service`),
  then `daemon-reload` + `enable` + `restart` the unit. The `result` symlink is
  already re-pointed and `reuter.ini` already shipped earlier in the same
  deploy, so restarting here picks up the fresh closure.
- `bin/deploy.sh` passes those three deploy values (plus the existing
  `DEPLOY_TAGS`) to the remote step, mirroring the framework's deploy.conf
  environment replay.
- The unit `ExecStart`s the *stable* `/usr/local/simox/result/bin/php-fpm` path
  (not the per-deploy store path), so the unit file never churns. php-fpm runs
  as `www-data` (same uid as the current Apache/mod_php process), so `reuter.ini`
  read access is unchanged. `REUTER_INI` moves from the Apache vhost `SetEnv`
  to the pool's `env[REUTER_INI]` (php-fpm does not inherit Apache `SetEnv`).

## Changes

### simox (this repo)

- [x] `etc/php-fpm-simox.conf.template` — new: `[global]` + `[simox]` pool
      (www-data, `/run/php-fpm-simox.sock`, `env[REUTER_INI]`), placeholders
      for the log and `REUTER_INI` paths.
- [x] `etc/php-fpm-simox.service.template` — new: systemd unit, foreground
      `php-fpm -F -y /etc/simox/php-fpm-simox.conf` from the nix `result` bin.
- [x] `bin/deploy/server-side-post-deploy.sh` — extend the `web` step: render +
      install the pool config and unit, then `daemon-reload`/`enable`/`restart`;
      accept the replayed deploy values (fail loudly if a `web` host lacks them).
- [x] `bin/deploy.sh` — `post_deploy_one` passes `DEPLOY_REUTER_INI`,
      `DEPLOY_LOG_DIR`, `DEPLOY_NIX_RESULT_DIR` to the remote step.
- [x] `etc/machines.ini.template` — `web` tag comment: now also installs the
      nix-built php-fpm (not just Apache traversal).
- [x] `README.md` — Apache vhost block (proxy:fcgi, drop `SetEnv REUTER_INI`),
      web-step description, System Requirements wording.
- [x] `doc/system/web_setup.md` — production web config rewritten to php-fpm +
      `proxy:fcgi` (modules, socket, vhost).
- [x] `doc/plans/2026-09-20-php-fpm-web-server.md` — this plan.

## Open items

- Apache module/vhost switch (`a2enmod proxy proxy_fcgi`, disable mod_php,
  rewrite vhost to `SetHandler proxy:unix:/run/php-fpm-simox.sock|fcgi://localhost`)
  is manual on the live `web` host — not managed by deploy (Apache provisioning
  stays out of scope). Do this after the first deploy that installs the unit.
- Pool `pm.*` sizing is a first guess (`dynamic`, max 20); tune from real load
  if needed.
- Local php-fpm config validation uses the built closure's `php-fpm -t`, but the
  `www-data`/`/var/log/simox` paths are prod-only; syntax is checked, not the
  prod socket ownership.
