# Private configuration repository

Date: 2026-09-08
Scope: the simox-specific private data, and where the shared mechanism is
documented.

The `.private-source` pointer, the `fetch-private-data` injector, and the
`deploy-private-config` prod shipper are owned by
`judijasa/php-daas-framework`. Their mechanism — the single git retrieval
mode, the symlink wire invariant, and the two-step production delivery
(`deploy-private-config` ships `deploy.conf` and `reuter.ini` to the stable
per-app dir; `fetch-private-data` links them into `etc/`) — is documented
upstream in that repo's
[`doc/system/private-config.md`](https://github.com/judijasa/php_daas_framework/blob/main/doc/system/private-config.md).
This page only records what is private *here*.

## Private data in simox

| File | Public template | Private data | Ships to prod? |
|---|---|---|---|
| `etc/deploy.conf` | `etc/deploy.conf.template` | project deployment target (paths, the app-user name, cron target) | **yes — ships to prod with `reuter.ini`** |
| `etc/reuter.ini` | `etc/reuter.ini.template` | per-database connectivity sections for `simo0`/`simo1` (recorded from `ema create`) | **yes — ships to prod with `deploy.conf`** |
| `etc/machines.ini` | `etc/machines.ini.template` | prod ZeroTier IPs + `tag[:name]` roster | no (deploy/dev-time only) |
| `etc/team.ini` | `etc/team.ini.template` | member identities, hostnames, ZeroTier IPs | no (dev-only) |
| `etc/hosts` | `etc/hosts.template` | prod server name→IP aliases (feed the `/etc/hosts` merge and the generated ssh config) | no (dev-only) |
| `etc/host-hardening.php` | `etc/host-hardening.php.template` | firewall reconcile declaration (`$zerotierRange`, `$cloudTest`, `$tagRules`) for `gen-firewall` | no (deploy/dev-time only) |

`etc/deploy.conf` is now private data too (the project deployment target:
paths, the app-user name, the cron target) — it ships to prod with
`reuter.ini`, so the public repo keeps only `etc/deploy.conf.template`.
`etc/hosts` is a dev-only name→IP convenience mapping, injected by
`fetch-private-data`. It feeds two dev-machine conveniences from the same
entries: the `/etc/hosts` merge (`make dev-init`) and the generated
`~/.ssh/config.d/simox.conf`, where each entry becomes `ssh simox-<name>` as
`root` with the project key (see the README's dev ssh section).

What is actually secret in `reuter.ini` is the **connectivity endpoints**, not
credentials. Each `[simo0]`/`[simo1]` section carries `SERVER`/`PORT`/
`MYSQL_UNIX_PORT` (ZeroTier IPs and socket paths, recorded from `ema create`),
and those live only in the private repo, never in the public history. The
`SIMOX_PASSWORD` key is **not** secret: the service account is passwordless by
policy, so the key stays empty. The framework `gen-service-accounts`
reconciles the account (create/drop, role-based) against the shared
`srv/roles-<GUID>` declaration but never writes a password back into this
file — the template ships `SIMOX_PASSWORD=` empty. The service-account
*policy* itself — which accounts exist and on which databases (the shared
`srv/roles-<GUID>` `$sources`/`$accounts` declaration plus the per-database
`srv/<db>.roles-<GUID>` grants) — is committed, not private.

`deploy.conf` and `reuter.ini` are the only private files a prod host needs,
so they are the only ones that ever leave the private repo for a host — and
they ship **whole** (no inner filtering, no section splicing).
`machines.ini` feeds the local deploy roster, `team.ini` feeds `gen-cert`/
`gen-grants`/`gen-service-accounts`/`init-local-env`, `hosts` feeds the dev
`/etc/hosts` merge and the generated ssh config on the deploy/dev machine,
and `host-hardening.php` feeds `gen-firewall`; none of them reaches prod.

## Usage

Copy `.private-source.example` to `.private-source` and point it at the
private repo (tracked files: `deploy.conf`, `machines.ini`, `reuter.ini`,
`team.ini`, `hosts`, `host-hardening.php`) with `PRIVATE_DATA_GIT` (+ optional
`PRIVATE_DATA_REF`). In dev,
`make dev-init`
(via the framework `init-local-env.sh`) runs `fetch-private-data` to link the
files into `etc/`. In prod, the deploy machine runs
`bin/deploy-private-config` to ship `deploy.conf` and `reuter.ini` (whole) to
each host's `DEPLOY_PRIVATE_CONFIG_DIR`, and `pf-deploy.sh` runs
`fetch-private-data` on the host to link them into the fresh `etc/`. See
`php_daas_framework/doc/plans/2026-09-14-framework-private-config-deploy.md`
for the mechanism.
