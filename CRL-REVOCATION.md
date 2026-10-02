# CRL revocation for TLS client certs — Plan & Progress

Repos: simox (this repo); the private config repo; php_daas_framework (upstream);
ema (upstream)

This is the cross-repo metaplan. It supersedes the "Revocation — carried
forward" open item in `doc/plans/2026-09-29-require-x509-cert-values.md`; on
each repo below now has its own dated plan (`doc/plans/2026-10-01-*`).

## Decision

A leaked machine client certificate is currently unrevocable: `REQUIRE X509` +
an offline CA + no CRL means the only remedy is re-issuing the CA (rotating
every certificate). Add a server-side CRL so a leaked certificate can be
revoked without touching the rest of the network.

Revocation is one-directional for v1 — the DB server verifies client certs
against a CRL; the client half (`gen-cert`, the app layer, the
`~/.my.cnf.d` drop-in) is unchanged. The prerequisite that makes a CRL possible
is that the CA becomes a real `openssl ca` setup: the current ad-hoc signing
(`openssl x509 -req -CAcreateserial`) keeps no `index.txt`, so nothing can be
revoked or listed. `openssl ca` maintains that ledger, and `-revoke` /
`-gencrl` then produce the CRL. All of it is standard `openssl` — no repo
scripts — so the CA-side workflow is consumer data and lives in the private
config repo's `doc/system/`.

Mechanism:

- **CA (offline):** a `simox-ca/` directory — `openssl.cnf`
  (`database = index.txt`, `serial`, `crlnumber`, `default_crl_days = 30`,
  `default_md = sha256`), `index.txt` (the revocation ledger), `newcerts/`,
  plus the existing `ca.crt`/`ca.key`. Signing becomes
  `openssl ca -config openssl.cnf -batch -in client.csr -out client.crt`.
- **Revoke:** `openssl x509 -in <leaked>.crt -noout -serial` (or grep
  `index.txt` by CN) → `openssl ca -revoke <serial>` →
  `openssl ca -gencrl -out crl.pem`. Revocation is by serial, not CN.
- **Server:** ema reads a host-level `ssl-crl` beside `ssl-ca` and emits
  `ssl-crl = <path>` into each instance's `[mysqld]`. `ssl-crl` requires
  `ssl-ca` (a CRL with no CA is meaningless).
- **Distribution:** out-of-band (a public, signed file) to
  `/etc/simox/ssl/crl.pem`, beside `ca.crt`.
- **Apply:** restart the instance. `ssl-crl` is read only at startup; `FLUSH SSL`
  reloads cert/key/CA but not the CRL. A restart on revoke is accepted (rare).
- **Renewal:** regenerate the CRL on revoke and on a ~30-day cadence, then
  distribute + restart. An expired CRL fails all client verification.

Out of scope: client-side server verification (mutual TLS) and its CRL — still
deferred.

## Changes

### ema (upstream)

- [x] `ema` — read `_EMA_SSL_CRL` (env `EMA_SSL_CRL` override) from the host
      config beside `_EMA_SSL_CA`; three-state resolution (absent → no line;
      empty override → clear; present → absolute path). Loud error when
      `ssl-crl` is set without `ssl-ca`.
- [x] `ema` — `_prod_write_conf`: existence check for the CRL file at create
      time, and emit `ssl-crl = <path>` into `[mysqld]` beside `ssl-ca`.
- [x] `etc/ema.default.conf` — document the `ssl-crl` key (shape); leave the
      default absent so a plain checkout stays CRL-free.
- [x] `doc/system/host-ssl-ca.md` — extend (or rename) to cover `ssl-crl`
      (resolution, requires-`ssl-ca`, startup-only/restart note).

### php_daas_framework (upstream)

- [x] `doc/system/machine-certs.md` — the "offline CA step" no longer embeds the
      `openssl x509 -req` sign command; it points at the consumer's CA/CRL
      workflow (the private config repo's `doc/system/`). Client code
      (`gen-cert`, `Database`) is unchanged.

### simox (this repo)

- [x] `etc/ema.default.conf` — add `ssl-crl = /etc/ssl/crl.pem` beside the
      committed `ssl-ca`.
- [x] `etc/ema.conf` override — note the `ssl-crl` key (diverge only); the
      committed default's comment documents it, and the private repo's `ema.conf`
      carries the commented override (`/etc/simox/ssl/crl.pem`).
- [x] `doc/system/machine-certs.md` — add the CRL half: the
      `/etc/simox/ssl/crl.pem` path, the restart-on-apply note, and a pointer to
      the CA workflow in the private config repo.

### private config repo

- [x] `doc/system/cert-authority.md` — new: the standard `openssl ca`
      sign/revoke/gencrl workflow, the `openssl.cnf` shape, distribution to the
      DB hosts, and the restart step.
- [x] `ema.conf` — add a commented `ssl-crl` override line (diverge only).
- [x] `README.md` — the file table and the private-docs table row for the new CA
      workflow doc.

## Open items

- **Hot reload**: none — `ssl-crl` is startup-only and `FLUSH SSL` does not
  reload the CRL; restart-on-apply is accepted.
- **CRL renewal cadence**: `default_crl_days = 30`; renewal must be on an
  operator schedule (the CA machine is offline, so no cron there) — otherwise an
  expired CRL rejects every client.
- **Backfill**: none expected — v1's real issuance has not run, so adopting
  `openssl ca` before issuance leaves `index.txt` empty to start.
- **Replication account**: unchanged; if it is later certed it is covered by the
  same server CRL (per-server, not per-account).
- **Validation**: the dev sandbox is plain TCP (no `ssl-ca`), so CRL behavior
  must be verified against a prod-like instance, not the sandbox.
