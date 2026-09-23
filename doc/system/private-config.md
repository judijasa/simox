# Private configuration

Date: 2026-09-08 (shipping moved to the framework 2026-09-20)
Scope: the simox-specific private data and the delivery that puts it on dev and
prod machines.

simox owns the materialization half: the `.private-source` pointer and
`bin/fetch-private-data` (copy the real `etc/` files in locally). The shipping
half is the framework's: it ships the files named in `DEPLOY_PRIVATE_FILES`
into the freshly swapped `etc/` on each host and replays the deploy machine's
`deploy.conf` environment to every remote step, so `deploy.conf` itself never
reaches prod. The generic contract is documented in the framework's
`doc/system/consumer-config.md`.

## Private data in simox

| File | Public template | Private data | Ships to prod? |
|---|---|---|---|
| `etc/deploy.conf` | `etc/deploy.conf.template` | project deployment target (paths, the app-user name, cron target) | **no — deploy-machine only; its values are replayed as environment** |
| `etc/reuter.ini` | `etc/reuter.ini.template` | per-database connectivity sections for `simo0`/`simo1` (recorded from `ema create`) | **yes — via `DEPLOY_PRIVATE_FILES`** |
| `etc/machines.ini` | `etc/machines.ini.template` | prod ZeroTier IPs + `tag[:name]` roster | no (deploy/dev-time only) |
| `etc/team.ini` | `etc/team.ini.template` | member identities, hostnames, ZeroTier IPs | no (dev-only) |
| `etc/hosts` | `etc/hosts.template` | prod server name→IP aliases (feed the `/etc/hosts` merge and the generated ssh config) | no (dev-only) |
| `etc/host-hardening.php` | `etc/host-hardening.php.template` | firewall reconcile declaration (`$zerotierRange`, `$tagRules`) for `gen-firewall` | no (deploy/dev-time only) |

`etc/deploy.conf` is private data too (the project deployment target: paths, the
app-user name, the cron target) — but it stays on the deploy machine: the
framework sources it locally and replays its environment to the host, so the
public repo keeps only `etc/deploy.conf.template`. `etc/hosts` is a dev-only
name→IP convenience mapping. It feeds two dev-machine conveniences from the same
entries: the `/etc/hosts` merge (`make dev-init`) and the generated
`~/.ssh/config.d/simox.conf`, where each entry becomes `ssh simox-<name>` as
`root` with the project key (see [deploy.md](deploy.md#dev-ssh-config)).

## Delivery

One retrieval mechanism — git, through `.private-source` — and two steps:

1. **Materialize (dev/deploy machine).** `bin/fetch-private-data` clones or
   fetches `PRIVATE_DATA_GIT` (+ optional `PRIVATE_DATA_REF`, default `main`)
   into `var/private-data`, then copies the six tracked files
   from there into `etc/` as **real files**, overwriting them on every run.
   `make dev-init` runs it, so a dev checkout carries its own
   `etc/reuter.ini`, `etc/machines.ini`, `etc/team.ini` and `etc/hosts` (plus
   `etc/deploy.conf` and `etc/host-hardening.php` when the private repo provides
   them). An absent `.private-source` makes the step a no-op, and the repo then
   runs on its committed templates.
2. **Ship (framework, during deploy).** `pf-deploy.sh` ships the files named in
   `DEPLOY_PRIVATE_FILES` (here just `reuter.ini`) — and nothing else —
   **whole** from the deploy machine's `etc/` into the freshly swapped `etc/`
   on each `[prod]` host (the roster read locally from `etc/machines.ini` via
   `vendor/bin/pf-roster`). At the same time it replays the deploy machine's
   `deploy.conf` environment to every remote step, so the host's `gen-env`,
   `provision-extra.sh` and `server-side-post-deploy.sh` resolve `DEPLOY_*`
   without a `deploy.conf` of their own. `bin/deploy.sh` runs
   `fetch-private-data` first, then hands off to the framework.

Real files, not symlinks: the private repo's committed content is the single
source of truth, and every machine materializes its own copy of it. A symlinked
`etc/` would leave the operational data dangling whenever `var/private-data` is rebuilt, and would let a local edit silently change the
source repo; copying overwrites, so a local edit to one of these files is lost —
the private repo is the place to change settings.

Prod hosts have neither git nor the `.private-source` pointer, so they only ever
receive `reuter.ini`, through the framework's ship step above.

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

`reuter.ini` is the only private file a prod host needs, so it is the only one
that ever leaves the private repo for a host — and it ships **whole** (no inner
filtering, no section splicing). `machines.ini` feeds the local deploy roster,
`team.ini` feeds `gen-cert`/`gen-grants`/`gen-service-accounts`/`init-local-env`,
`hosts` feeds the dev `/etc/hosts` merge and the generated ssh config on the
deploy/dev machine, and `host-hardening.php` feeds `gen-firewall`; none of them
reaches prod.

## Usage

Copy `.private-source.example` to `.private-source` and point it at the private
repo (tracked files: `deploy.conf`, `machines.ini`, `reuter.ini`, `team.ini`,
`hosts`, `host-hardening.php`) with `PRIVATE_DATA_GIT` (+ optional
`PRIVATE_DATA_REF`). Then:

```bash
make dev-init                     # materializes the private files into etc/ (dev)
bin/fetch-private-data            # that same step alone (idempotent)
make deploy [<host>]              # ships reuter.ini via DEPLOY_PRIVATE_FILES, then deploys
```

`bin/fetch-private-data` performs only the materialize step — the deploy
pipeline runs it for you before handing off to the framework.
