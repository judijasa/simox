# System requirements

In addition to [composer](https://getcomposer.org/doc/01-basic-usage.md#introduction) and the programs in the `composer.json` file, we require:

1. **Web server** (Nginx, Apache, etc.). Production forwards PHP to the
   nix-built `php-fpm` over FastCGI (see [web_setup.md](web_setup.md)) — not
   mod_php.
2. **PHP >= 8.4**. Production PHP comes from the nix closure (flake: `php84` +
   `mysqli`/`pdo_mysql`/`bz2`/`curl`), not from the OS package manager; the apt
   notes below apply only outside the nix environment.
   `jakoch/phantomjs-installer` further requires the `bz2` extension
   (`... install php-bz2`). cURL is also recommended (`... install php-curl`).
3. **MariaDB Server >= 10.6**.
4. **PHP/MySQL support modules for the web server**. With the nix php-fpm these
   are already compiled into the closure (`pdo_mysql`, `mysqli`). The legacy
   manual route used `libapache2-mod-php` to integrate PHP with Apache2 and
   `php-mysql` to integrate PHP with MySQL/MariaDB.
5. **Python**. Required during phpcasperjs/phpcasperjs installation
   (`... install python-is-python3`).
6. **libfontconfig.so.1**. Required by the `phantomjs` binary
   (`... install libfontconfig1`).
7. **Nix (optional)**. There is a `flake.nix` providing a Nix dev environment
   for local tests.
