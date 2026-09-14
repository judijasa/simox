# Role-based host pins — Plan & Progress

Date: 2026-09-11
Repos: simox (this repo, consumer data); simox_cnf (../simox_cnf, private
       config); php_daas_framework (../php_daas_framework, upstream
       mechanism).

## Decision

Generalize the account-level host pin (one `@hosts` set, identical grants for
every host) into per-role pins backed by **MariaDB roles** — and move the
*mechanism* into the framework. `php_daas_framework` gains a generic
`bin/gen-service-accounts` that reads a consumer declaration (roles, grants,
account/source mapping) and reconciles the live instances against it. simox
keeps only the *data*: the `simox_*` role definitions, their grants, the
source → role → account mapping, and the host registries it already owns
(`etc/team.ini`, `etc/machines.ini`).

The account model is a simox choice, not a framework structure: simox uses one
shared `simox` account for all team members; a different consumer can declare
a different model (e.g. per-member accounts) with no framework change. Each
pin source owns a named role, a host carries the union of the roles for its
tags, and the reconcile grants that union to `simox@<ip>`. This makes the
physical layout a pure-config change: move `web`/`worker`/`db` tags across
hosts in `machines.ini` (or add/remove `team.ini` member IPs) and re-run the
reconcile; no code edits in either repo.

It supersedes the "Per-DB `@hosts` grammar" open item in
`doc/plans/2026-09-11-drop-public-account.md` and the consumer-owned
`bin/gen-service-users` script. `web` becomes a real pin source again — a
narrow one (`SELECT` on `simo1` only) — so `web == worker` (today) and
`web != worker` (a future dedicated web box) both fall out of the same
declaration. The `member` role is renamed `simox_member`; its full access
is unchanged, and the pin source stays `member` (team.ini).

See `php_daas_framework/doc/plans/2026-09-14-role-based-host-pins.md` for the
framework-side mechanism (reconcile algorithm, closed-world semantics, data
contract).

## Config model

| Source   | Role              | `simo0`        | `simo1` | From                               |
|----------|-------------------|----------------|---------|------------------------------------|
| `member` | `simox_member` | ALL PRIVILEGES | SELECT  | `etc/team.ini` member IPs          |
| `worker` | `simox_worker`    | ALL PRIVILEGES | SELECT  | `etc/machines.ini` `worker` tag    |
| `db`     | `simox_db`        | ALL PRIVILEGES | SELECT  | each `etc/machines.ini` `db:<name>` tag (declared as its own source) |
| `web`    | `simox_web`       | —              | SELECT  | `etc/machines.ini` `web` tag       |

`db:<name>` is no longer advisory: the `db` source pins every host carrying a
`db:<name>` tag, with the same grants as `worker`. A host carrying several tags
(e.g. `db:simo0, worker` + `web` today) gets the union of its roles. Roles are
additive; the reconcile never needs per-role privilege precedence.

The declaration simox ships (framework `srv/` package convention):

    // srv/roles-<GUID>/default.php
    $sources  = array('member'   => 'simox_member',
                      'worker'   => 'simox_worker',
                      'db:simo0' => 'simox_db',
                      'db:simo1' => 'simox_db',
                      'web'      => 'simox_web');
    $accounts = array('simox' => array('member', 'worker', 'db:simo0',
                                       'db:simo1', 'web'));

    -- srv/roles-<GUID>/upgrade.sql
    CREATE ROLE IF NOT EXISTS simox_member;
    CREATE ROLE IF NOT EXISTS simox_worker;
    CREATE ROLE IF NOT EXISTS simox_db;
    CREATE ROLE IF NOT EXISTS simox_web;

    -- srv/simo0.roles-<GUID>/upgrade.sql
    GRANT ALL PRIVILEGES ON {{dbname}}.* TO simox_member;
    GRANT ALL PRIVILEGES ON {{dbname}}.* TO simox_worker;
    GRANT ALL PRIVILEGES ON {{dbname}}.* TO simox_db;

    -- srv/simo1.roles-<GUID>/upgrade.sql
    GRANT SELECT ON {{dbname}}.* TO simox_member;
    GRANT SELECT ON {{dbname}}.* TO simox_worker;
    GRANT SELECT ON {{dbname}}.* TO simox_db;
    GRANT SELECT ON {{dbname}}.* TO simox_web;

`$sources` maps each source to its role; `$accounts` maps each account to its
sources. A role with no `GRANT` on a database means its account is not wanted
there — so `simox_web`'s absence from `simo0` drops `simox` on `simo0` for a
dedicated (non-worker) web host. Today `web == worker`, so the `simo0` grant
still arrives via the `worker`/`db` roles.

## Changes

### php_daas_framework (../php_daas_framework)

- [x] `bin/gen-service-accounts` + data contract — tracked in
      `php_daas_framework/doc/plans/2026-09-14-role-based-host-pins.md`.

### simox (this repo)

- [x] `srv/roles-D0YRR7WII6V1XDZR/` (new) — `simox_*` role definitions +
      `$sources`/`$accounts` declaration (replaces `etc/service-users.sql`).
- [x] `srv/simo0.roles-D0TVLE3YJCFE1A8U/` + `srv/simo1.roles-D0A3HGW7BHWSVCUQ/`
      (new) — per-database grants (see Config model).
- [x] `etc/service-users.sql` — removed (replaced by the `srv/` declaration).
- [x] `bin/gen-service-users` — removed (mechanism moved to the framework's
      `gen-service-accounts`).
- [x] `etc/machines.ini.template` — document `db` (any `db:<name>`) and `web`
      as role-pin sources.
- [x] `etc/team.ini.template`, `etc/reuter.ini.template`, `etc/deploy.conf`,
      `doc/system/private-config.md`, `doc/system/replica-bootstrap.md` —
      drop the `bin/gen-service-users` / `etc/service-users.sql` references.
- [x] `README.md` — Service Accounts section: source → role → grant table;
      state that the reconcile uses roles and revokes excess roles and direct
      grants; point at the framework doc.
- [x] `composer.json` / `composer.lock` — pin bump to pick up
      `gen-service-accounts`.
- [x] `doc/plans/2026-09-11-role-based-host-pins.md` — this doc.

### simox_cnf (../simox_cnf)

- [x] `machines.ini` — header comment: source → role → grant mapping; removing
      a tag revokes the corresponding role.
- [x] `team.ini` — header comment: the `member` source pins `simox_member`;
      the framework reconcile replaces `bin/gen-service-users`.
- [x] `reuter.ini` — header comment: the framework reconcile manages the
      account and its roles but never the `<ACCOUNT>_PASSWORD` keys.

## Open items

- **Live apply** — the declaration is verified by a `gen-service-accounts
  -n` dry-run only (it warns that the closed-world revoke/drop set is omitted
  without live state); the first real apply on `simo0`/`simo1`, and with it
  the observed drop of a `web`-only host's `simox` on `simo0`, is pending.
- **`simox_web` on `simo0`** — absent by design (web is read-only on `simo1`);
  the per-instance drop of `simox@<web-ip>` on `simo0` depends on the
  framework's "role with no grant on a db ⇒ not wanted there" rule.
- **Member removal** — with one shared `simox` account, removing a member IP
  from `etc/team.ini` revokes that pin on the next reconcile (there is no
  per-member account to drop).
