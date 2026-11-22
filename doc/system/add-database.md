# Adding a database

The sequence for adding a database to this repo. `<primary>` is the reference
case; `<replica>` is the same sequence plus the replica build.

## Quick setup

```bash
# private etc/machines.ini: add db:<name> to the target host's tokens

ema database <name>                          # scaffold srv/<name>-<GUID>/
ema schema <name>                            # scaffold pkg/<pkg>-<GUID>/
# add pkg/<name>.roles-<GUID>/, granting the roles on {{dbname}}

deploy <host>

tmux-remote <host> <session>                 # then inside the session:
ema create srv/<name>-<GUID>

# private reuter.ini: record the printed [mariadb:<name>] section

gen-service-accounts <name>      # from the dev machine
vendor/bin/gen-firewall <host> --apply
deploy <host>                                 # read db-check
```

## 1. Tag the host

Add `db:<name>` to the target host's token list in the private
`etc/machines.ini` roster (`etc/machines.ini.template` documents what
the tag drives). The roster is read on the deploy machine and never shipped to
a host, so the tag must be in place before the deploy in step 3.

## 2. Author the packages

`ema database <name>` scaffolds `srv/<name>-<GUID>/`, `ema schema <name>`
scaffolds `pkg/<pkg>-<GUID>/` (both shapes are in the framework's
[doc/system/ema.md](https://github.com/judijasa/php_daas_framework/blob/main/doc/system/ema.md)). The shared `pkg/roles-<GUID>/` package is untouched — its
roles are global (`member`/`worker`/`web`), not per-database. For the new
database's own grants, add a `pkg/<name>.roles-<GUID>/` package (mirroring
`pkg/<primary>.roles-<GUID>/`) whose `upgrade.sql` grants the existing roles,
scoped with the `{{dbname}}` placeholder — e.g. `GRANT ALL PRIVILEGES ON
{{dbname}}.* TO <account>_worker;`. A `db:<name>` tag pins no role of its own:
access follows what a host runs (`worker`/`web`) or its members, never what
database it stores.

Which roles a database grants, and to which sources, is
[service-accounts.md](service-accounts.md).

## 3. Deploy

```bash
deploy <host>               # or `deploy all` for every prod host
```

`<host>` is a prod host by its short name or its ZeroTier IP, resolved by
the framework's shared host lookup (`vendor/bin/pf-host`; the framework's
[doc/system/host-resolution.md](https://github.com/judijasa/php_daas_framework/blob/main/doc/system/host-resolution.md)).

`ema create` asserts the `mariadb@.service` unit that deploy installs, so a
host that has never been deployed must be deployed first.

## 4. Create the database

```bash
tmux-remote <host> <session>         # from the repo root
ema create srv/<name>-<GUID>         # inside the session
```

`tmux-remote` opens the shell `ema` needs on a prod host — the framework's
[doc/system/tmux-remote.md](https://github.com/judijasa/php_daas_framework/blob/main/doc/system/tmux-remote.md).
`ema create` provisions the database's own
instance and creates the database; it reads nothing from `reuter.ini`, because
the section does not exist yet. It is create-only (`ema drop` is retired: to
delete a created database, run a deliberate `DROP DATABASE` over
`ema mdb <name>` — the instance's datadir/unit are removed by hand) and
prints the `[mariadb:<name>]` section on success.

A replica package instead takes `--from-snapshot <path>`: it restores the
primary's snapshot and attaches replication, reading the primary's
`[mariadb:<primary>]` section — so the primary's section must be recorded first. See
[replica-bootstrap.md](replica-bootstrap.md).

## 5. Record the connectivity

Put the printed section in the private config repo's `reuter.ini` (which
[private-config.md](private-config.md) delivers; the keys are documented in
`etc/reuter.ini.template`). The `<ACCOUNT>_PASSWORD` key is optional (an absent
or empty value means a passwordless account):

```ini
[mariadb:<name>]
SERVER=<overlay ip>
PORT=<auto-picked>
MYSQL_UNIX_PORT=/var/lib/mariadb/<name>/mysql.sock
```

`MYSQL_UNIX_PORT` is what step 6's root-over-socket path needs. A lost record is
recoverable with `ema values <name>`.

## 6. Reconcile the accounts and grants

The section must be recorded first: the reconcile runs `ema mdb <name>` on
the host, which resolves it. From the dev machine:

```bash
gen-service-accounts <name> -n     # review the SQL
gen-service-accounts <name>        # apply
```

`gen-service-accounts` plans from the roster here and applies the SQL as `root`
over ssh on the host carrying the `db:<name>` tag, over the instance's own
socket — the `MYSQL_UNIX_PORT` recorded in step 5. The policy it converges to is
[service-accounts.md](service-accounts.md).

## 7. Open the port

```bash
vendor/bin/gen-firewall <host> --dry-run     # review
vendor/bin/gen-firewall <host> --apply       # apply (`all` for every host)
```

`gen-firewall` runs from the repo root on the deploy/dev machine, not on the
host: it reads the local `etc/machines.ini` and `etc/host-hardening.php` and
applies over `ssh root@<host>`. The `db:<name>` rule takes the port from the
section recorded in step 5.

## 8. Verify

Run a deploy and read `db-check`'s warn-only output, or open the database with
`ema mdb <name>`.

## 9. Removing a database instance

Removal is the reverse of the add sequence and is manual — `ema` has no delete
verb (`ema drop` is retired, see step 4). Two scopes:

- **Schema only** (keep the instance): drop the schema over the instance's own
  socket; the instance — datadir, unit, port, socket — is untouched. On the
  database host, as root:

  ```bash
  tmux-remote <host> <session>                 # from the repo root
  ema mdb <name> -e 'DROP DATABASE <dbname>'   # inside the session
  ```

  `<name>` is the instance (the `[mariadb:<name>]` section header); `<dbname>` is the
  schema it serves (that section's `DBNAME`). `ema create` may rebuild it
  afterward.

- **Whole instance** (the datadir, systemd unit, conf and port): the teardown
  below. The schema lives in the datadir and goes with it, so no
  `DROP DATABASE` is needed.

### 9.1 Remove the instance (on the host)

Inside a `tmux-remote <host> <session>` session, as root:

```bash
systemctl disable --now mariadb@<name>
rm -rf /var/lib/mariadb/<name>        # data/, socket, pid, log, binlog
rm -rf /etc/<name>                    # the instance's my.cnf
```

`mariadb@<name>` is the unit `ema create` enabled; `disable --now` reverses it.
`/var/lib/mariadb/<name>/` holds the datadir (`data/`) plus the socket, pid,
log and (primary only) `binlog/`; `/etc/<name>/my.cnf` is the conf `ema create`
wrote.

### 9.2 Untag the host

Remove the `db:<name>` token from the target host's token list in the private
`etc/machines.ini` roster. `gen-firewall` emits the database's allow rule only
while that token is present.

### 9.3 Close the port

```bash
vendor/bin/gen-firewall <host> --dry-run     # review
vendor/bin/gen-firewall <host> --apply       # apply (`all` for every host)
```

With the `db:<name>` token gone the port's allow rule drops out of the desired
set; `gen-firewall` resets and re-applies the whole set (fail-open), so
re-running converges. Keep the `[mariadb:<name>]` section in `reuter.ini` until this
step — `gen-firewall` reads the port from it, and errors if the section is
missing while the token is still present.

### 9.4 Drop the connectivity record

Delete the `[mariadb:<name>]` section from the private `reuter.ini` (delivered by
[private-config.md](private-config.md)). This removes the instance from
`db-check`'s reachability check and from anything resolving that section.

### 9.5 Remove the packages

```bash
git rm -r srv/<name>-<GUID> pkg/<name>.roles-<GUID>
```

`srv/<name>-<GUID>/` is the database package scaffolded in step 2;
`pkg/<name>.roles-<GUID>/` is its per-database grants package. The service
accounts themselves lived in the instance's own datadir and were removed with
it in step 9.1, so no `gen-service-accounts` pass is needed.

### 9.6 Deploy

```bash
deploy <host>               # or `deploy all` for every prod host
```

`db-check` (warn-only) now finds no `mariadb@<name>` unit and no `[mariadb:<name>]`
section, so it reports nothing for the removed instance.
