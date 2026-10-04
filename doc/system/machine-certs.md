# Machine certs and the X509 service account

Date: 2026-09-29
Scope: the `require: 'X509'` membership gate — the per-machine client certificate
the service account demands, the CA the prod instances verify it against, and
the config keys that name each value. The mechanism is not this repo's: the
framework owns the client half (the `SSL_DIR` read in the app layer, the
`gen-cert` CLI, the `etc/dev.conf` sourcing) and `ema` the server half (`ssl-ca`
in each instance's `[mysqld]`). The generic contract is in the framework's
[doc/system/machine-certs.md](https://github.com/judijasa/php_daas_framework/blob/main/doc/system/machine-certs.md);
this doc keeps the model and the generic shape.

## Quick setup

The shape is the same on every machine:

```bash
gen-cert                       # dev machine: mint key + CSR (CN derived from etc/team.ini)
#   -> offline CA machine: sign the CSR (openssl ca); carry back only the .crt
gen-cert install client.crt    # dev machine: install cert next to the key + ~/.my.cnf.d/<app>.cnf drop-in

# prod host, once, out of band (never through a deploy): the host's own client
# pair (client.crt + client.key) and the server-side anchors (ca.crt + crl.pem)

gen-service-accounts <db> -n   # review: REQUIRE X509 on every account
gen-service-accounts <db>      # apply
```

## Model

One client certificate per **machine**, not per account: the same `client.crt`
is presented for every service account that machine connects as. `REQUIRE X509`
is a membership gate — it checks only that the presented certificate chains to a
CA the server trusts, and never matches the subject. Identity stays what it
already was: the source-IP host pin (`'<account>'@'<ip>'`, derived by
`gen-service-accounts` from `etc/team.ini` / `etc/machines.ini`). The
certificate's CN is an audit identity (which machine connected), visible in the
instance's audit log — not an authorization check.

Why the extra gate at all: the pin alone is satisfied by any peer on the trusted
network that can claim the address, so it authenticates the network position, not
the machine. A certificate adds a private key the peer must hold.

The account stays **passwordless** (`<ACCOUNT>_PASSWORD=` empty in `reuter.ini`):
the cert is not a replacement for a password, it is the condition under which a
passwordless account is allowed to connect. Both halves must be in place — the
server-side `ssl-ca` (or `REQUIRE X509` authenticates nobody) and a cert on the
client (or the account refuses it).

Per-project isolation comes from each project running **its own CA**, not from
the subject: certificates signed by another project's CA do not chain to this
project's.

## Values

| Value | Where it lives | Consumed by |
|---|---|---|
| `require: 'X509'` | `pkg/roles-<GUID>/default.php` (committed) | framework `gen-service-accounts` → `REQUIRE X509` on every account |
| dev machine `SSL_DIR` | private `etc/dev.conf` override (committed `etc/dev.default.conf` ships the generic `~/.ssl`) | `gen-cert` (mint + install) |
| host `SSL_DIR` | private `etc/deploy.conf` `DEPLOY_SSL_DIR` → `gen-env` → host `.env` | the app layer (`Database::connectAs()`), over TCP |
| host CA path | private `etc/ema.conf` override (committed `etc/ema.default.conf` `ssl-ca` ships the generic `/etc/ssl/ca.crt`) | `ema`, into each instance's `[mysqld]` |
| host CRL path | private `etc/ema.conf` override (committed `etc/ema.default.conf` `ssl-crl` ships the generic `/etc/ssl/crl.pem`) | `ema`, into each instance's `[mysqld]` |

The name is the same on both sides — `SSL_DIR` — because it is the same
mechanism: one directory holding `client.crt` + `client.key`. `DEPLOY_SSL_DIR` is
the only path from the deploy machine's `deploy.conf` to that value; the
certificate bytes themselves never travel through a deploy or a repo (only the
public cert returns from the CA to the operator, who installs it on the host).

## Dev machine

`make dev-init` does **not** touch certificates: the cert needs an operator
holding the CA key, so it stays a deliberate, occasionally-run step. What
`dev-init` does is materialize the values the step reads — `etc/team.ini` (the
hostname → IP registry `gen-cert` matches this machine against) and, when a
machine diverges from the committed `etc/dev.default.conf`, the optional
`etc/dev.conf` override (`SSL_DIR`).

`gen-cert` (from the repo root, inside `nix develop`) mints `client.key` +
`client.csr`; the CSR's CN is the `etc/team.ini` hostname whose IP equals one of
this machine's local addresses, so the machine must be on the overlay before it
can be issued a certificate. `gen-cert install <client.crt>` places the returned
cert and the local key into `$SSL_DIR` and writes the `~/.my.cnf.d/<app>.cnf`
drop-in, so the `mysql` CLI presents the certificate over TCP as well. Both are
idempotent.

The dev **sandbox** is unaffected: it connects as `root` over the local unix
socket, where no TLS is applied (`EMA_TARGET=sandbox`).

## Prod hosts

A host needs its own certificate to connect to any instance whose accounts
require X509 — including a database it does not itself serve. The material is
installed out of band, never through the repo or the deploy (the private key
must not travel, and the deploy swaps the repo directory anyway). The four files
are the host's client pair (`client.crt` + `client.key`, under the host `SSL_DIR`)
and the server-side anchors (`ca.crt` + `crl.pem`, the `etc/ema.conf`
`ssl-ca`/`ssl-crl` paths).

Order matters at the mechanism level: the anchors must be in place before the
instance is first provisioned (`ema` writes `ssl-ca`/`ssl-crl` at first provision
only), and the client pair before the accounts are reconciled with
`REQUIRE X509` (otherwise the deploy's own `db-check`, warn-only, stops
connecting).

## Revocation (CRL)

A leaked machine certificate is revoked through a CRL (Certificate Revocation
List), not by re-issuing the CA: the offline CA's standard `openssl ca` workflow
(`-revoke <serial>`, then `-gencrl`) emits it. The CRL is a server-side trust
anchor beside the CA — the `etc/ema.conf` `ssl-crl` path, written into each
instance's `[mysqld]` at first provision — and is read at startup only, so a new
CRL takes effect on restart (`FLUSH SSL` does not reload it).

## Out of scope

- **Revocation mechanics** — the CRL is generated on the offline CA and
  installed out of band (above); automated renewal or distribution stays out of
  scope — it is a deliberate, operator-driven event.
- **Client-side server verification** — for v1 the client presents its
  certificate and the server verifies it; the client does not verify the
  server's. Full mutual TLS needs CA-signed server certificates.
- **The `replication` account** — stays passwordless and host-pinned to the
  replica host, unless it is later certed as well.
