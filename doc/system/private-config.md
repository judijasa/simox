# Private configuration repository

Date: 2026-09-08
Scope: the simox-specific private data, and where the shared mechanism is
documented.

The `.private-source` pointer, the `fetch-private-data` injector, and the
`deploy-private-config` prod shipper are owned by
`judijasa/php-daas-framework`. Their mechanism — the single git retrieval
mode, the symlink wire invariant, and the two-step production delivery
(`deploy-private-config` ships `reuter.ini` to the stable per-app dir;
`fetch-private-data` links it into `etc/`) — is documented upstream in that
repo's
[`doc/system/private-config.md`](https://github.com/judijasa/php_daas_framework/blob/main/doc/system/private-config.md).
This page only records what is private *here*.

## Private data in simox

| File | Public template | Private data | Ships to prod? |
|---|---|---|---|
| `etc/reuter.ini` | `etc/reuter.ini.template` | per-database connectivity sections for `simo0`/`simo1` (recorded from `ema create`) | **yes — the only private file that leaves the private repo for a host** |
| `etc/machines.ini` | `etc/machines.ini.template` | prod ZeroTier IPs + `tag[:name]` roster | no (deploy/dev-time only) |
| `etc/team.ini` | `etc/team.ini.template` | member identities, hostnames, ZeroTier IPs | no (dev-only) |

`etc/deploy.conf` stays committed (project-static: paths, the app-user name —
no secrets). `etc/hosts` is git-ignored but is a local convenience mapping,
not injected by `fetch-private-data`. The `reuter.ini` connectivity sections
(and their `SIMOX_PASSWORD` key) are private data and
live only in the private repo, never in the public history —
The framework `gen-service-accounts` no longer writes those keys; they are
committed empty for now. The service-account policy itself (which accounts
exist and on which databases, via the shared `srv/roles-<GUID>` declaration
and the per-database `srv/<db>.roles-<GUID>` grants) is committed, not
private.

`reuter.ini` is the only private file a prod host needs, so it is the only
one that ever leaves the private repo for a host — and it ships **whole**
(no inner filtering, no section splicing). `machines.ini` feeds the local
deploy roster and `team.ini` feeds `gen-cert`/`gen-grants`/
`gen-service-accounts`/`init-local-env` on the deploy/dev machine; neither
reaches prod.

## Usage

Copy `.private-source.example` to `.private-source` and point it at the
private repo (tracked files: `machines.ini`, `reuter.ini`, `team.ini`) with
`PRIVATE_DATA_GIT` (+ optional `PRIVATE_DATA_REF`). In dev, `make dev-init`
(via the framework `init-local-env.sh`) runs `fetch-private-data` to link the
files into `etc/`. In prod, the deploy machine runs
`bin/deploy-private-config` to ship `reuter.ini` (whole) to each host's
`DEPLOY_PRIVATE_CONFIG_DIR`, and `pf-deploy.sh` runs `fetch-private-data` on
the host to link it into the fresh `etc/reuter.ini`. See
`php_daas_framework/doc/plans/2026-09-14-framework-private-config-deploy.md`
for the mechanism.
