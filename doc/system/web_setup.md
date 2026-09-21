## Web service configuration

In production the site is served by a global system web server (Apache/nginx)
that forwards PHP to the nix-built `php-fpm` over FastCGI — it does **not** run
PHP as an Apache module (`mod_php`). The deploy's `web` step installs and
manages the php-fpm pool config (`/etc/simox/php-fpm-simox.conf`) and systemd
unit (`php-fpm-simox.service`); only the vhost + module switch below are
one-time manual steps.

Apache (`/etc/apache2/sites-available/*.conf`):

```apache
DocumentRoot "/srv/apps/simox/public"
<Directory "/srv/apps/simox/public">
    Require all granted
</Directory>
<FilesMatch "\.php$">
    SetHandler "proxy:unix:/run/php-fpm-simox.sock|fcgi://localhost"
</FilesMatch>
```

Enable the proxy modules, disable mod_php, and restart Apache:

```bash
a2enmod proxy proxy_fcgi
a2dismod php8.4   # module name varies by distro/version
systemctl restart apache2
```

`REUTER_INI` is set in the php-fpm pool (`env[REUTER_INI]`), not the vhost —
php-fpm does not inherit Apache `SetEnv`.

This is (relatively) safe because the browser physically cannot look "backward"
into your root directory.

```php
<?php
// index.php can still access the private folder securely from the server side:
require_blank_page_or_file("../src/Database.php");
?>
```

## Local endpoints

- From prod server (prod test) use the apache/nginx endpoint (FastCGI to the
  nix php-fpm above).

- From dev machine (dev test) use the PHP Built-in Server:

At repo root dir, execute (-t to target index.php relative location)

```bash
php -S localhost:8000 -t public
```

Open your browser and navigate to

```
http://localhost:8000
```

Ctrl + C in terminal to shut down the server.
