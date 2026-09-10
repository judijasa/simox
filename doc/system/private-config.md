# Private configuration repository

Date: 2026-09-08
Scope: the simox-specific private data, and where the shared mechanism is
documented.

The `.private-source` pointer and the `fetch-private-data` CLI are owned by
`judijasa/php-daas-framework`. Their mechanism — how the pointer resolves a
private repo, the three retrieval modes, validation, and the no-git production
fallback — is documented upstream in that repo's
[`doc/system/private-config.md`](https://github.com/judijasa/php_daas_framework/blob/main/doc/system/private-config.md).
This page only records what is private *here*.

## Private data in simox

| File | Public template | Private data |
|---|---|---|
| `etc/machines.ini` | `etc/machines.ini.template` | prod ZeroTier IPs + `tag[:name]` roster |
| `etc/reuter.ini` | `etc/reuter.ini.template` | per-database connectivity sections (recorded from `ema create`) |
| `etc/team.ini` | `etc/team.ini.template` | member identities, hostnames, ZeroTier IPs |

`etc/deploy.conf` stays committed (project-static: paths, the app-user name —
no secrets). `etc/hosts` is git-ignored but is a local convenience mapping,
not injected by `fetch-private-data`. The `reuter.ini` connectivity sections
(and any `<ACCOUNT>_PASSWORD` keys) are private data and live only in the
private repo, never in the public history — `bin/gen-service-users` no longer
writes those keys; they are committed empty for now.

## Usage

Copy `.private-source.example` to `.private-source` and point it at a checkout
of the private repo (tracked files: `machines.ini`, `reuter.ini`, `team.ini`).
`bin/deploy.sh` (via the framework `pf-deploy.sh`) and `make dev-init` (via
the framework `init-local-env.sh`) run `fetch-private-data` before reading
those files, so the private data is injected whenever a `.private-source` is
configured.
