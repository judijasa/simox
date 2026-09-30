# Require X509 and TLS cert values — Plan & Progress

Date: 2026-09-29
Repos: simox (this repo); private config repo; php_daas_framework (upstream);
ema (upstream)

## Decision

Declare `require: 'X509'` on the `simox` service accounts and supply the
consumer-side cert values, so the prod DB hosts require a TLS client cert from
every connecting machine. The framework and ema mechanism work is tracked in
their own repos' plans; this repo carries the data.

One cert per machine, CN = the pinned simox-local name (no GUID), cert material
prod-only (the dev sandbox stays plain TCP). `REQUIRE X509` is a membership
gate, not identity: identity is the existing host pin (`'simox'@'10.99.233.X'`)
plus the cert subject in the audit log. It is applied uniformly — dev-machine
clients and prod-server clients alike must present a cert; there is no per-pin
opt-out.

Values this repo owns:

- `require: 'X509'` in the roles package;
- the dev-side consumer options `etc/dev.conf` (`DBUSER`,
  `SSL_DIR`) — source of truth for the human/dev cert path;
- the host-level `etc/ema.conf(.template)` carrying the server `ssl-ca` path;
- the private-config delivery of those values (`bin/fetch-private-data`, the
  private repo's own files).

`gen-cert` is deliberately **not** wired into `dev-init`: minting needs an
operator holding the CA key, and it matters only once a machine must reach a
database whose accounts require X509 — so it stays a documented manual step
(`doc/system/machine-certs.md`), and `dev-init` only materializes the values it
reads. `bin/fetch-private-data` treats `dev.conf` and `ema.conf` as required:
a checkout that cannot deploy, connect or mint a certificate should stop loudly.

Out of scope: the framework's client mechanism (`Database` env read, the
`dev.conf` materialization + sourcing, the machine-cert CLI) and ema's server
mechanism (reading `etc/ema.conf`, emitting `ssl-ca`) — each tracked in its own
repo's plan.

## Changes

### simox (this repo)

- [x] `pkg/roles-D0YRR7WII6V1XDZR/default.php` — add `require: 'X509'` to the
      `RolesConfig` (the `simox` account; the `replication` allow-list is
      unaffected unless later certed).
- [x] `etc/dev.conf.template` — commit the shape (`DBUSER`, `SSL_DIR`); the real
      `etc/dev.conf` stays git-ignored and materialized (values
      `DBUSER=simox`, `SSL_DIR=~/.simox/ssl`).
- [x] `etc/ema.conf.template` — commit `[default] ssl-ca = /etc/simox/ssl/ca.crt`
      (the server-side CA path); the real `etc/ema.conf` stays git-ignored and
      materialized.
- [x] `.gitignore` — ignore `etc/dev.conf` and `etc/ema.conf`.
- [x] `bin/fetch-private-data` — materialize `etc/dev.conf` and `etc/ema.conf`
      (the `reuter.ini` delivery pattern), both as required files.
- [x] `etc/deploy.conf.template` — `DEPLOY_PRIVATE_FILES="reuter.ini ema.conf"`
      (every host in the roster provisions an instance, so all of them need the
      host-level `ssl-ca`) and `DEPLOY_SSL_DIR=/etc/simox/ssl` (the host's
      client-cert dir, for the app layer's `SSL_DIR`). The real
      `etc/deploy.conf` is git-ignored and rewritten from the private repo on
      every fetch — the private repo carries the same two values.
- [x] `Makefile` — `dev-init` drops the hardcoded `DBUSER` append: the framework
      `init-local-env.sh` sources the materialized `etc/dev.conf` and relays
      `DBUSER`/`SSL_DIR` into `.env`, so the Makefile owns no dev values.
- [x] `composer.json` + `composer.lock` — re-pin `judijasa/php-daas-framework`
      → `699b55f` and `judijasa/ema` → `5341f0c` (both pushed); verified no old
      hash remains.
- [x] `etc/php-fpm-simox.conf.in` + `etc/php-fpm-simox.service.in` — renamed
      from `*.template`: the `.template` suffix is reserved for private-config
      shapes a human copies into a git-ignored `etc/<name>`, while these two are
      machine-rendered by `bin/deploy/server-side-post-deploy.sh` (the `web`
      step). `etc/` now holds only `.template` shapes.
- [x] `doc/system/machine-certs.md` — new: simox's cert values, the dev-machine
      mint/sign/install steps, the prod-host material and the ordering around
      first provision / account reconcile.
- [x] Doc consistency with the mechanism: `README.md` (cert prerequisite, X509
      gate, link to the new doc), `doc/system/deploy.md` (`SSL_DIR` in the env
      contract, `DEPLOY_PRIVATE_FILES`, `team.ini` without `subject`, the
      `dev.conf` relay), `doc/system/private-config.md` (`etc/dev.conf` row,
      required/optional files, X509 wording), `doc/system/service-accounts.md`
      (the three authentication layers), `etc/team.ini.template` and
      `etc/reuter.ini.template`.

### private config repo

- [x] `dev.conf` — new: `DBUSER=simox`, `SSL_DIR=~/.simox/ssl`.
- [x] `ema.conf` — new: `[default] ssl-ca = /etc/simox/ssl/ca.crt`.
- [x] `deploy.conf` — `DEPLOY_PRIVATE_FILES="reuter.ini ema.conf"` and
      `DEPLOY_SSL_DIR=/etc/simox/ssl`, with the projection comment updated.
- [x] `team.ini` — drop the stale `subject` key (and its comment): `gen-cert`
      derives the CN from the `hostname = ZeroTier-IP` entries, and the account
      is gated by `REQUIRE X509`, not by a subject.
- [x] `README.md` — the file table, the delivery/deployment text and the
      pre-deployment checklist (new step: TLS cert material).

### php_daas_framework / ema (upstream)

No changes: the mechanism landed there (framework `699b55f` + `bacff56`, ema
`5341f0c` + `2b64bf4`).

## Open items

- **Push the private config repo** — its required-file additions are local
  commits-to-be, so `make dev-init` / `make deploy` fail until they are pushed
  (the fetch resolves the private repo from its remote).
- **`SSL_DIR` in the php-fpm pool** — the website connects to `simo1` as `simox`
  over TCP from a php-fpm process that has no `.env`; once the accounts require
  X509 it needs `env[SSL_DIR]` in `etc/php-fpm-simox.conf.in` (replayed from
  `DEPLOY_SSL_DIR` by the `web` step), the same way `REUTER_INI` is. Not yet
  implemented.
- **First real apply** — the reconcile has only ever been dry-run verified; the
  first `gen-service-accounts` apply on `simo0`/`simo1` follows the framework +
  ema changes landing (see `doc/plans/2026-09-18-per-database-db-roles.md`).
  The hosts also need their cert material and the CA in place first (see
  `doc/system/machine-certs.md`), and an instance already provisioned without
  `ssl-ca` needs that line added to its `my.cnf` by hand.
- **Replication channel** — the `replication` account stays
  passwordless/host-pinned unless later certed.
- **Revocation** — a leaked machine cert cannot be revoked (no CRL); carried
  forward.
- **Client-side server verification** — out for v1 (client presents cert+key;
  server verifies); full mutual TLS is the recorded follow-up.
- **Re-pin ordering** — done: both upstream pins were already pushed when the
  re-pin ran.
