# Private configuration

Date: 2026-09-08 (shipping moved to the framework 2026-09-20; committed defaults 2026-10-01)
Scope: the consumer-specific private data and the delivery that puts it on dev and
prod machines.

this repo owns the materialization half: the `.private-source` pointer and
`bin/fetch-private-data` (copy the real `etc/` files in locally). The shipping
half is the framework's: it ships the files named in `DEPLOY_PRIVATE_FILES`
into the freshly swapped `etc/` on each host and replays the deploy machine's
`deploy.conf` environment to every remote step, so `deploy.conf` itself never
reaches prod. The generic contract is documented in the framework's
[doc/system/consumer-config.md](https://github.com/judijasa/php_daas_framework/blob/main/doc/system/consumer-config.md).

## Quick setup

```bash
cp .private-source.example .private-source   # set PRIVATE_DATA_GIT (+ PRIVATE_DATA_REF)
make dev-init                                # materialize the private files into etc/
make deploy [<host>]                         # ship the private files, then deploy
```

A machine that must reach a database whose accounts require X509 also needs its
own client certificate — a separate, occasionally-run step, not part of
`dev-init`. See [machine-certs.md](machine-certs.md).

## Private data in this repo

| File | Committed in public repo | Private data | Ships to prod? |
|---|---|---|---|
| `etc/deploy.conf` | `etc/deploy.conf.template` | project deployment target (paths, the app-user name, cron target, the host-side client-cert dir) | **no — deploy-machine only; its values are replayed as environment** |
| `etc/reuter.ini` | `etc/reuter.ini.template` | per-instance connectivity sections for `<primary>`/`<replica>` (recorded from `ema create`; each section's `DBNAME` names the shared schema) | **yes — via `DEPLOY_PRIVATE_FILES`** |
| `etc/ema.conf` | `etc/ema.default.conf` (consumed default) + optional `etc/ema.conf` override | host-level `ema` config (the `ssl-ca` the instance verifies client certs against) | **yes — via `DEPLOY_PRIVATE_FILES` (the override over the generic committed default)** |
| `etc/dev.conf` | `etc/dev.default.conf` (consumed default) + optional `etc/dev.conf` override | dev-machine values the framework sources (`DBUSER`, `SSL_DIR`) | no (dev-machine only) |
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
`~/.ssh/config.d/<app>.conf`, where each entry becomes `ssh <app>-<name>` as
`root` with the project key (see [deploy.md](deploy.md#dev-ssh-config)).

`etc/dev.default.conf` and `etc/ema.default.conf` are different from the rest:
they are **consumed defaults** (real values the repo ships, so a checkout works
without private data), not copy-me shapes. `.template` stays reserved for the
copy-me files (`deploy.conf`, `reuter.ini`, `machines.ini`, `team.ini`, `hosts`,
`host-hardening.php`); the two default files are read directly, with the
git-ignored `*.conf` override layered on top only when a machine diverges.

## Delivery

One retrieval mechanism — git, through `.private-source` — and two steps:

1. **Materialize (dev/deploy machine).** `bin/fetch-private-data` clones or
   fetches `PRIVATE_DATA_GIT` (+ optional `PRIVATE_DATA_REF`, default `main`)
   into `var/private-data`, then copies the eight tracked files
   from there into `etc/` as **real files**, overwriting them on every run.
   `etc/deploy.conf` is required — a private source without it fails the step,
   because a checkout that cannot deploy is worse than a loud stop.
   `etc/reuter.ini` is expected but not a hard prerequisite: a no-database
   bootstrap legitimately has none, so its absence is only warned here and
   confirmed by the deploy wrapper before shipping. `etc/dev.conf` is an
   optional override (its default is committed in `etc/dev.default.conf`, so a
   checkout works without the private copy and only a machine that diverges
   needs one); `etc/ema.conf` is the host-level override whose real
   `ssl-ca`/`ssl-crl` ship to prod (the committed `etc/ema.default.conf` carries
   only a generic fallback). `etc/machines.ini`, `etc/team.ini`, `etc/hosts` and
   `etc/host-hardening.php` are copied only when the private source provides
   them. `make dev-init` runs it, so a dev checkout carries its own
   `etc/reuter.ini` and `etc/team.ini` (plus whatever else the private repo
   carries). An absent `.private-source` makes the step a no-op, and the repo
   then runs on its committed defaults.
2. **Ship (framework, during deploy).** `pf-deploy.sh` ships the files named in
   `DEPLOY_PRIVATE_FILES` (here `reuter.ini ema.conf`) — and nothing else — **whole**
   from the deploy machine's `etc/` into the freshly swapped `etc/` on each
   prod host (the roster read locally from `etc/machines.ini` via
   `vendor/bin/pf-roster`), skipping any name that is absent from `etc/` —
   `bin/deploy.sh` asks the operator to confirm such an absence first. At the
   same time it replays the deploy machine's
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
receive `reuter.ini` and `ema.conf` through the framework's ship step above; the
latter carries the host-level `ssl-ca` (the committed `etc/ema.default.conf` is
only a generic fallback).

What is actually secret in `reuter.ini` is the **connectivity endpoints**, not
credentials. Each `[<primary>]`/`[<replica>]` section carries `SERVER`/`PORT`/
`MYSQL_UNIX_PORT` (ZeroTier IPs and socket paths, recorded from `ema create`),
and those live only in the private repo, never in the public history. The
`<ACCOUNT>_PASSWORD` key is **not** secret: the service account is passwordless, and
what gates it is the `require: 'X509'` declaration in the roles package plus the
host-level `ssl-ca` in `etc/ema.default.conf` (see [machine-certs.md](machine-certs.md)),
so the key stays empty. The framework `gen-service-accounts` reconciles
the account (create/drop, role-based) against the shared `pkg/roles-<GUID>`
declaration but never writes a password back into this file — the template ships
`<ACCOUNT>_PASSWORD=` empty. The service-account *policy* itself — which accounts
exist and on which databases (the shared `pkg/roles-<GUID>`
`sources`/`accounts` declaration plus the per-instance `pkg/<db>.roles-<GUID>`
grants) — is committed, not private.

`reuter.ini` is the one private file **every** DB-connecting prod host needs
whatever its role, so it ships **whole** (no inner filtering, no section
splicing) — except on a no-database bootstrap, where its absence is confirmed
and nothing ships. `ema.conf` ships too: its
host-level `ssl-ca` is the real host path, which the committed
`etc/ema.default.conf` carries only as a generic fallback (the real value must
never be committed), so `DEPLOY_PRIVATE_FILES` names `reuter.ini ema.conf`.
`dev.conf` never leaves the deploy machine — it is sourced there
(the framework's `init-local-env.sh`, from the committed default then the
optional override) and read by `gen-cert`. `machines.ini` feeds the local
deploy roster, `team.ini` feeds
`gen-cert`/`gen-team-accounts`/`gen-service-accounts`,
`hosts` feeds the dev `/etc/hosts` merge and the generated ssh config on the
deploy/dev machine, and `host-hardening.php` feeds `gen-firewall`; none of them
reaches prod.

The service-account reconcile is where that matters most:
`gen-service-accounts` plans from the roster (`machines.ini`, `team.ini`) and
applies on the host carrying the database's `db:<name>` tag, as `root` over ssh
— so the roster's authority stays on the machine where it is edited and
reviewed, and no host holds a roster copy that could go stale between deploys
and silently revoke a member's access on the next run.

## Usage

Copy `.private-source.example` to `.private-source` and point it at the private
repo (tracked files: `deploy.conf`, `reuter.ini`, `ema.conf`, `dev.conf`,
`machines.ini`, `team.ini`, `hosts`, `host-hardening.php`) with
`PRIVATE_DATA_GIT` (+ optional
`PRIVATE_DATA_REF`). Then:

```bash
make dev-init                     # materializes the private files into etc/ (dev)
bin/fetch-private-data            # that same step alone (idempotent)
make deploy [<host>]              # ships the private files via DEPLOY_PRIVATE_FILES, then deploys
```

`bin/fetch-private-data` performs only the materialize step — the deploy
pipeline runs it for you before handing off to the framework.
