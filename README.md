# SIMOExpress

1. Extracts the job openings reported on the SIMO platform of the Colombian government.
2. Stores the job openings in a database.
3. Provides an online portal for job openings.

The application has three components: _crawler_ (indexer + pipeline), _database_, and _website_.

Built on [judijasa/php_daas_framework](https://github.com/judijasa/php_daas_framework) (the underlying framework) and [judijasa/ema](https://github.com/judijasa/ema) (database & deploy tooling) — two public repos by the same [author](https://github.com/judijasa). Live at [http://192.159.99.50/](http://192.159.99.50/).

## Quick Test

Test under the `nix develop` environment, which supplies the binaries (PHP + extensions, composer, MariaDB, bash, ...). The framework and `ema` code are Composer-delivered, not part of `flake.nix`.

```bash
nix develop
```

Initialize the developer environment (git hooks, log dirs, `composer install`, the private `etc/` files, `etc/hosts` sync, and `.env`):

```bash
make dev-init
```

Re-enter the shell (or `source .env`) so the repo paths, `DBUSER` and `SSL_DIR` written to `.env` are in scope.

Create the `simo0` instance (schema `simo`) + dev sandbox (under `var/sandbox/`); `ema sandbox` builds and starts the isolated MariaDB instance:

```bash
ema sandbox srv/simo0-D03J4K6RM0K7X8E4
```

Run the indexer (`phprun` injects the DB connection from the `#[Agent]` attribute, so `main()` takes no explicit connection argument). The dev `.env` no longer pins `EMA_TARGET`, so scripts default to `prod` — prefix local runs with `EMA_TARGET=sandbox` to hit the sandbox instance:

```bash
EMA_TARGET=sandbox phprun 'src/scripts/simo/indexer/main.php:main()'
```

Access the local `simo0` instance (schema `simo`) and verify content:

```bash
EMA_TARGET=sandbox ema mdb simo0
SELECT count(*) FROM empleo_snapshot;
```

Run the pipeline and verify content:

```bash
EMA_TARGET=sandbox phprun 'src/scripts/simo/pipeline/main.php:main()'
```

Start PHP's built-in server (from the repo root, inside `nix develop`; serves `public/` as docroot). The website defaults to `simo1` (prod's read-only replica), so for the local sandbox swap it to `simo0` — in `public/index.php` and `public/insight.php` change `$dbname = 'simo1'` to `$dbname = 'simo0'` — then serve with `EMA_TARGET=sandbox`:

```bash
EMA_TARGET=sandbox make web
```

Navigate to `http://localhost:8000`.

> In prod the website reads `simo1`, the read-only replica built with ema's replica flow (see [doc/system/replica-bootstrap.md](doc/system/replica-bootstrap.md)). The `simo1` → `simo0` swap above is a quick-setup convenience for the local sandbox only.

## Remote Access

Connecting to a production server via `ema` needs the machine registry config, all private data materialized by `make dev-init` from the `.private-source` repo (copy `.private-source.example`, set `PRIVATE_DATA_GIT`). The files, their roles, and the materialization/shipping are documented in [doc/system/private-config.md](doc/system/private-config.md); the roster semantics (tags, cron scope, role pins) are in [doc/system/deploy.md](doc/system/deploy.md).

`make dev-init` also generates `~/.ssh/config.d/<repo-dir>.conf` from `etc/hosts`, so dev machines reach prod servers as `root` with one project key:

```
ssh simox-<name>        # e.g. ssh simox-simo0
```

The file is generated and idempotent — never hand-edit it; edit `etc/hosts` and re-run `make dev-init`. Details in [doc/system/deploy.md](doc/system/deploy.md).

A machine that must connect to a production database also needs its own TLS client certificate, since the service accounts require X509. That is a separate, occasionally-run step (the certificate is signed by the project CA, which only the operator holds) — see [doc/system/machine-certs.md](doc/system/machine-certs.md).

## Public repo, private config (human-in-the-loop)

I want this repository to remain public and reusable, while it is in production and under
continuous development. For this reason, the deployment's real values never appear here,
only templates or default values.
The real values live in a separate private repo (`.private-source` → `PRIVATE_DATA_GIT`),
are materialized into `etc/` at deploy time, and are never committed.

Because the private values sit outside this repo's history, two points in the flow
depend on an operator rather than on git:

- **Materialization — `bin/fetch-private-data`.** Runs automatically on every
  `nix develop` entry (and before every deploy), keeping `etc/` fresh; it copies
  whatever the private repo actually provides. The operator only sets the
  `.private-source` pointer once.
- **Deploy-time confirmation.** When a private file the deploy would otherwise ship is
  absent, the deploy asks the operator to confirm: intentional or a missed materialization.

The alternative — keeping both a public and a private fork — would leave the public (or
private) fork vulnerable to drift.

## Production Server Setup

**1. Apache vhost + php-fpm** — one-time manual steps (vhost + FastCGI to the nix-built php-fpm). See [doc/system/web_setup.md](doc/system/web_setup.md).

**2. TLS cert material** — install the project CA and the host's client certificate under `/etc/<app>/ssl` (out of band, before the accounts are reconciled with `REQUIRE X509`). See [doc/system/machine-certs.md](doc/system/machine-certs.md).

**3. Cron daemon** — install a cron daemon on each prod host. The deploy's cron step writes the `#[CronJob]` jobs and restarts the daemon; the package/service name is distro-specific (`cron`, `crond`, or `cronie` depending on the OS).

**4. Deploy** — run from the dev machine inside `nix develop`:

```bash
deploy all                         # every prod host in etc/machines.ini
deploy <host>                      # a single prod host (short name or ZeroTier IP)
```

`deploy` materializes the private config, runs the framework `deploy` (ships `reuter.ini`, replays `deploy.conf`, regenerates `.env`, verifies DB connectivity, installs cron), then the per-host post-deploy step (Apache www-data traversal + nix-built php-fpm). The full flow, plus MariaDB instance provisioning and the `.env`/`REUTER_INI`/`SSL_DIR`/`EMA_TARGET` contract, is in [doc/system/deploy.md](doc/system/deploy.md).

## Adding a Database

Adding a database — its own MariaDB instance on its own host (`ema create`), its packages, its `reuter.ini` record, its users/grants — is in [doc/system/add-database.md](doc/system/add-database.md).

## Service Accounts & Read Replica

MariaDB users/grants are declared in the shared `pkg/roles-<GUID>` package and the per-instance `pkg/<db>.roles-<GUID>` grant packages, reconciled by the framework's `gen-service-accounts` CLI, run from the dev machine: it plans from the private roster here and applies on the database's host as `root` over ssh. A single passwordless account, gated by a TLS client certificate: the security boundary is ZeroTier membership, the source-IP host pin, and `REQUIRE X509` with a certificate signed by the project CA. See [doc/system/service-accounts.md](doc/system/service-accounts.md) for the role/source table, the authentication layers and routing, [doc/system/machine-certs.md](doc/system/machine-certs.md) for the certificates, and [doc/system/replica-bootstrap.md](doc/system/replica-bootstrap.md) for the `simo1` read-replica build.

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
