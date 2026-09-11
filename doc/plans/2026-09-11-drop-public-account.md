# Drop the `public` account — Plan & Progress

Date: 2026-09-11
Repos: simox (this repo).

## Decision

Collapse the two-account model from
`doc/plans/2026-09-10-simox-service-account-and-read-replica.md` into a single
service account: **`simox`**. Drop `public` entirely and point the website at
`simox` on the read-only replica `simo1`. The writer (`simo0`) and reader
(`simo1`) distinction is preserved by the **host pin**, not by the account
name: `simox` is `ALL` on `simo0` and `SELECT` on `simo1`, and it remains
passwordless (boundary = ZeroTier membership + source-IP host pin).

This supersedes the `public`-account half of
`doc/plans/2026-09-10-simox-service-account-and-read-replica.md`; everything
else in that plan (replica mechanism, `replication` transport account,
routing to `simo0`/`simo1`) is unchanged — except the reconcile's stale-drop,
which is generalized here (see Changes).

## Justification

The account name is **not a credential** here: both accounts are passwordless,
so the only enforcement is the DB-side `user@host` pin — an attacker on a
compromised host can connect under *any* username, and what stops them is
whether their source IP matches a pin.

- **`public` adds no grant restriction.** On `simo1` it has `SELECT`, identical
  to `simox`'s `SELECT` there; and `simo1` is `read_only=1` anyway.
- **`public`'s only unique value is its pin set** (`web` hosts). That matters
  only when web hosts are *disjoint* from `simox`'s pin set (`member` ∪
  `worker`). In the current topology `web == worker`, so the web host's IP is
  already in `simox`'s pin set and can reach `simo0` with `ALL PRIVILEGES`
  regardless of `public`.
- **Compromised-web-host escalation is already closed by the pin, or already
  open.** Everything an attacker needs is already on the web host: the
  `[simo0]` endpoint (in deployed `etc/reuter.ini`), the `simox` name (in the
  deployed code), and — when `web == worker` — an authorized IP. `public`
  changes none of that.
- **A future dedicated web server does not need `public` either.** It connects
  as `simox`, and the pin confines it to `simo1`: create
  `simox@<web-ip>` on `simo1` (`SELECT`) and *never* on `simo0`, so that IP
  reads `simo1` but fails auth entirely against `simo0`. This needs a small
  grammar extension (per-DB `@hosts`), tracked as an Open item.

The replica therefore exists for read scaling/availability, not as a
security boundary between the website and the primary — which is the honest
description of the current single-host topology.

## Config model (after)

| Account | Privileges | Host pin | Used by |
|---|---|---|---|
| `simox` | ALL on `simo0`, SELECT on `simo1` | team.ini member IPs ∪ machines.ini `worker` hosts | cron/indexer, member ad-hoc scripts, website (`public/`) |

- The website connects as `simox` to `simo1`; its host must be in the `simox`
  pin set (`worker`/`member`), which is automatic today (`web == worker`).
- The `web` tag in `machines.ini` remains only the Apache-traversal deploy
  step tag; it no longer feeds any account pin.
- The `replication` transport account is unchanged (bootstrap-created on the
  primary, not part of the reconcile).

## Changes

### simox (this repo)

- [x] `etc/service-users.sql` — remove the `public` block; update the policy
      header to a single `simox` account and a closed-world drop note.
- [x] `bin/gen-service-users` — drop the hardcoded `LEGACY_ACCOUNTS` in favor
      of a closed-world reconcile: enumerate every account and drop any not in
      the declared set ∪ a fixed allow-list (`root`, `mariadb.sys`,
      `replication`); create/grant the declared ones. The drop set is the
      union of declared accounts across all dbs (accounts are global in
      `mysql.user`; only the GRANT is per-db); update the header/usage text.
- [x] `etc/reuter.ini.template` — drop `PUBLIC_PASSWORD=` from `[simo1]`;
      remove `public` from the header comment.
- [x] `public/index.php`, `public/insight.php` — `connectAs('simo1','simox')`.
- [x] `etc/machines.ini.template` — `web` no longer feeds an account pin
      (Apache-traversal only); update wording.
- [x] `etc/deploy.conf` — `SIMOX_PASSWORD / PUBLIC_PASSWORD` → `SIMOX_PASSWORD`.
- [x] `doc/system/private-config.md` — `SIMOX_PASSWORD / PUBLIC_PASSWORD` →
      `SIMOX_PASSWORD`.
- [x] `README.md` — Service Accounts section: single `simox`; website reads
      `simo1` via `simox`.
- [x] `doc/system/replica-bootstrap.md` — "serves the website (`public`
      account)" → `simox`.
- [x] `doc/plans/2026-09-11-drop-public-account.md` — this doc.

## Open items

- **Per-DB `@hosts` grammar** — when a dedicated (non-worker) web server is
  added, express per-DB pins so `simox` on `simo1` allows the web host while
  `simox` on `simo0` does not. Today `@hosts` is account-level and applied to
  every `@db` line; an optional `@hosts` on the `@db` line (or a second
  `simox` block) is the likely shape. Tracked here for later, not needed now.
- **Reconcile drop of stale `public`** — verify `bin/gen-service-users` really
  removes a pre-existing `public@<ip>` on `simo1` after this change (the
  closed-world drop above), while leaving `root`, `mariadb.sys`, and
  `replication` untouched. Also confirm the general case: declaring a new
  account and then removing it yields create-then-drop, not an orphan.
- **Dev sandbox single account** — `ema sandbox` applies `srv/` and `pkg/*`
  as DDL-only (no users/grants), so the sandbox's account set is governed by
  `etc/service-users.sql` via `bin/gen-service-users`. After this drop the
  sandbox needs only `simox`; the still-pending dev-wiring of
  `gen-service-users` against the sandbox (2026-09-07 open item) should
  create just `simox`.
- **Team-member `DBUSER` → `simox`** — `etc/team.ini.template` still
  documents `init-local-env.sh` exporting the member's section name as
  `DBUSER` (README repeats it). With no per-member accounts, remote DB access
  must resolve `DBUSER` to the single `simox` account (the member IP is the
  pin, not a distinct account). Not yet changed — carried here.
