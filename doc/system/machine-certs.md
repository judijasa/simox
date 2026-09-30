# Machine certs and the X509 service account

Date: 2026-09-29
Scope: simox's client-certificate values and the operator steps around them —
the per-machine certificate the `require: 'X509'` service account demands, the
CA the prod instances verify it against, and where each value comes from. The
mechanism is not simox's: the framework owns the client half (the `SSL_DIR` read
in the app layer, the `gen-cert` CLI, the `etc/dev.conf` sourcing) and `ema` the
server half (`ssl-ca` in each instance's `[mysqld]`). The generic contract is in
the framework's
[doc/system/machine-certs.md](https://github.com/judijasa/php_daas_framework/blob/main/doc/system/machine-certs.md);
this doc carries simox's data and the order to apply it in.

## Quick setup

```bash
# 1. dev machine, once: record this machine's hostname -> its ZeroTier IP in the
#    private etc/team.ini (materialized by make dev-init), and set SSL_DIR in the
#    private etc/dev.conf (template: etc/dev.conf.template):
#      export SSL_DIR=~/.simox/ssl

# 2. dev machine, repo root (nix develop): mint the key + CSR. The CN is derived,
#    never typed — the team.ini hostname whose IP is one of this machine's own.
gen-cert

# 3. offline CA machine: sign the CSR (the CA key never leaves that machine)
openssl x509 -req -in client.csr -CA simox-ca.crt -CAkey simox-ca.key \
    -CAcreateserial -days 365 -out client.crt

# 4. dev machine, repo root: install the signed cert next to the local key and
#    write the ~/.my.cnf.d/simox.cnf drop-in (mysql CLI over TCP)
gen-cert install client.crt

# 5. prod host, once: install the CA and the host's own certificate out of band
#    (never through a deploy), then reconcile the accounts
install -m 0644 simox-ca.crt        /etc/simox/ssl/ca.crt
install -m 0644 <host>-client.crt   /etc/simox/ssl/client.crt
install -m 0600 <host>-client.key   /etc/simox/ssl/client.key
gen-service-accounts <db> -n        # review: REQUIRE X509 on every account
gen-service-accounts <db>           # apply
```

## Model

One client certificate per **machine**, not per account: the same `client.crt`
is presented for every service account that machine connects as. `REQUIRE X509`
is a membership gate — it checks only that the presented certificate chains to a
CA the server trusts, and never matches the subject. Identity stays what it
already was: the source-IP host pin (`'simox'@'<ip>'`, derived by
`gen-service-accounts` from `etc/team.ini` / `etc/machines.ini`). The
certificate's CN is an audit identity (which machine connected), visible in the
instance's audit log — not an authorization check.

Why the extra gate at all: the pin alone is satisfied by any peer on the trusted
network that can claim the address, so it authenticates the network position, not
the machine. A certificate adds a private key the peer must hold.

The account stays **passwordless** (`SIMOX_PASSWORD=` empty in `reuter.ini`):
the cert is not a replacement for a password, it is the condition under which a
passwordless account is allowed to connect. Both halves must be in place — the
server-side `ssl-ca` (or `REQUIRE X509` authenticates nobody) and a cert on the
client (or the account refuses it).

Per-project isolation comes from simox running **its own CA**, not from the
subject: certificates signed by another project's CA do not chain to simox's.

## Values

| Value | Where it lives | Consumed by |
|---|---|---|
| `require: 'X509'` | `pkg/roles-D0YRR7WII6V1XDZR/default.php` (committed) | framework `gen-service-accounts` → `REQUIRE X509` on every account |
| dev machine `SSL_DIR` (`~/.simox/ssl`) | private `etc/dev.conf` (template: `etc/dev.conf.template`) | `gen-cert` (mint + install) |
| host `SSL_DIR` (`/etc/simox/ssl`) | private `etc/deploy.conf` `DEPLOY_SSL_DIR` → `gen-env` → host `.env` | the app layer (`Database::connectAs()`), over TCP |
| host CA path (`/etc/simox/ssl/ca.crt`) | private `etc/ema.conf` `[default] ssl-ca` (template: `etc/ema.conf.template`) | `ema`, into each instance's `[mysqld]` |

The name is the same on both sides — `SSL_DIR` — because it is the same
mechanism: one directory holding `client.crt` + `client.key`. `DEPLOY_SSL_DIR` is
the only path from the deploy machine's `deploy.conf` to that value; the
certificate bytes themselves never travel through a deploy or a repo (only the
public cert returns from the CA to the operator, who installs it on the host).

`etc/ema.conf`'s `ssl-ca` is read at **first provision only**: `ema` writes it
into the instance's `<db>/my.cnf` `[mysqld]` when it creates that instance and
never rewrites an existing one. On an instance already provisioned without it,
the path has to be added to that `my.cnf` by hand (and the instance restarted) —
see `doc/system/add-database.md` for the instance layout.

## Dev machine

`make dev-init` does **not** touch certificates: the cert needs an operator
holding the CA key, so it stays a deliberate, occasionally-run step (it matters
only once a machine must reach a database whose accounts require X509 — one
machine in the network needs no certificate at all). What `dev-init` does is
materialize the values the step reads: `etc/dev.conf` (`SSL_DIR`, relayed into
`.env` by the framework's `init-local-env.sh`) and `etc/team.ini` (the
hostname → IP registry `gen-cert` matches this machine against).

`gen-cert` (from the repo root, inside `nix develop`) mints `client.key` +
`client.csr` in the current directory; the CSR's CN is the `etc/team.ini`
hostname whose IP equals one of this machine's local addresses, so the machine
must be on the overlay before it can be issued a certificate. `gen-cert install
<client.crt>` then places the returned cert and the local key into `$SSL_DIR`
and writes the tagged `~/.my.cnf.d/simox.cnf` drop-in, so the `mysql` CLI
presents the certificate over TCP as well. Both the key and the drop-in are
idempotent — re-running the generator replaces the managed region and nothing
else.

The dev **sandbox** is unaffected: it connects as `root` over the local unix
socket, where no TLS is applied, so a dev machine needs no certificate for local
work (`EMA_TARGET=sandbox`).

## Prod hosts

A host needs its own certificate to connect to any instance whose accounts
require X509 — including a host connecting to a database it does not itself
serve. The material is installed out of band under `/etc/simox/ssl` (the CA, the
host's `client.crt` and `client.key`), never through the repo or the deploy: the
private key must not travel, and the deploy swaps the repo directory anyway.
`DEPLOY_SSL_DIR` is what tells the deployed app layer where that directory is;
`etc/ema.conf`'s `ssl-ca` tells each instance on the host where the CA is. Both
are deploy-machine values, so both arrive through `etc/deploy.conf` (see
[private-config.md](private-config.md)).

Order matters: install the CA before the instances are provisioned (the
`ssl-ca` line is written at first provision only), and the client certificate
before the accounts are reconciled with `REQUIRE X509` — otherwise the deploy's
own DB check (`db-check`, warn-only) stops connecting.

## Out of scope

- **Revocation** — no CRL is consumed anywhere, so a leaked machine certificate
  cannot be revoked. The remedy is re-issuing the CA, i.e. rotating every
  certificate in the network.
- **Client-side server verification** — for v1 the client presents its
  certificate and the server verifies it; the client does not verify the
  server's. Full mutual TLS needs CA-signed server certificates.
- **The `replication` account** — stays passwordless and host-pinned to the
  replica host, unless it is later certed as well.
