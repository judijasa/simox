# DB Routing — Plan & Progress

## Vision

`ema` is a CLI tool for managing MariaDB schema packages, analogous to what
`scm` is in the schematic repo (PostgreSQL). You can find the schematic repo in
`../schematic`. It is a personal project —
developed at a deliberate pace with AI assistance on specific tasks.

It lives in its own repo at `github:judijasa/ema`.

### ema and the dev shell

`ema` is a flake input in simox wired into **both**
`devShells.default` and `packages.default`:

```nix
# simox/flake.nix
inputs.ema.url = "github:judijasa/ema";
```

Adding it to `packages.default` means the existing `deploy_nix_packages`
step in `bin/deploy.sh` ships it to the prod server automatically — `ema`
arrives at `/usr/local/simox/result/bin/ema` alongside `php`, `mariadb`,
etc. The `flake.lock` is the version pin; no extra deploy logic is needed.

Current `ema` commands:
- `ema schema <name>` — create a new schema package in `pkg/`

Planned commands (scope of this document):
- `ema mariadb` — open an interactive MariaDB shell against the active target
                  (analogous to `scm psql` in the schematic repo)
- `ema init db <name>` — create the database, users, and grants
- `ema init tables <root-pkg>` — apply all schema packages in topological order

`<name>` is required with no default — the user must always supply it explicitly.
It resolves to `srv/<name>.sql` (e.g. `ema init db simo` → `srv/simo.sql`).
`<root-pkg>` is the top-level package for the topological sort
(e.g. `ema init tables simo-C196A24801D24B16`).

Future: `srv/` items could become proper package directories like `pkg/`,
enabling `ema init srv/simo-HASH` — not in scope now.

Explicitly out of scope for now (future):
- Declarative schema upgrade / diff (like `scm upgrade`)
- Sandbox creation / lifecycle management

## Core concepts

### Reuter file

A "reuter" is a bash-compatible INI file that describes how to reach a named
database target. There is exactly one location: `src/reuter/{name}.sh`
(git-ignored, machine-specific). A machine can have as many reuter files as it
has targets — local sandbox, remote prod, staging, a second local cluster, etc.
Keys:

```ini
SERVER="10.147.x.x"      # hostname or IP (ignored when MYSQL_UNIX_PORT is set)
PORT="3306"              # TCP port (optional, default 3306)
DBNAME="simo"
DBMS="mariadb"
ADMIN_PASSWORD="..."
READER_PASSWORD="..."
# MYSQL_UNIX_PORT=...    # leave unset for TCP; set path for Unix socket
```

A template lives at `../ema/etc/reuter.template.sh` (in the `ema` repo). The user
copies it once per target to `src/reuter/{name}.sh` and fills in the values for
that target.

### `SIMO_TARGET` environment variable

| Value | Config read from | Transport |
|---|---|---|
| unset or `local` | `src/reuter/local.sh` | socket (if `MYSQL_UNIX_PORT` set) or TCP localhost |
| any name | `src/reuter/{name}.sh` | as configured in that file |

This covers all scenarios without touching script code:

| Scenario | How |
|---|---|
| Prod cron on prod server | `SIMO_TARGET` unset (defaults to `local`) |
| Dev machine → prod DB via ZeroTier | `export SIMO_TARGET=prod` |
| Dev shell → local sandbox | `SIMO_TARGET=local` (or unset), socket via `MYSQL_UNIX_PORT` |
| Dev machine → second local cluster | `export SIMO_TARGET=local2` (user-defined) |

### Where `ema` looks for its config

`ema` is called from the repo root. It loads the active target at startup via
a `_load_target` internal function, then all subcommands use the loaded
variables (`$SERVER`, `$DBMS`, `$ADMIN_PASSWORD`, etc.) and the
`$MYSQL_UNIX_PORT` env var for socket routing.

## Implementation steps

### Phase 0 — Fix existing bugs in `ema`

- [x] `ema` sources `/etc/environment` unconditionally; it should skip that
      when `IN_NIX_SHELL` is set (same pattern as `src/init/databases.sh`)
- [x] `ema` references `$SIMOX_REPO_PATH` but the flake exports `$SIMO_REPO_PATH`
      — fix the variable name throughout `ema`

### Phase 1 — Directory structure & cleanup

- [x] Create `src/reuter/` directory (tracked; `*.sh` files inside are git-ignored)
- [x] Add `src/reuter/*.sh` to `.gitignore`
- [x] Add `etc/reuter.template.sh` (single tracked template; user copies once per
      target to `src/reuter/{name}.sh` and fills in the values)
- [x] Remove the `alias mariadb="mariadb --socket=..."` line from `flake.nix`
      shellHook — connection details belong in `src/reuter/`, and the alias
      hardcodes a single transport incompatible with multiple targets

### Phase 2 — Target loading in `ema`

- [x] Add `_load_target` function to `ema`:
      reads `SIMO_TARGET` (defaults to `local`), sources `src/reuter/${target}.sh`,
      exports the connection variables for use by other functions in `ema`
- [x] Add `_mariadb_args` function: returns an array of `mariadb` flags
      (`--socket=...` or `-h $SERVER [-P $PORT]`) based on the loaded target
- [x] Add `ema mariadb` subcommand: loads the active target and drops into an
      interactive MariaDB shell (analogous to `scm psql` in the schematic repo);
      replaces the `alias mariadb=...` that was previously in `flake.nix`

### Phase 3 — Absorb `src/init/` into `ema`

- [x] `ema init db <name>` — absorbs `src/init/databases.sh`;
      loads `srv/<name>.sql`, substitutes placeholders from the active reuter,
      executes via `_mariadb_args`
- [x] `ema init tables <root-pkg>` — absorbs `src/init/tables.sh`;
      topological sort called exactly as now via `php -r "..."`, uses `_mariadb_args`
- [x] Update Makefile callers; remove or thin-wrap `src/init/databases.sh`
      and `src/init/tables.sh`

### Phase 4 — Wire ema into flake.nix

- [x] Add `ema` as a flake input (`github:judijasa/ema`)
- [x] Add `ema.packages.${system}.default` to both `devShells.default`
      and `packages.default`

Adding `ema` to `packages.default` means the existing `deploy_nix_packages`
step in `bin/deploy.sh` ships it to prod automatically — no Makefile changes
needed.

`ema init db` and `ema init tables` are **manual operations**, never called
from `make dev-init` or `make prod-init`. `make dev-init` does not create
any database — the user creates it manually using `ema init db <name>` after
copying and filling in the appropriate reuter template. Different servers may
hold different databases; the user decides when and what to initialise.

Init always runs **locally on the target server**:

| Scenario | How |
|---|---|
| Dev sandbox | Enter dev shell, run `ema init db <name>` |
| Prod server | SSH in via ZeroTier, run `ema init db <name>` |

### ZeroTier

ZeroTier is the secure overlay network for all access to prod servers —
SSH and database connections alike. The prod server's ZeroTier IP is the
single address used for everything:

- `ssh deploy@<zerotier-ip>` to reach the server
- `SERVER="<zerotier-ip>"` in `src/reuter/prod.sh` so that
  `SIMO_TARGET=prod` on a dev machine reaches the prod database

`SIMO_TARGET=prod` on a dev machine is for running application scripts
(e.g. `pipeline.php`) against a prod database without SSH-ing in. ZeroTier
makes this reachable; the tool sees only a hostname.

### Phase 5 — Update `Database.php`

- [x] Extract `loadConfig(): array` private static: reads `SIMO_TARGET`
      (defaults to `local`), sources `src/reuter/{target}.sh`
- [x] Replace `shell_exec` for socket detection with PHP `getenv()`
- [x] Support `PORT` key in DSN

### Phase 6 — Dev shell

- [x] Set `export SIMO_TARGET=local` explicitly in `flake.nix` shellHook
      (makes the mechanism visible; easy to override with `export SIMO_TARGET=prod`
      before entering the shell or in `.envrc`)

### Phase 7 — Verification

- [ ] `ema init db simo` + `ema init tables simo-C196A24801D24B16` work in dev shell
- [ ] `pipeline.php` connects correctly with `SIMO_TARGET=local`
- [ ] `pipeline.php` connects to prod DB with `SIMO_TARGET=prod`
- [ ] Prod cron jobs work with `SIMO_TARGET` unset

## Notes

- The topological sort (`src/utils/sort_schemas.php`) is called by
  `ema init tables` via `php -r "..."` exactly as in `tables.sh` — unchanged.
- `ema` is consumed by simox as a flake input (`github:judijasa/ema`).
- `etc/reuter.template.sh` lives in the `ema` repo (`../ema/etc/reuter.template.sh`).
- `src/reuter/` stays in simox (machine-specific config, git-ignored).
- `src/config.sh` is superseded by `src/reuter/local.sh`; it can be removed
  once all callers are migrated.
