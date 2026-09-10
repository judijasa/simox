# SIMOExpress

## Esta aplicacion...
1. Extrae las ofertas de empleo reportadas en la plataforma SIMO del gobierno de Colombia.
2. Guarda las ofertas de empleo en una base de datos.
3. Ofrece un portal en línea para ofertas de empleo.

This application is comprised of three components: _crawler_, _database_ and _website_.

## Quick Test
This application can be easily tested under the `nix develop` environment, managing all dependencies and specified in the `nix.flake` file.
This requires the installation of Nix. Under this environment, execute from the repo root directory the following command:
```bash
nix develop
```

Initialize developer environment (install mariadb locally, etc):
```bash
make dev-init
```

Create the `simo` database + dev sandbox (git-ignored):
```bash
ema sandbox srv/simo-D03J4K6RM0K7X8E4
```


Run indexer:
```bash
phprun 'src/scripts/indexer/get_jobs.php:main($batch_size_limit=15, $jobs_per_page=5, $timeout=15)'
```

Access local `simo` database:
```bash
ema mariadb simo
```

Verify content in the empleo_snapshot:
```bash
SELECT count(*) FROM empleo_snapshot;
```

Run pipeline:
```bash
phprun 'src/scripts/pipeline/pipeline.php:main()'
```

Verify content in tables e.g.
```bash
SELECT * FROM convocatoria WHERE id = (SELECT convocatoria_id FROM empleo LIMIT 1) \G;
```

Start PHP's built-in server (from repo root directory):
```bash
php -S localhost:8000
```

Navigate to the website: `http://localhost:8000/public/index.php`

## Remote Access
To connect to a production server via `ema`, the machine registry config is needed:

- `etc/machines.ini` — copy from `etc/machines.ini.template` (git-ignored;
  commit it only in a private fork). The `[prod]` section lists the prod
  servers by ZeroTier IP; the value is a comma-separated list of
  `tag[:name]` tokens (`ip=db:simo, db:analytics, web, worker`): `db` (named)
  and `worker` (bare) are the framework's built-in tags — a `db:<name>` token
  is the advisory anchor for the framework's warn-only `db-check` (the
  instance itself is provisioned by `ema create`, not by deploy), and `worker`
  installs the `cron-manifest` output on every deploy — while `web` is
  simox's own step (restore Apache www-data traversal). Each named token maps
  to exactly one server; a server may host several databases. `pf-deploy.sh`
  targets every `[prod]` host by default; a server with a `db:<name>` token
  hosts one or more databases, each with its own MariaDB instance created by
  `ema create`.
- `etc/team.ini` — copy from `etc/team.ini.template` (git-ignored). One section per team member (the section name IS their DB username) with a `subject` key (their client-certificate subject DN) and `hostname=ZeroTier-IP` entries pinning their dev machine(s). `make dev-init` resolves your `DBUSER` from here (the section whose entries include your `hostname`) for remote DB access.
- `etc/hosts` — optional: maps ZeroTier hostnames to IPs (merged into `/etc/hosts` by `make dev-init`) if you prefer names over raw IPs. Copy from `etc/hosts.template` and add your server entries.
- `.private-source` — optional: instead of copying the `etc/*.template` files directly, keep `etc/machines.ini`, `etc/team.ini` and `etc/reuter.ini` in a private config repo and inject them via a git-ignored `.private-source` pointer (copy `.private-source.example`). The framework's `fetch-private-data` CLI (run by `pf-deploy.sh` and `init-local-env.sh`) symlinks them into `etc/` — see `doc/system/private-config.md`.

## Production Server Setup

One-time steps to provision a new production server. The app user
(`PROD_USER` in `etc/deploy.conf`) must exist with SSH access first —
see `_my_notes_/prod-user-setup.md` for the exact steps (create the user
with `useradd --create-home`, lock the password, install the SSH key):

**1. Apache vhost** — point the vhost at the deploy directory and set the config path:
```apache
DocumentRoot "/srv/apps/simox/public"
<Directory "/srv/apps/simox/public">
    Require all granted
</Directory>
SetEnv REUTER_INI /srv/apps/simox/etc/reuter.ini
```
`etc/reuter.ini` is git-ignored and injected as private data by the framework's
`fetch-private-data` (never regenerated — see `doc/system/private-config.md` and
the contract in `etc/reuter.ini.template`). The Apache vhost and `.env` point at
it via:

- **`REUTER_INI`** — path to the `reuter.ini` file (`/srv/apps/simox/etc/reuter.ini`,
  the `DEPLOY_REUTER_INI` value), consumed by the framework's `Database` class and
  the `ema` CLI (prod mode). If unset, `etc/reuter.ini` inside the repo is used.
- **`EMA_TARGET`** — operation-mode flag for the `ema` CLI only: `sandbox`
  (default, per-instance sandbox) or `prod` (reads
  `REUTER_INI`). The app layer ignores it; `Database.php` always resolves a
  database to its `[<dbname>]` section.

No `/etc/environment` entries are required: the framework's `phprun` CLI (shipped via Composer to `vendor/bin`) loads the `.env` file from the current working directory itself — no wrapper needed. The `.env` is generated per environment:

- **dev** — `make dev-init` runs `vendor/bin/init-local-env.sh` (shipped via Composer), which writes `.env` in the repo root with `REPO_PATH=$PWD`, `REPO_LOG=$PWD/var/log`, `REUTER_INI=$PWD/var/reuter.local.ini` and `EMA_TARGET=sandbox`.
- **prod** — every deploy regenerates `/srv/apps/simox/.env` via the
  framework `gen-env` CLI, run by the framework's `pf-deploy.sh` as a built-in
  per-host step; it projects it from the committed `etc/deploy.conf` (no
  separate `etc/env.prod`): `REPO_PATH=/srv/apps/simox`,
  `REPO_LOG=/var/log/simox`, `REUTER_INI=/srv/apps/simox/etc/reuter.ini` and
  `EMA_TARGET=prod`. The `.env` stays `MYSQL_*`-free — the socket lives in the
  `reuter.ini` section, not the environment. `reuter.ini` itself is a
  manually-maintained private file (values recorded from `ema create` output),
  injected into `etc/` by `fetch-private-data`; `gen-env` only projects its
  path (`DEPLOY_REUTER_INI`), never its contents. On the same per-host pass,
  `pf-deploy.sh` runs `db-check` (warn-only) to verify each `reuter.ini`
  section's TCP endpoint is reachable. `ema create srv/<name>-<GUID>` (run on
  the DB host) uses the section socket for root auth; the app (`Database.php`)
  reads `.env` and stays TCP. `gen-env` fails loudly if a required
  `deploy.conf` key is missing or a projected key is lost — a missing
  `EMA_TARGET=prod` would silently put the `ema` CLI in sandbox mode (the app
  layer ignores the variable and resolves `[<dbname>]` from `REUTER_INI`).

The production MariaDB instance is created by `ema create srv/<name>-<GUID>`
(run on the database host) — one instance per database, not provisioned by
deploy. `ema create` provisions the datadir/socket/pid/log under
`/var/lib/mariadb/<db>/`, the per-instance defaults file `/etc/<db>/my.cnf`,
and a `mariadb@<db>` systemd unit (enabled once, durable across reboots), then
prints the `[<dbname>]` connectivity values (`SERVER`/`PORT`/`DBMS`/
`MYSQL_UNIX_PORT`) to record in the manual `reuter.ini`. The instance listens
on TCP over ZeroTier so both the DB host and the app-only servers can serve
the website against the same database. `ema values <db>` recovers a lost
record. Never start `mysqld` manually in production; re-deploys leave running
instances untouched.

**2. Deploy** — run from the dev machine inside `nix develop`:
```bash
make deploy                        # every [prod] host in etc/machines.ini
make deploy <host>                 # a single prod host (must be in [prod])
```
`make deploy` runs the deploy entrypoint (`bin/deploy.sh`), which first
runs the framework `pf-deploy.sh` CLI (shipped via Composer to `vendor/bin`) and then
the per-host post-deploy step. On every deploy, the framework's generic
`vendor/bin/pf-provision.sh` runs on the remote (idempotently)
to assert the app user and create system directories (`/srv/apps`,
`/var/log/simox`), then the consumer-specific `DEPLOY_INIT_CMD`
(`bin/deploy/provision-extra.sh`: Apache www-data traversal).
The framework CLI also runs its built-in per-host steps — regenerating `.env`,
verifying DB connectivity via `db-check` (warn-only), and, on hosts tagged
`worker`, installing cron (`/etc/cron.d/simo-orchestrator`) from the
`#[CronJob]`/`#[Agent]` attributes. After it returns, the deploy entrypoint
runs `bin/deploy/server-side-post-deploy.sh` on each `[prod]` host (passing
that host's tag list via `DEPLOY_TAGS`); only simox's own `web` step remains
there — restoring Apache www-data traversal on the repo dir.

## Dependency Pinning

Both external packages — `judijasa/php-daas-framework` and `judijasa/ema` —
are delivered exclusively via Composer (single code delivery path). Each is
pinned to a known-good commit in `composer.json` using the `dev-main#<hash>`
form, recorded in `composer.lock`. There is no Nix-side pin to keep in step
anymore: `flake.nix` supplies only the environment binaries (php + extensions,
composer, mariadb, bash, tmux, jq), not the code of either package.

To bump a package to a newer upstream commit, update its `require` entry in
`composer.json` and refresh the lock:

```bash
composer require "judijasa/php-daas-framework:dev-main#<hash>" \
                 "judijasa/ema:dev-main#<hash>"
```

The `#<hash>` suffix tells Composer to resolve `dev-main` to that exact commit,
recorded in `composer.lock`. Subsequent `composer install` runs (including on
the production server) always fetch that revision.

### When to bump

- After validating a new upstream version locally (`nix develop` + full test run).
- Bump each package independently — the nix env rev and each Composer code rev
  are decoupled, so the two packages no longer have to agree with each other.
- Commit the updated `composer.json` and `composer.lock` so the pinned
  revisions are tracked in git.

## System Requirements
In addition to [composer](https://getcomposer.org/doc/01-basic-usage.md#introduction) and the programs in the `composer.json` file, we require

#### 1. Web Server (Ngnix, Apache, etc.)
#### 2. PHP >=8.4+
jakoch/phantomjs-installer further requires installation of the bz2 (`... install php-bz2`) extension for PHP.  It is also recommended to install cURL (`... install php-curl`).
#### 3. MariaDB Server >=10.6
#### 4. PHP/MySQL support modules for the Web Server
For example, `libapache2-mod-php` to integrate PHP with Apache2 and `php-mysql` to integrate PHP with MySQL/MariaDB.
#### 5. Python
Required during phpcasperjs/phpcasperjs installation (`...install python-is-python3`).
#### 6. libfontconfig.so.1
Required by the `phantomjs` binary (`... install libfontconfig1`).
#### 7. Nix (optional)
There is a shell.nix providing a Nix dev environment for local tests.

## PHP Casper Class
Scraping use to be the original approach to fetch data from the SIMO website. It has been superseded by
the use of the API endpoint. A minor role is still kept to showcase the use of crawling with Casper.
`Utils\Crawler\CasperTrio` (from the `judijasa/php-daas-framework` composer package, used by
`src/scripts/indexer/helpers.php`) is a subclass of `vendor/phpcasperjs/phpcasperjs/src/Casper.php:Casper`.
It overrides and defines new methods.  To use this subclass, after downloading the vendor libraries, the
`judijasa/php-daas-framework` composer plugin edits `vendor/phpcasperjs/phpcasperjs/src/Casper.php:Casper`,
replacing `private $script` with `protected $script` automatically on every
`composer install`/`composer update`.<br/>
An alternative is to edit `vendor/phpcasperjs/phpcasperjs/src/Casper.php:sendKeys()` to allow setting
of the boolean option `reset`, which is already defined in
`vendor/jerome-breton/casperjs/modules/casper.js:sendKeys()`

    Code:

    ```php
        /**
         *  @param string $selector
         *  @param string $input
         *  @param boolean $reset
         */
        public function sendKeys($selector, $input, $reset=false)
            {
                $jsonData = json_encode($input);

                $fragment = <<<FRAGMENT
        casper.then(function () {
                    this.sendKeys('$selector', $jsonData, { reset: $reset });
        });

        FRAGMENT;

                $this->script .= $fragment;

                return $this;
            }
    ```

    And define `vendor/phpcasperjs/phpcasperjs/src/Casper.php:fetchText()`

    Code:

    ```php
        /**
         *  @param string $selector
         */
        public function fetchText($selector)
            {
                $fragment = <<<FRAGMENT
        casper.then(function () {
                    this.echo(this.fetchText('$selector'));
        });

        FRAGMENT;

                $this->script .= $fragment;

                return $this;
            }
    ```

#### Notes

1.  There are other useful functions in PHP/CasperJS. See the links below.<br/>
    Code:<br/>
    [https://github.com/synackSA/casperjs-php/blob/master/src/Casper.php](https://github.com/synackSA/casperjs-php/blob/master/src/Casper.php)<br/>
    Basic usage:<br/>
    [https://github.com/synackSA/casperjs-php](https://github.com/synackSA/casperjs-php)

2.  casperjs' `sendKeys()` uses phantomjs' `sendEvent()`. Useful references about the latter:<br/>
    Documentation:
    [PHANTOMJS sendEvent](https://phantomjs.org/api/webpage/method/send-event.html)<br/>
    Code:<br/>
    [https://github.com/ariya/phantomjs/blob/master/src/webpage.cpp](https://github.com/ariya/phantomjs/blob/master/src/webpage.cpp)

3.  Another important section of code is `vendor/jerome-breton/casperjs/modules/clientutils.js:setField`,
    used in casperjs' `sendKeys()` method.
