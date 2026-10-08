# Web service configuration

In production the site is served by a global system web server that forwards PHP
to the nix-built `php-fpm` over FastCGI — it does **not** run PHP as a web-server
module (`mod_php`). The deploy's `web` step installs and manages the php-fpm
pool config (`/etc/<app>/php-fpm-<app>.conf`) and systemd unit
(`php-fpm-<app>.service`) and starts the service.

The pool derives its `pid` and `listen` (FastCGI socket) paths from the single
`DEPLOY_PHP_FPM_RUN` value in `deploy.conf`: `<base>.pid` and `<base>.sock`,
where `<base>` is that value.

`REUTER_INI` and `SSL_DIR` are set in the php-fpm pool (`env[REUTER_INI]`,
`env[SSL_DIR]`), not in the web server's vhost: php-fpm does not inherit the web
server's `SetEnv`.

The web server install, the `mod_php` → php-fpm switch, the vhost, and the site
enable are the consumer's responsibility, not simox's: simox only provides the
socket above, and the vhost must forward `.php` to it.

## Local endpoints

- From prod server (prod test) use the web server endpoint (FastCGI to the nix
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
