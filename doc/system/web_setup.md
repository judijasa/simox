# Web service configuration

In production the site is served by a global system web server (Apache) that
forwards PHP to the nix-built `php-fpm` over FastCGI — it does **not** run PHP
as an Apache module (`mod_php`). The deploy's `web` step installs and manages
the php-fpm pool config (`/etc/simox/php-fpm-simox.conf`) and systemd unit
(`php-fpm-simox.service`) and starts the service. The Apache install, the
`mod_php` → php-fpm switch, the vhost and the site enable below are one-time
manual steps that deploy does not manage; run them as root.

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

Point the vhost at the deploy directory and forward `.php` to the php-fpm
socket. Write it to `/etc/apache2/sites-available/simox.conf`:

```apache
DocumentRoot "/srv/apps/simox/public"
<Directory "/srv/apps/simox/public">
    Require all granted
</Directory>
<FilesMatch "\.php$">
    SetHandler "proxy:unix:/run/php-fpm-simox.sock|fcgi://localhost"
</FilesMatch>
```

`REUTER_INI` is set in the php-fpm pool (`env[REUTER_INI]`), not the vhost —
php-fpm does not inherit Apache `SetEnv`.

## Enable the site and restart Apache

Do this **after the first deploy**, not before: Apache checks `DocumentRoot`
while parsing the config and refuses to start when the path is missing
(`AH00526: ... DocumentRoot '/srv/apps/simox/public' is not a directory, or is
not readable`), and `/srv/apps/simox` appears only with that deploy's swap.
Until then leave the site disabled (`a2dissite simox`) and Apache stopped.

```bash
a2ensite simox
systemctl restart apache2
```

Deploy never touches Apache — its `web` step only restarts
`php-fpm-simox.service` — so this vhost switch is the operator's step: once per
host, plus a `systemctl reload apache2` after any later vhost edit. The site
becomes reachable once the first deploy has created `/srv/apps/simox` and
started `php-fpm-simox.service` (the socket the vhost proxies to).

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
