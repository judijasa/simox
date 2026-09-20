# Private configuration

Date: 2026-09-08 (private-config pipeline made simox-owned 2026-09-20)
Scope: the simox-specific private data and the simox-owned pipeline that
delivers it to dev and prod machines.

simox owns its private-config pipeline end to end: the `.private-source`
pointer, `bin/fetch-private-data` (materialize the real `etc/` files),
`bin/deploy-private-config` (ship the runtime files to prod) and
`bin/deploy/inject-private-config.sh` (restore them on the host). The framework
ships no private-config tooling of its own: it only sources the real `etc/`
files and runs the hook `etc/deploy.conf` declares in
`DEPLOY_PRE_PROVISION_CMD` (the generic contract is documented in the
framework's `doc/system/consumer-config.md`).

## Private data in simox

| File | Public template | Private data | Ships to prod? |
|---|---|---|---|
| `etc/deploy.conf` | `etc/deploy.conf.template` | project deployment target (paths, the app-user name, cron target) | **yes — ships to prod with `reuter.ini`** |
| `etc/reuter.ini` | `etc/reuter.ini.template` | per-database connectivity sections for `simo0`/`simo1` (recorded from `ema create`) | **yes — ships to prod with `deploy.conf`** |
| `etc/machines.ini` | `etc/machines.ini.template` | prod ZeroTier IPs + `tag[:name]` roster | no (deploy/dev-time only) |
| `etc/team.ini` | `etc/team.ini.template` | member identities, hostnames, ZeroTier IPs | no (dev-only) |
| `etc/hosts` | `etc/hosts.template` | prod server name→IP aliases (feed the `/etc/hosts` merge and the generated ssh config) | no (dev-only) |
| `etc/host-hardening.php` | `etc/host-hardening.php.template` | firewall reconcile declaration (`$zerotierRange`, `$cloudTest`, `$tagRules`) for `gen-firewall` | no (deploy/dev-time only) |

`etc/deploy.conf` is private data too (the project deployment target: paths, the
app-user name, the cron target) — it ships to prod with `reuter.ini`, so the
public repo keeps only `etc/deploy.conf.template`. `etc/hosts` is a dev-only
name→IP convenience mapping. It feeds two dev-machine conveniences from the same
entries: the `/etc/hosts` merge (`make dev-init`) and the generated
`~/.ssh/config.d/simox.conf`, where each entry becomes `ssh simox-<name>` as
`root` with the project key (see the README's dev ssh section).

## Delivery

One retrieval mechanism — git, through `.private-source` — and three steps, all
of them simox's:

1. **Materialize (dev/deploy machine).** `bin/fetch-private-data` clones or
   fetches `PRIVATE_DATA_GIT` (+ optional `PRIVATE_DATA_REF`, default `main`)
   into the git-ignored `var/private-data`, then copies the six tracked files
   from there into `etc/` as **real files**, overwriting them on every run.
   `make dev-init` runs it, so a dev checkout carries its own
   `etc/reuter.ini`, `etc/machines.ini`, `etc/team.ini` and `etc/hosts` (plus
   `etc/deploy.conf` and `etc/host-hardening.php` when the private repo provides
   them). An absent `.private-source` makes the step a no-op, and the repo then
   runs on its committed templates.
2. **Ship (deploy machine).** `bin/deploy-private-config` ships `deploy.conf`
   and `reuter.ini` — and nothing else — **whole** (`git archive HEAD`, so
   committed content only) to `DEPLOY_PRIVATE_CONFIG_DIR` on each `[prod]` host,
   with the roster read locally from `etc/machines.ini` via
   `vendor/bin/pf-roster`. `bin/deploy.sh` runs it before the framework
   `pf-deploy.sh`; it also works on its own.
3. **Restore (prod host).** `bin/deploy/inject-private-config.sh` is the
   `DEPLOY_PRE_PROVISION_CMD` hook named in `etc/deploy.conf`. `pf-deploy.sh`
   runs it as root in the deployed repo root, right after the repo swap (which
   replaces the directory and leaves only the committed templates in `etc/`) and
   before anything sources `etc/deploy.conf`, replaying the deploy machine's
   `deploy.conf` environment so `DEPLOY_PRIVATE_CONFIG_DIR` resolves. It copies
   the two files into `etc/` as real files — `reuter.ini` at
   `DEPLOY_REUTER_INI`.

Real files, not symlinks: the private repo's committed content is the single
source of truth, and every machine materializes its own copy of it. A symlinked
`etc/` would leave the operational data dangling whenever the git-ignored
`var/private-data` is rebuilt, and would let a local edit silently change the
source repo; copying overwrites, so a local edit to one of these files is lost —
the private repo is the place to change settings.

Prod hosts have neither git nor the `.private-source` pointer, so they only ever
receive the two runtime files, through the steps above.

What is actually secret in `reuter.ini` is the **connectivity endpoints**, not
credentials. Each `[simo0]`/`[simo1]` section carries `SERVER`/`PORT`/
`MYSQL_UNIX_PORT` (ZeroTier IPs and socket paths, recorded from `ema create`),
and those live only in the private repo, never in the public history. The
`SIMOX_PASSWORD` key is **not** secret: the service account is passwordless by
policy, so the key stays empty. The framework `gen-service-accounts` reconciles
the account (create/drop, role-based) against the shared `srv/roles-<GUID>`
declaration but never writes a password back into this file — the template ships
`SIMOX_PASSWORD=` empty. The service-account *policy* itself — which accounts
exist and on which databases (the shared `srv/roles-<GUID>`
`$sources`/`$accounts` declaration plus the per-database `srv/<db>.roles-<GUID>`
grants) — is committed, not private.

`deploy.conf` and `reuter.ini` are the only private files a prod host needs, so
they are the only ones that ever leave the private repo for a host — and they
ship **whole** (no inner filtering, no section splicing). `machines.ini` feeds
the local deploy roster, `team.ini` feeds
`gen-cert`/`gen-grants`/`gen-service-accounts`/`init-local-env`, `hosts` feeds
the dev `/etc/hosts` merge and the generated ssh config on the deploy/dev
machine, and `host-hardening.php` feeds `gen-firewall`; none of them reaches
prod.

## Usage

Copy `.private-source.example` to `.private-source` and point it at the private
repo (tracked files: `deploy.conf`, `machines.ini`, `reuter.ini`, `team.ini`,
`hosts`, `host-hardening.php`) with `PRIVATE_DATA_GIT` (+ optional
`PRIVATE_DATA_REF`). Then:

```bash
make dev-init                     # materializes the private files into etc/ (dev)
bin/fetch-private-data            # that same step alone (idempotent)
make deploy [<host>]              # ships + injects the two runtime files, then deploys
```

`bin/deploy-private-config [<host>]` performs only the ship step (it
materializes first, so it is self-contained) — the deploy pipeline runs it for
you.
