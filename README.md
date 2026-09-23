# SIMOExpress

1. Extracts the job openings reported on the SIMO platform of the Colombian government.
2. Stores the job openings in a database.
3. Provides an online portal for job openings.

The application has three components: _crawler_ (indexer + pipeline), _database_, and _website_.

## Quick Test

Test under the `nix develop` environment, which supplies the binaries (PHP + extensions, composer, MariaDB, bash, ...). The framework and `ema` code are Composer-delivered, not part of `flake.nix`.

```bash
nix develop
```

Initialize the developer environment (git hooks, log dirs, `composer install`, the private `etc/` files, `etc/hosts` sync, and `.env`):

```bash
make dev-init
```

Re-enter the shell (or `source .env`) so the generated repo paths and `DBUSER` are in scope.

Create the `simo0` database + dev sandbox (under `var/sandbox/`); `ema sandbox` builds and starts the isolated MariaDB instance:

```bash
ema sandbox srv/simo0-D03J4K6RM0K7X8E4
```

Run the indexer (`phprun` injects the DB connection from the `#[Agent]` attribute, so `main()` takes no explicit connection argument):

```bash
phprun 'src/scripts/indexer/get_jobs.php:main()'
```

Access the local `simo0` database and verify content:

```bash
ema mariadb simo0
SELECT count(*) FROM empleo_snapshot;
```

Run the pipeline and verify content:

```bash
phprun 'src/scripts/pipeline/pipeline.php:main()'
```

Start PHP's built-in server (from the repo root, inside `nix develop`; serves `public/` as docroot):

```bash
make web
```

Navigate to `http://localhost:8000`.

> The website (`public/index.php`, `public/insight.php`) reads from the read-only replica `simo1`, not `simo0`. `simo1` is built with ema's replica flow, not as a dev sandbox — see [doc/system/replica-bootstrap.md](doc/system/replica-bootstrap.md).

## Remote Access

Connecting to a production server via `ema` needs the machine registry config, all private data materialized by `make dev-init` from the `.private-source` repo (copy `.private-source.example`, set `PRIVATE_DATA_GIT`). The files, their roles, and the materialization/shipping are documented in [doc/system/private-config.md](doc/system/private-config.md); the roster semantics (tags, cron scope, role pins) are in [doc/system/deploy.md](doc/system/deploy.md).

`make dev-init` also generates `~/.ssh/config.d/<repo-dir>.conf` from `etc/hosts`, so dev machines reach prod servers as `root` with one project key:

```
ssh simox-<name>        # e.g. ssh simox-simo0
```

The file is generated and idempotent — never hand-edit it; edit `etc/hosts` and re-run `make dev-init`. Details in [doc/system/deploy.md](doc/system/deploy.md).

## Production Server Setup

**1. Apache vhost + php-fpm** — one-time manual steps (vhost + FastCGI to the nix-built php-fpm). See [doc/system/web_setup.md](doc/system/web_setup.md).

**2. Deploy** — run from the dev machine inside `nix develop`:

```bash
make deploy                        # every [prod] host in etc/machines.ini
make deploy <host>                 # a single prod host
```

`make deploy` materializes the private config, runs the framework `pf-deploy.sh` (ships `reuter.ini`, replays `deploy.conf`, regenerates `.env`, verifies DB connectivity, installs cron), then the per-host post-deploy step (Apache www-data traversal + nix-built php-fpm). The full flow, plus MariaDB instance provisioning and the `.env`/`REUTER_INI`/`EMA_TARGET` contract, is in [doc/system/deploy.md](doc/system/deploy.md).

## Service Accounts & Read Replica

MariaDB users/grants are declared in the shared `srv/roles-<GUID>` package and the per-database `srv/<db>.roles-<GUID>` grant packages, reconciled by the framework's `gen-service-accounts` CLI. A single passwordless account; the security boundary is ZeroTier membership plus the source-IP host pin. See [doc/system/service-accounts.md](doc/system/service-accounts.md) for the role/source table and routing, and [doc/system/replica-bootstrap.md](doc/system/replica-bootstrap.md) for the `simo1` read-replica build.

## Dependencies

`judijasa/php-daas-framework` and `judijasa/ema` are delivered via Composer and pinned to known-good commits in `composer.json`; `flake.nix` supplies only the environment binaries. See [doc/system/composer.md](doc/system/composer.md) for the `composer.json` layout and how to bump the pins.

## System Requirements

- PHP >= 8.4 (from the nix closure in production).
- MariaDB >= 10.6.
- Composer.
- A web server forwarding PHP to the nix-built php-fpm over FastCGI (not mod_php).
- Nix (optional) for the dev environment.

The full list (incl. legacy CasperJS/phantomjs dependencies) is in [doc/system/system-requirements.md](doc/system/system-requirements.md).

## PHP Casper Class

Scraping was the original approach to fetch data from the SIMO website; it has been superseded by the API endpoint, kept only to showcase crawling. See [doc/system/casperjs.md](doc/system/casperjs.md).
