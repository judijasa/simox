# CRL revocation — Plan & Progress

Date: 2026-10-01
Repos: simox (this repo); private config repo; php_daas_framework (upstream);
ema (upstream)

## Decision

simox adds a server-side CRL so a leaked client certificate can be revoked
without re-issuing the CA. The committed `etc/ema.default.conf` ships the
generic `ssl-crl = /etc/ssl/crl.pem` (beside `ssl-ca = /etc/ssl/ca.crt`),
overridden by the private `etc/ema.conf` to the actual `/etc/simox/ssl/crl.pem`
(and `/etc/simox/ssl/ca.crt`); ema writes it into each instance's `[mysqld]` at
first provision. The CRL itself is consumer data:
the CA workflow (standard `openssl ca`, revoke-by-serial, `-gencrl`) is
documented in the private config repo's `doc/system/cert-authority.md`, and the
file is installed out of band — never through the repo or a deploy. Applying a
new CRL is a restart event (MariaDB reads `ssl-crl` at startup only).

## Changes

### simox (this repo)

- [x] `etc/ema.default.conf` — add the generic
      `[default] ssl-crl = /etc/ssl/crl.pem` beside `ssl-ca`, documenting the
      override and the requires-`ssl-ca` rule.
- [x] (doc) — `doc/system/machine-certs.md`: keep the actual server CA path at
      `/etc/simox/ssl/ca.crt`, add the CRL value/quick-setup/prod-host steps, a
      `## Revocation (CRL)` section replacing the "re-issue the CA" out-of-scope
      bullet, and correct the dev `SSL_DIR` references to the generic `~/.ssl`
      default + `~/.simox/ssl` override.

## Open items

- **Restart on apply** — a new CRL takes effect only after each instance is
  restarted (startup-only; `FLUSH SSL` does not reload it); a rare, deliberate
  event.
- **CRL renewal** — the CRL lapses (`default_crl_days = 30`) and is regenerated
  on the offline CA machine; an operator-scheduled task.
