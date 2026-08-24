# One MariaDB Instance per Project — Plan & Progress

## Decision

Keep **one isolated MariaDB instance (datadir) per project** — the model
`php_daas_framework` already provisions — instead of a shared system instance
with grant-level isolation. Rationale:

- **Process-level isolation**: each project owns its daemon, memory budget
  (`innodb_buffer_pool_size`), binlogs, log files and root — a runaway query or
  operator mistake cannot touch another project's data.
- **Fits the existing framework contract**: `mariadb-install-db --datadir=...`
  per project is already the mechanism; nothing about the deploy flow changes.
- Dev sandbox already works this way (private socket, `--skip-networking`).

**Accepted cost:** N daemons = N sockets/ports, N systemd units, N log files,
N upgrades. The conflicts this introduces on a shared host, and their
avoidance, are the subject of this plan.

**Design decisions (2026-08-20):**

1. **Instance identity is derived by convention** from a single base dir —
   `DEPLOY_DB_BASE=/var/lib/<proj>/mariadb` in the committed `etc/deploy.conf`
   — mirroring dev's existing `$PWD/var/mariadb/{data,mysql.sock,mysql.pid}`
   layout. `etc/reuter.ini` stays **pure connectivity** (SERVER/PORT/DBNAME/
   credentials); it is gitignored, machine-specific and lives outside the repo
   in prod (`/etc/<proj>/reuter.ini`), so it must not carry infrastructure
   identity.
2. **Prod daemon lifecycle is systemd's job**: a framework-shipped template
   unit `mariadb@<proj>` (with a per-project `--defaults-file`), installed and
   `systemctl enable --now`'d once by `deploy --init`. "Start only once" is
   `enable --now` semantics — durable across reboots, never duplicated by
   re-deploys.
3. **`ema` is untouched.** No `ema server` subcommand: daemon management is a
   systemd/provisioning concern, not a schema-package-manager one. The dev
   sandbox keeps `init-cluster.sh` / `shell-enter.sh`.
4. **Runtime socket pin flows via `.env` `MYSQL_*`** (as in dev): `gen-env`
   emits `MYSQL_DATA_DIR`/`MYSQL_UNIX_PORT`/`MYSQL_PID_FILE` into prod `.env`.

## Current state (start of session)

- Framework `bin/provision.sh` (generic, runs as root via `deploy --init`):
  `mkdir -p "$DEPLOY_DB_DATA_DIR"` + `chown PROD_USER`, then, if empty,
  `mariadb-install-db --datadir="$DEPLOY_DB_DATA_DIR" --user="$PROD_USER"`.
  **Initializes the datadir but never starts the daemon.**
- simox `etc/deploy.conf` (committed): `DEPLOY_DB_DATA_DIR=/var/lib/simox/mariadb/data`.
- **Prod daemon start is an undocumented manual step** — nothing in the repo
  starts `mysqld` in production; a gap.
- **Runtime socket gap:** prod `.env` (from `etc/env.prod`) has no
  `MYSQL_UNIX_PORT` — `Database.php` connects via `host=localhost` → PDO's
  *default* socket. A per-project instance must either export `MYSQL_*` into
  prod `.env` or own the default socket. The plan closes this via `gen-env`.
  *(2026-08-24: `etc/env.prod` merged into `etc/deploy.conf`; `gen-env` now
  emits `MYSQL_*` on the database host only — `$DEPLOY_DB_BASE/mysql.sock`
  present.)*
- Dev: framework `init-cluster.sh <data-dir> <pid-file> <socket>` +
  `shell-enter.sh` resume; `mysqld --skip-networking` on a private socket under
  `var/mariadb/`; socket/pid/datadir come from `.env` (`MYSQL_*`).
- Cron (`CRON_FILE=/etc/cron.d/simo-orchestrator`, `CRON_USER=root`) is
  installed by `bin/deploy/post-nix.sh`, which runs **after** `--init`
  provisioning — so a daemon started during provisioning is up before any cron
  job can run.

## Conflict analysis (background for the "avoidance" requirements)

When N per-project instances share one host, each `mysqld` must own its full
runtime identity. The Debian defaults collide:

| # | Conflict | Symptom | Avoid |
|---|---|---|---|
| 1 | TCP port 3306 | `Bind on TCP/IP port: Address already in use` (distro `mariadb.service` or another project) | per-project `port` (`DEPLOY_DB_PORT`, required on DB hosts) |
| 2 | Default socket + pid (`/run/mysqld/*.sock`, `*.pid`) | second daemon fails; `mariadb` CLI silently hits the wrong instance | per-project `socket` + `pid-file` |
| 3 | Global `/etc/mysql/` includes (`!includedir /etc/mysql/mariadb.conf.d/`) | even a "custom" datadir inherits distro paths → triggers 1/2 | `--no-defaults` or dedicated `--defaults-file=/etc/<proj>/my.cnf` |
| 4 | Error log (default `/var/log/mysql/error.log`) | instances interleave/lock one file | per-project `log-error` (+ logrotate) |
| 5 | AppArmor (Debian/Ubuntu) | default `usr.sbin.mariadbd` profile only permits `/var/lib/mysql/**`; datadir at `/var/lib/<proj>/...` is DENIED at startup | per-project AppArmor profile, or disable profile when no distro instance |
| 6 | Daemon lifecycle | distro `mariadb.service` enabled at boot starts a second instance; `mysql_upgrade`/package upgrades only touch the distro datadir | one `mariadb@<proj>` systemd template unit per project; disable distro service |

## Design

### 1. Instance identity: one base dir + convention (deploy.conf)

- `etc/deploy.conf` carries `DEPLOY_DB_BASE=/var/lib/<proj>/mariadb`
  (committed, deployed with the repo — the same file `deploy`/provision
  already read).
- The framework derives, by fixed convention (no per-project code):
  - datadir = `$DEPLOY_DB_BASE/data`
  - socket  = `$DEPLOY_DB_BASE/mysql.sock`
  - pid     = `$DEPLOY_DB_BASE/mysql.pid`
- Identical shape to dev (`$PWD/var/mariadb/...`) — one convention, two roots.
- `etc/reuter.ini` is untouched. Runtime connectivity reaches the instance via
  `MYSQL_UNIX_PORT` in `.env`, exactly like dev.

### 2. Prod daemon: systemd template unit, enabled once at `deploy --init`

`deploy --init` sequence (ordering guarantee: cron install in `post-nix.sh`
runs last, so every newly created cron job finds the DB up):

1. framework `bin/provision.sh`: assert `PROD_USER` → create dirs
   (`DEPLOY_DB_BASE` + `data`/`log`) → `mariadb-install-db --datadir=...`
   (if empty; `--user=$PROD_USER`).
2. provision generates the per-project defaults file
   `/etc/<proj>/my.cnf` from the derived paths (`datadir`, `socket`,
   `pid-file`, `log-error`, `skip-networking` — see Open decisions) — this
   shields the instance from the global `/etc/mysql/` includes (conflict #3).
3. provision installs the framework-shipped template unit
   `mariadb@<proj>.service` (`--defaults-file=/etc/%i/my.cnf`; the unit runs
   as root and the daemon drops privileges via `user = $PROD_USER` in the
   defaults file — a systemd `User=` line cannot be parameterized per
   template instance) and runs `systemctl enable --now mariadb@<proj>`.
4. consumer `DEPLOY_INIT_CMD` (simox: `provision-extra.sh`) → nix →
   `post-nix.sh` (gen-env emits `MYSQL_*` into prod `.env`, then cron).

Re-deploys (non-init) never touch the unit: "start once" holds by construction.

### 3. Conflict detection (provision-time preflight + `ExecStartPre`)

Before enabling/binding, a small preflight (provision-time; optionally also
`ExecStartPre` — see Open decisions):

- **Fatal** (refuse to proceed): configured socket path is a live socket;
  configured TCP port already listening; datadir non-empty but not ours (no
  matching pid-file). **Messages stay generic — no pid/owner disclosure**:
  `socket <path> already taken` (provisioning logs may be read by more than
  the operator; revealing which process/owner holds the path leaks
  information).
- **Warning + auto-clean**: stale pid-file (pid not alive) — remove; socket
  file with no live listener — unlink; then proceed.
- **Idempotent skip**: our pid is alive and answers
  `mysqladmin ping --socket=...` — "already running", exit 0.

Note: mysqld itself already refuses to bind a taken socket/port — the preflight
exists for clear, safe diagnostics, not for correctness.

### 4. AppArmor (Debian/Ubuntu)

The distro `usr.sbin.mariadbd` profile only permits `/var/lib/mysql/**`; a
datadir at `/var/lib/<proj>/mariadb` is DENIED at startup. Approach is an open
decision (per-project profile vs documented disable); without it, the first
`deploy --init` on Debian fails at daemon start.

## Phases

### Phase 0 — This document — DONE

### Phase 1 — Framework provisioning (repo: `../php_daas_framework`) — DONE (2026-08-20)

- [x] `bin/provision.sh`: derives paths from `DEPLOY_DB_BASE` (datadir/socket/
      pid by convention); writes the per-project defaults file
      `/etc/<instance>/my.cnf` (always `port = $DEPLOY_DB_PORT` and
      `bind-address`; `DEPLOY_DB_PORT` is required on DB hosts); installs the `mariadb@.service` template unit
      (`--defaults-file=/etc/%i/my.cnf`, daemon drops privileges via
      `user = $PROD_USER` in the defaults file); `systemctl enable --now`.
      Idempotent: skips when the unit is already active; adopts a running
      instance (enable, no start).
- [x] Preflight conflict check (per Design #3): fatal on a live socket or TCP
      port with generic messages (`socket <path> already taken`,
      `TCP port <n> already taken`); stale pid-file/socket cleanup after
      crashes; already-running skip. Provision-time only (no `ExecStartPre` —
      see Open decisions #3).
- [ ] AppArmor: per-project profile or documented disable — **pending**, needs
      a Debian host (Open decisions #2).

**Validation (2026-08-20):** sandbox harness
(`/home/juan/.deepseek/tmp/simox/test-provision.sh`) with REAL mariadb
binaries (nix dev shell `mariadb-server-11.8.8`) + stubbed `systemctl`/`ss`.
7 scenarios / 27 checks, all PASS: fresh init+start, legacy-run adoption,
idempotent skip, foreign-live-socket fatal, stale pid/socket cleanup,
TCP-port-conflict fatal, port-enabled config (no `skip-networking`).

### Phase 2 — simox config (repo: simox) — DONE (2026-08-20)

- [x] `etc/deploy.conf`: `DEPLOY_DB_DATA_DIR` replaced by `DEPLOY_DB_BASE`
      (`/var/lib/simox/mariadb`) + explicit `DEPLOY_DB_INSTANCE=simox`.
- [x] `etc/env.prod`: emits `MYSQL_DATA_DIR`/`MYSQL_UNIX_PORT`/
      `MYSQL_PID_FILE` into prod `.env` (closes the runtime socket gap).
      *(2026-08-24: `etc/env.prod` deleted; `gen-env` now projects these from
      `etc/deploy.conf`, DB host only.)*

### Phase 3 — Docs — DONE (2026-08-20)

- [x] Framework README: "Multiple MariaDB instances on one server" section
      (conflict table + avoidance recipe) under "Deploying a consumer
      project"; `deploy.conf` table rows updated (`DEPLOY_DB_BASE`,
      `DEPLOY_DB_INSTANCE`, `DEPLOY_DB_PORT`).
- [x] simox README: prod DB instance is systemd-managed (`mariadb@simox`),
      never started manually; `.env` bullet mentions `MYSQL_*`.

### Phase 4 — Validation (staging)

- [ ] Two framework consumers on one staging host, `deploy --init` each:
      both instances up, no 3306/socket/pid conflicts.
- [ ] Re-deploy (non-init): units untouched (start-once holds).
- [ ] Reboot: daemons come back via systemd; cron jobs connect.
- [ ] Conflict drill: enable distro `mariadb.service` → next `deploy --init`
      fails loudly with the generic fatal message (no pid/owner in output).
      **Requires a real Debian host** (also covers the pending AppArmor item).

## Open decisions

1. **`skip-networking` vs per-project TCP port in prod.** **Resolved
   (2026-08-22), TCP-only:** `DEPLOY_DB_PORT` is required on database hosts;
   `provision.sh` always writes `port = $DEPLOY_DB_PORT` + `bind-address`,
   and `gen-reuter` requires `DEPLOY_DB_PORT` for the prod sections.
2. **AppArmor approach.** Per-project profile (precise, more moving parts) vs
   documented disable of the distro profile. **Still open** — to be decided
   on the first Debian staging host (Phase 4).
3. **Preflight placement.** **Resolved (2026-08-20): provision-time only.**
   No `ExecStartPre`: mysqld's own bind refusal + the unit's
   `Restart=on-failure` cover boot-time conflicts, and it avoids shipping a
   check script on the host.

**Resolved (2026-08-20):** instance-identity source (`deploy.conf`
`DEPLOY_DB_BASE` + convention, reuter.ini untouched); daemon runtime user
(the unit runs as root, `mysqld` drops privileges via `user = $PROD_USER`
in the defaults file); unit ownership (framework ships template +
install step, consumer names the instance, e.g. `mariadb@simox`); **`ema`
is not part of this plan** (no `server` subcommand; dev keeps
`init-cluster.sh`/`shell-enter.sh`); conflict diagnostics stay generic
(`socket <path> already taken` — no pid/owner disclosure); preflight at
provision time only; TCP on `DEPLOY_DB_PORT` (required on DB hosts).

## Notes

- Two repos involved: `../php_daas_framework` (provision, unit, README) and
  simox (config, docs). For reference, the ema repo lives at `../ema` — not
  involved here.
- Both repos are outside the CLI write workspace; stage edits under the temp
  dir, then `cp` (as done for the framework before).
- Commit policy: never stage/commit without explicit user approval; propose
  the commit message first.
- The distro `mariadb.service` must not be enabled on hosts running
  per-project instances (it would claim port 3306 + `/run/mysqld/` at boot).
- Provisioning logs may be read beyond the operator: conflict diagnostics must
  never reveal which pid/user owns a taken socket or port.
