# Deploy & production setup

How production servers are provisioned and how `make deploy` ships the app.
Complements [private-config.md](private-config.md) (what the private files are
and how they materialize/ship) and [web_setup.md](web_setup.md) (the web
server/php-fpm configuration).

## Machine roster

Connecting to a production server via `ema` needs the machine registry config:

- `etc/machines.ini` — the `[prod]` roster (private data; see
  [private-config.md](private-config.md)). The `[prod]` section lists the prod
  servers by ZeroTier IP; the value is a comma-separated list of `tag[:name]`
  tokens (`ip=db:simo0, db:simo1, web, worker`). `db` (named) is the
  framework's built-in tag — a `db:<name>` token names a database (the instance
  itself is provisioned by `ema create`, not by deploy, and `db-check` verifies
  it via the host's own `mariadb@*` units). Every tag doubles as a cron scope:
  a `#[CronJob]` declares `scope: <tag[:name]>` (or `scope: host` to run on
  every host), and the cron install is scope-filtered per host. `worker` is an
  ordinary bare tag data jobs use as their scope; `web` is simox's own step
  (restore Apache www-data traversal). `worker`, `web` and every `db:<name>`
  token are the role-pin sources `gen-service-accounts` reconciles against the
  shared `srv/roles-<GUID>` declaration (`worker` → `simox_worker`,
  `db:<name>` → `simox_db_<name>`, `web` → `simox_web`); see
  [service-accounts.md](service-accounts.md). Each named token maps to exactly
  one server; a server may host several databases. `pf-deploy.sh` targets every
  `[prod]` host by default; a server with a `db:<name>` token hosts one or more
  databases, each with its own MariaDB instance created by `ema create`.
- `etc/team.ini` — private data. One section per team member with a `subject`
  key (their client-certificate subject DN, used for cert issuance) and
  `hostname=ZeroTier-IP` entries. There is no longer one DB account per member:
  every member IP is a pin for the single shared `simox` writer account (the
  `member` source, mapped to the `simox_member` role in `srv/roles-<GUID>`).
  `make dev-init` resolves your `DBUSER` from here (the section whose entries
  include your `hostname`) for remote DB access.
- `etc/hosts` — optional: maps production server names to IPs (prod servers
  only — member machines are not listed, there is no member ssh). Private data.
  It is the single source for two dev-machine conveniences: the `/etc/hosts`
  merge (so the names work under `ema` and raw `ssh`/`scp`) and the generated
  ssh config described below.
- `.private-source` — a pointer to the private config repo (copy
  `.private-source.example`, set `PRIVATE_DATA_GIT`). `bin/fetch-private-data`
  (run by `make dev-init`) copies `etc/machines.ini`, `etc/team.ini`,
  `etc/reuter.ini`, `etc/hosts` and `etc/host-hardening.php` into `etc/` as real
  files. See [private-config.md](private-config.md).

## Dev ssh config

`make dev-init` generates `~/.ssh/config.d/<repo-dir>.conf` from `etc/hosts`
(the framework `gen-ssh-config` CLI), so dev machines reach the prod servers as
`root` with one project key instead of hand-edited aliases. The alias prefix is
the repo directory name, derived by `gen-ssh-config` from the directory it runs
in — distinctive per repo, never typed. For this checkout (`simox`):

```
ssh simox-<name>        # e.g. ssh simox-simo0
```

Each `etc/hosts` entry becomes one `Host simox-<name>` block (`HostName <ip>`,
`User root`, `IdentityFile ~/.ssh/simox-sshkey`, `IdentitiesOnly yes`). The
`simox-` prefix is hard isolation: several projects sharing the same
`~/.ssh/config.d/*.conf` drop-in — possibly against the same server IP — cannot
collide on alias names. The public half of `~/.ssh/simox-sshkey` must be
authorized in root's `authorized_keys` on each prod host. The file is generated
and idempotent: never hand-edit it, edit `etc/hosts` and re-run `make dev-init`.

## Provisioning a new production server

One-time steps. The app user (`PROD_USER` in `etc/deploy.conf`) must exist with
SSH access first (create the user with `useradd --create-home`, lock the
password, install the SSH key).

**1. Apache vhost + php-fpm** — see [web_setup.md](web_setup.md).

**2. Deploy** — run from the dev machine inside `nix develop`:

```bash
make deploy                        # every [prod] host in etc/machines.ini
make deploy <host>                 # a single prod host (must be in [prod])
```

`make deploy` runs the deploy entrypoint (`bin/deploy.sh`), which first runs
simox's own private-config materialization (`bin/fetch-private-data` copies the
real `etc/` files in locally), then the framework `pf-deploy.sh` CLI (shipped
via Composer to `vendor/bin`) — which ships `DEPLOY_PRIVATE_FILES`
(`reuter.ini`) to each target host and replays the `deploy.conf` environment to
every remote step — and finally the per-host post-deploy step. On every deploy,
the framework's generic `vendor/bin/pf-provision.sh` runs on the remote
(idempotently) to assert the app user and create system directories
(`/srv/apps`, `/var/log/simox`), then the consumer-specific `DEPLOY_INIT_CMD`
(`bin/deploy/provision-extra.sh`: Apache www-data traversal).

The framework CLI also runs its built-in per-host steps — regenerating `.env`,
verifying DB connectivity via `db-check` (warn-only), and installing cron
(`/etc/cron.d/simo-orchestrator`) from the `#[CronJob]`/`#[Agent]` attributes
on every host, scope-filtered by that host's tag list. After it returns, the
deploy entrypoint runs `bin/deploy/server-side-post-deploy.sh` on each `[prod]`
host (passing that host's tag list via `DEPLOY_TAGS` plus the `deploy.conf`
values the render needs); only simox's own `web` step remains there — restoring
Apache www-data traversal on the repo dir and installing the nix-built php-fpm
(pool config + systemd unit, then restarting the service).

## Environment contract (`REUTER_INI` / `EMA_TARGET` / `.env`)

`REUTER_INI` is no longer a vhost `SetEnv` — php-fpm does not inherit Apache
`SetEnv`; the php-fpm pool sets it instead (see [web_setup.md](web_setup.md)).
`etc/reuter.ini` is private data, materialized by simox's
`bin/fetch-private-data` and never regenerated (see
[private-config.md](private-config.md) and the contract in
`etc/reuter.ini.template`). The php-fpm pool and `.env` point at it via:

- **`REUTER_INI`** — path to the `reuter.ini` file
  (`/srv/apps/simox/etc/reuter.ini`, the `DEPLOY_REUTER_INI` value), consumed by
  the framework's `Database` class and the `ema` CLI (prod mode). The `web`
  deploy step writes it into the php-fpm pool (`env[REUTER_INI]`), since the web
  process has no `.env`. If unset, `etc/reuter.ini` inside the repo is used.
- **`EMA_TARGET`** — operation-mode flag for the `ema` CLI only: `sandbox`
  (default, per-instance sandbox) or `prod` (reads `REUTER_INI`). The app layer
  ignores it; `Database.php` always resolves a database to its `[<dbname>]`
  section.

No `/etc/environment` entries are required: the framework's `phprun` CLI
(shipped via Composer to `vendor/bin`) loads the `.env` file from the current
working directory itself — no wrapper needed. The `.env` is generated per
environment:

- **dev** — `make dev-init` runs `vendor/bin/init-local-env.sh` (shipped via
  Composer), which writes `.env` in the repo root with `REPO_PATH=$PWD`,
  `REPO_LOG=$PWD/var/log`, `REUTER_INI=$PWD/var/reuter.local.ini` and
  `EMA_TARGET=sandbox`.
- **prod** — every deploy regenerates `/srv/apps/simox/.env` via the framework
  `gen-env` CLI, run by `pf-deploy.sh` as a built-in per-host step; it projects
  it from the replayed `deploy.conf` environment (no separate `etc/env.prod`):
  `REPO_PATH=/srv/apps/simox`, `REPO_LOG=/var/log/simox`,
  `REUTER_INI=/srv/apps/simox/etc/reuter.ini` and `EMA_TARGET=prod`. The `.env`
  stays `MYSQL_*`-free — the socket lives in the `reuter.ini` section, not the
  environment. `reuter.ini` itself is a manually-maintained private file
  (values recorded from `ema create` output), shipped (whole) into `etc/` by the
  framework's `DEPLOY_PRIVATE_FILES` key; `gen-env` only projects its path
  (`DEPLOY_REUTER_INI`), never its contents. On the same per-host pass,
  `pf-deploy.sh` runs `db-check` (warn-only) to verify the host's own
  `mariadb@*` instances are up and each `reuter.ini` section's TCP endpoint is
  reachable. `ema create srv/<name>-<GUID>` (run on the DB host) uses the
  section socket for root auth; the app (`Database.php`) reads `.env` and stays
  TCP. `gen-env` fails loudly if a required `deploy.conf` key is missing or a
  projected key is lost — a missing `EMA_TARGET=prod` would silently put the
  `ema` CLI in sandbox mode (the app layer ignores the variable and resolves
  `[<dbname>]` from `REUTER_INI`).

## MariaDB instance provisioning

The production MariaDB instance is created by `ema create srv/<name>-<GUID>`
(run on the database host) — one instance per database, not provisioned by
deploy. `ema create` provisions the datadir/socket/pid/log under
`/var/lib/mariadb/<db>/`, the per-instance defaults file `/etc/<db>/my.cnf`,
and a `mariadb@<db>` systemd unit (enabled once, durable across reboots), then
prints the `[<dbname>]` connectivity values (`SERVER`/`PORT`/`DBMS`/
`MYSQL_UNIX_PORT`) to record in the manual `reuter.ini`. The instance listens
on TCP over ZeroTier so both the DB host and the app-only servers can serve the
website against the same database. `ema values <db>` recovers a lost record.
Never start `mysqld` manually in production; re-deploys leave running instances
untouched.
