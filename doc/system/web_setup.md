# Web service configuration

In production the site is served by a global system web server (Apache) that
forwards PHP to the nix-built `php-fpm` over FastCGI — it does **not** run PHP
as an Apache module (`mod_php`). The deploy's `web` step installs and manages
the php-fpm pool config (`/etc/<app>/php-fpm-<app>.conf`) and systemd unit
(`php-fpm-<app>.service`) and starts the service. The Apache install and the
`mod_php` → php-fpm switch below are one-time manual steps (run as root). The
vhost is **not** written by hand: it is a consumer-owned template, rendered from
`deploy.conf` and installed by a consumer-owned reconcile script (see "Vhost").

## Quick setup

```bash
# on the host, as root (one-time)
apt-get update && apt-get install -y apache2
a2enmod proxy proxy_fcgi
apache2ctl -M 2>/dev/null | grep -i php      # if mod_php appears: a2dismod php8.4

# after the first deploy (DocumentRoot exists only then), from the deploy
# machine: run the consumer's vhost reconcile script — it renders the vhost
# from deploy.conf, installs it, enables the site, and reloads Apache.
```

## Install Apache

```bash
apt-get update
apt-get install -y apache2
```

Do **not** install `libapache2-mod-php` — PHP runs in php-fpm, not as an
Apache module.

## Switch PHP handling to php-fpm

Enable the FastCGI proxy modules:

```bash
a2enmod proxy proxy_fcgi
```

Then check for `mod_php` — the distro's PHP as an Apache module, a second,
unpinned PHP that would handle `.php` itself and win over the vhost's FastCGI
handler:

```bash
apache2ctl -M 2>/dev/null | grep -i php   # enabled module, if any
ls /etc/apache2/mods-available/php*.load  # what the distro has
```

No output from either means `mod_php` is absent and this step is already done.
Otherwise disable the version you found:

```bash
a2dismod php8.4   # php8.4 on trixie; php8.2 / php8.3 on older distros
```

That module name tracks the **distro's** PHP package, not the nix-built one: it
does not change when the flake's PHP version does. Disabling a module that is
not installed merely prints an error and exits non-zero.

## Vhost

The vhost is a consumer-owned template, not a committed file: it lives in the
consumer's config repo and is rendered from `deploy.conf`, the single source of
truth for its two placeholders. A consumer-owned reconcile script fills the
placeholders, installs the result as
`/etc/apache2/sites-available/<app>.conf` on the web host, and reloads Apache
**only when the file changed** — a vhost change needs `reload`, never `restart`,
and the reconcile must not run (or reload) on every deploy.

The two placeholders:

- the deploy directory (`DEPLOY_TARGET_DIR`) — the docroot base; and
- the php-fpm run base (`DEPLOY_PHP_FPM_RUN`) — the php-fpm pool renders its
  pid as `…@.pid` and its FastCGI socket as `…@.sock` from it, and the vhost
  must proxy to the same socket.

The rendered vhost forwards `.php` to the php-fpm socket:

```apache
<VirtualHost *:80>
    DocumentRoot "@DEPLOY_TARGET_DIR@/public"
    <Directory "@DEPLOY_TARGET_DIR@/public">
        Require all granted
        AllowOverride None
        Options -Indexes
    </Directory>
    <FilesMatch "\.php$">
        SetHandler "proxy:unix:@PHP_FPM_RUN@.sock|fcgi://localhost"
    </FilesMatch>
</VirtualHost>
```

`REUTER_INI` is set in the php-fpm pool (`env[REUTER_INI]`), not the vhost —
php-fpm does not inherit Apache `SetEnv`.

## Enable the site and restart Apache

Do this **after the first deploy**, not before: Apache checks `DocumentRoot`
while parsing the config and refuses to start when the path is missing
(`AH00526: ... DocumentRoot '/srv/apps/<app>/public' is not a directory, or is
not readable`), and `/srv/apps/<app>` appears only with that deploy's swap. The
reconcile script's first run installs the vhost, enables the site, and reloads
Apache — so run it only after the first deploy.

Deploy never touches Apache — its `web` step only restarts
`php-fpm-<app>.service` — so the reconcile is the operator's step: once after
the first deploy, and again after any later change to the vhost template or the
`deploy.conf` values it renders. The site becomes reachable once the first
deploy has created `/srv/apps/<app>` and started `php-fpm-<app>.service` (the
socket the vhost proxies to).

This is (relatively) safe because the browser physically cannot look "backward"
into your root directory.

```php
<?php
// index.php can still access the private folder securely from the server side:
require_blank_page_or_file("../src/Database.php");
?>
```

## Local endpoints

- From prod server (prod test) use the Apache endpoint (FastCGI to the nix
  php-fpm above).

- From dev machine (dev test) use the PHP built-in server. Run it inside
  `nix develop` so `php` is the same flake `php84` closure (version +
  `mysqli`/`pdo_mysql`/`bz2`) whose `php-fpm` serves prod — not the system
  PHP.

At the repo root, run:

```bash
make web
```

This starts the built-in server with `public/` as the docroot (`-t public`),
matching prod's DocumentRoot. `Ctrl-C` stops it.

Open your browser and navigate to

```
http://localhost:8000
```
