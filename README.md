# SIMOExpress

## What it is

Collaborative platform that

1. Extracts job openings reported on the [SIMO](https://simo.cnsc.gov.co/) platform of the Colombian government.
2. Stores the job openings in a database.
3. Provides an online portal for job openings. Live at [http://192.159.99.50/](http://192.159.99.50/).


The application has three components: _crawler_ (indexer + pipeline), _database_, and _website_.

Built on [judijasa/php_daas_framework](https://github.com/judijasa/php_daas_framework) (PHP-based framework for collaborative data crawling projects) and [judijasa/ema](https://github.com/judijasa/ema) (MariaDB package manager) — two public repos by the same [author](https://github.com/judijasa).
## Quick Test

Test under the `nix develop` environment, which supplies the binaries (PHP + extensions, composer, MariaDB, bash, ...). The framework and `ema` code are Composer-delivered, not part of `flake.nix`.

```bash
nix develop
```

Initialize the developer environment (git hooks, log dirs, `composer install`, the private `etc/` files, `etc/hosts` sync, and `.env`):

```bash
make dev-init
```

Re-enter the shell (`nix develop` again) so the dev-shell bootstrap applies: `PATH`, the `.env` values (repo paths, `DBUSER`, `SSL_DIR`) and the `tmux` session alias. It comes from `vendor/bin/pf-shell-enter.sh`, a file `make dev-init` creates — the shell that ran it lacks all of it.

Create the `simo0` instance (schema `simo`) + dev sandbox (under `var/sandbox/`); `ema sandbox` builds and starts the isolated MariaDB instance:

```bash
ema sandbox srv/simo0-D03J4K6RM0K7X8E4
```

Run the indexer (`phprun` injects the DB connection from the `#[Agent]` attribute, so `main()` takes no explicit connection argument). The dev `.env` no longer pins `EMA_TARGET`, so scripts default to `prod` — prefix local runs with `EMA_TARGET=sandbox` to hit the sandbox instance:

```bash
EMA_TARGET=sandbox phprun 'src/scripts/simo/publico/indexer/main.php:main()'
```

The CNSC "Avisos informativos" indexer snapshots the amendments published on
`www.cnsc.gov.co` (the job offers themselves come from the SIMO indexer above).
It has two agents: `backfill()` dumps the whole archive (manual, resumable —
re-run it until it reports the archive fully consumed) and `update()` is the
incremental cron, which stops at the first page with no new node, so it only
catches avisos newer than what is already stored and cannot fill a gap left by
an unfinished backfill. A backfill stopped by its timeout logs the page it
stopped on; the optional `$start_page` resumes from there. That cursor is only
short-term-stable: the pager is newest-first, so newly published avisos push
stored content to higher pages (re-covered and deduped by node id), while a
deletion could shift content the other way and skip a node. Only a pass from
page 0 confirms the archive is consumed. Both agents write to
`cnsc_aviso_snapshot` in the same `simo0` instance:

```bash
EMA_TARGET=sandbox phprun 'src/scripts/cnsc/indexer/main.php:backfill()'
EMA_TARGET=sandbox phprun 'src/scripts/cnsc/indexer/main.php:backfill(start_page: 42)'
EMA_TARGET=sandbox phprun 'src/scripts/cnsc/indexer/main.php:update()'
```

The SIMO "historico" backfill dumps the whole historical archive
(`empleos/ofertaPublica/historico`, ~66k offers) into the same
`empleo_snapshot` as the publico indexer, tagged `source='historico'`. The
historico endpoint has no stable sort order, so a durable cross-run page cursor
cannot be trusted: `backfill()` pages the archive from 0 until the first empty
page and relies on the snapshot unique key to dedupe. It has no cron — run it
manually (and re-run) until a pass from page 0 reports 0 new rows. An aborted
pass logs the page it died on and flushes its pending batch; the optional
`$start_page` resumes from there, though only a pass from page 0 confirms the
archive is fully consumed. A page is retried twice (2s, then 4s) before the
pass aborts, since the endpoint intermittently answers 500:

```bash
EMA_TARGET=sandbox phprun 'src/scripts/simo/historico/indexer/main.php:backfill()'
EMA_TARGET=sandbox phprun 'src/scripts/simo/historico/indexer/main.php:backfill(200, 50, 86400, 213)'
EMA_TARGET=sandbox phprun 'src/scripts/simo/historico/indexer/main.php:backfill(start_page: 213)'
```

The SIMO "convocatoria" flag-updater classifies every convocatoria into the
current (`publicar_opec`) vs historical (`opec_historico`) split, using the
parent convocatoria's admin flags (the offer payload never carries them). It has
two agents: `backfill()` classifies everything once (manual, resumable) and
`update()` is the nightly cron (`0 4 * * *`) that re-checks current
convocatorias for `publicar_opec -> opec_historico` relocations, bulk-fetches
the relocated OPECs into `empleo_snapshot`, and denormalizes
`empleo.opec_historico` (the flag the website filters on to show only current
offers):

```bash
EMA_TARGET=sandbox phprun 'src/scripts/simo/convocatoria/main.php:backfill()'
EMA_TARGET=sandbox phprun 'src/scripts/simo/convocatoria/main.php:update()'
```

Access the local `simo0` instance (schema `simo`) and verify content:

```bash
EMA_TARGET=sandbox ema mdb simo0
SELECT count(*) FROM empleo_snapshot;
SELECT count(*) FROM cnsc_aviso_snapshot;
```

Run the pipeline and verify content:

```bash
EMA_TARGET=sandbox phprun 'src/scripts/simo/publico/pipeline/main.php:main()'
```

Start PHP's built-in server (from the repo root, inside `nix develop`; serves `public/` as docroot). The website defaults to `simo1` (prod's read-only replica), so for the local sandbox swap it to `simo0` — in `public/_table.php` and `public/insight.php` change `$dbname = 'simo1'` to `$dbname = 'simo0'` — then serve with `EMA_TARGET=sandbox`:

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

**1. php-fpm (web)** — installed by the deploy's `web` step; the web server and vhost (forwarding `.php` to the php-fpm socket) are consumer-owned. See [doc/system/web_setup.md](doc/system/web_setup.md).

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
