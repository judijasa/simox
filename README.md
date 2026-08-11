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

Create the database config from the template and fill in your values:
```bash
cp etc/reuter.ini.template etc/reuter.ini
```

`ema` is a MariaDB package manager. Build `simo` database locally and build tables from root package:
```bash
ema init db simo
ema init tables simo-C196A24801D24B16
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
To connect to a production server via `ema`, two additional config files are needed:

- `etc/dev-machines.ini` — maps your machine hostname to your prod DB username (`hostname=dbuser`). Run `hostname` to find yours. When set, `ema` uses it as the default DB user for remote connections; otherwise you must set `DBUSER` explicitly. Copy from `etc/dev-machines.ini.template`. In a private fork, this file (once removed from `.gitignore`) can also serve as a central team registry: all members' entries committed together so that `ema init db` creates each member's DB user and privileges in one shot. The latter functionality is not yet featured by ema.
- `etc/hosts` — maps prod server hostnames to IP addresses (merged into `/etc/hosts` by `make dev-init`). Copy from `etc/hosts.template` and add your server entries.

## Production Server Setup

One-time steps to provision a new production server:

**1. Apache vhost** — point the vhost at the deploy directory and set the config path:
```apache
DocumentRoot "/srv/apps/simox/public"
<Directory "/srv/apps/simox/public">
    Require all granted
</Directory>
SetEnv REUTER_INI /etc/simox/reuter.ini
```
Config files inside the repo (e.g. `etc/reuter.ini`) are gitignored but can be overwritten by `git clean`. Store production config outside the repo and point to it via:

- **`REUTER_INI`** — path to the `reuter.ini` file (e.g. `/etc/simox/reuter.ini`), consumed by the framework's `Database` class. If unset, `etc/reuter.ini` inside the repo is used (CLI only).
- **`EMA_TARGET`** — selects which section of `reuter.ini` to use (`prod` or `local`). Defaults to `local`.

No `/etc/environment` entries are required: the framework's `phprun` CLI (shipped via the flake's `phpDaasFrameworkPkg`) loads the `.env` file from the current working directory itself — no wrapper needed. The `.env` is generated per environment:

- **dev** — `make dev-init` runs `bin/dev/init-local-env.sh`, which writes `.env` in the repo root with `REPO_PATH=$PWD`, `REPO_LOG=$PWD/var/log`, `REUTER_INI=$PWD/etc/reuter.ini` and `EMA_TARGET=local`.
- **prod** — every deploy regenerates `/srv/apps/simox/.env` with `REPO_PATH=/srv/apps/simox`, `REPO_LOG=/var/log/simox`, `REUTER_INI=/etc/simox/reuter.ini` and `EMA_TARGET=prod` (via `bin/prod/gen-env.sh`, which fails loudly if the file would be incomplete — a missing `EMA_TARGET=prod` would silently route cron jobs to the wrong `reuter.ini` section).

**2. Initial deploy** — run from the dev machine inside `nix develop`:
```bash
bin/deploy.sh --init <host>
```
`--init` provisions system directories (`/srv/apps`, `/var/log/simox`, `/var/lib/simox/mariadb`) in addition to deploying the repo.

## Dependency Pinning

The external repo `php_daas_framework` is integrated via both Nix (`flake.nix`) and Composer (`composer.json`). By default, both track the tip of the `main` branch, which is convenient during active co-development but dangerous for production: a breaking upstream change can silently enter the next deploy.

To lock the integration to a known-good commit:

### 1. flake.nix

Replace the floating URL in the `inputs` section:

```nix
# Before (floating — tracks HEAD of main):
php_daas_framework.url = "github:judijasa/php_daas_framework";

# After (pinned to commit abc1234):
php_daas_framework.url = "github:judijasa/php_daas_framework?rev=abc1234";
```

After changing the URL, update the lock file:

```bash
nix flake lock --update-input php_daas_framework
```

This bakes the exact revision into `flake.lock`, so every `nix build` / `nix develop` — whether locally, in CI, or on the production server — pulls the identical source tree.

### 2. composer.json

Pin the commit hash in the `require` field:

```json
// Before (floating — tracks tip of dev-main):
"judijasa/php-daas-framework": "dev-main"

// After (pinned to commit abc1234):
"judijasa/php-daas-framework": "dev-main#abc1234"
```

Then refresh the lock file:

```bash
composer update judijasa/php-daas-framework
```

The `#<hash>` suffix tells Composer to resolve `dev-main` to that exact commit, recorded in `composer.lock`. Subsequent `composer install` runs (including on the production server) will always fetch that revision.

### When to bump

- After validating a new upstream version locally (`nix develop` + full test run).
- Always bump both files together so Nix and Composer agree on the same commit.
- Commit the updated `flake.lock` and `composer.lock` so the pinned revision is tracked in git.

## System Requirements
In addition to [composer](https://getcomposer.org/doc/01-basic-usage.md#introduction) and the programs in the `composer.json` file, we require

#### 1. Web Server (Ngnix, Apache, etc.)
#### 2. PHP >=8.4+
jakoch/phantomjs-installer further requires installation of the bz2 (`... install php-bz2`) extension for PHP.  It is also recommended to install cURL (`... install php-curl`).
#### 3. MariaDB Server >=10.6
#### 4. PHP/MySQL support modules for the Web Server
For example, `libapache2-mod-php` to integrate PHP with Apache2 and `php-mysql` to integrate PHP with MySQL/MariaDB.
#### 5. jq - commandline JSON processor [version 1.6]
Used in `src/init/tables.sh` to convert json to array in BASH.
#### 6. Python
Required during phpcasperjs/phpcasperjs installation (`...install python-is-python3`).
#### 7. libfontconfig.so.1
Required by the `phantomjs` binary (`... install libfontconfig1`).
#### 8. Nix (optional)
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
