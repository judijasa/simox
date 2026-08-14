# Deploy Module Relocation — `bin/deploy.sh` → `php_daas_framework` (Plan & Progress)

## Decision

Relocate simox's deployment module (`bin/deploy.sh`, and the deploy-relevant
parts of the Makefile) into `php_daas_framework`, consuming it the same way we
consume `phprun`: a project-agnostic `deploy` CLI shipped by the framework
(flake `packages.default` + composer `bin`), reading its configuration from
the consumer repo instead of hardcoded simox paths.

The boundary is: **framework = mechanism, consumer = data**.

- **Framework-owned (relocatable, config-driven):** the deploy flow
  (`bin/deploy.sh`), `bin/update-cron-manifest`, `bin/prod/gen-env.sh`, and
  (optionally) the provisioning scripts.
- **Consumer-owned (data, stays in simox):** config values, `etc/reuter.ini`,
  `src/` agent functions (`#[Agent]`/`#[CronJob]`), schema packages (`pkg/`).

**Config decision (2026-08-14):** deploy config lives in a **committed
`etc/deploy.conf`** (export-style lines) in the consumer repo — the
project-static deployment target (`PROD_USER`, `DEPLOY_TARGET_DIR`,
`DEPLOY_LOG_DIR`, `DEPLOY_NIX_RESULT_DIR`, `DEPLOY_NIX_GCROOT`,
`DEPLOY_INIT_CMD`). `.env` stays generated per environment (git-ignored) and
holds machine-specific values (`REPO_PATH`, `REPO_LOG`, `REUTER_INI`,
`EMA_TARGET`, `MYSQL_*`, `DBUSER`). Rationale: `.env` is regenerated per
environment, so it is the wrong home for project-static deploy parameters.

## Current state (start of session)

- simox ships: `bin/deploy.sh` (continuous deploy + `--init`), `bin/prod/*.sh`
  (provisioning), `bin/update-cron-manifest`, Makefile targets
  `prod-init`/`prod-gen-env`; hardcoded simox paths (`/srv/apps/simox`,
  `/var/log/simox`, `/usr/local/simox`, `/etc/cron.d/simo-orchestrator`,
  `PROD_USER=simox`).
- php_daas_framework ships `phprun` (bash wrapper + `src/phprun.php`), which
  loads the repo-root `.env` from the CWD at runtime — the project-agnostic
  config surface we want to mirror.
- Integration: nix flake input `github:judijasa/php_daas_framework` +
  composer VCS `judijasa/php-daas-framework` (both track `dev-main`).

## Progress & Resume

> Last updated: 2026-08-14. Read this section first when resuming this task
> from a new session. If the CLI supports it, `deepseek --resume <session-id>`
> restores the original conversation (`doc/todo.txt` uses this pattern); the
> session-id is not recorded in this repo — paste it here after resuming.

### Where the work lives
- Consumer repo: `/home/juan/git/simox` (this repo).
- Framework repo: `/home/juan/git/php_daas_framework` (i.e. `../` from simox).
  It is OUTSIDE the CLI write workspace: edit it via `run_shell_command`
  (stage content under `/home/juan/.deepseek/tmp/simox/`, then `cp`), not via
  `write_file`/`replace`.
- Framework commit `275a795` ("Add deploy CLI (project-agnostic deployment)")
  on local `main`, **not pushed** — includes the `etc/deploy.conf` config
  correction. simox's flake/composer pins still point at the previous
  framework revision.
- simox working tree: uncommitted changes (`doc/2026-07-31-srv-migration.md`,
  `doc/todo.txt`, `doc/prod-user-setup.md`, `prompt.txt`, this file); branch
  `2026-08-06-import-php-runner`.

### Status snapshot (as of 2026-08-14)
- [x] Phase 1 — framework `bin/deploy` CLI created and shipped (flake
      `packages.default` + composer `bin`), committed `275a795`.
- [x] Phase 1 correction — `bin/deploy` sources committed `etc/deploy.conf`
      (+ `.env` for `REPO_PATH`); framework ships `etc/deploy.conf.template`;
      README updated. Committed in `275a795`.
- [x] Phase 2 — simox: `etc/deploy.conf` created (PROD_USER + DEPLOY_*);
      `bin/dev/init-local-env.sh` no longer generates `PROD_USER` into `.env`;
      Makefile sources `etc/deploy.conf` (`PROD_USER ?= simox` fallback);
      flake.nix shellHook comment synced. Validated: config sources cleanly,
      `.env` regenerated without PROD_USER, `make -pn` resolves PROD_USER from
      deploy.conf, framework `deploy` passes `require_config` (stops at the
      branch flight check — branch is not `main`). Committed in `a298e43`.
- [x] Phase 3 — framework `cron-manifest` + `gen-env` CLIs created and
      shipped (flake + composer); framework `deploy` runs a second optional
      consumer hook `bin/deploy/post-nix.sh` after nix closure + composer
      deps (resolves the cron-before-nix-php ordering issue; the swap-time
      `post-swap.sh` hook contract is unchanged). Simox: committed
      `etc/env.prod` (template for `gen-env`), `bin/deploy/post-nix.sh`
      (regenerates .env + installs cron), `bin/provision.sh` (consolidates
      the old assert-user/create-dirs/init-cluster; `DEPLOY_INIT_CMD` now
      points at it), `CRON_FILE`/`CRON_USER` added to `etc/deploy.conf`.
      Validated: `bash -n`/`php -l`, cron-manifest output byte-identical to
      the old script, gen-env fail-fast tests, deploy CLI smoke tests,
      sandbox end-to-end run of `post-nix.sh` (stubbed systemctl), framework
      `nix build` (ships all four CLIs). **Committed:** framework `6883f00`
      (pushed to origin/main), simox `f954590`.
- [ ] Phase 4 — staging test pending; cleanup, docs and pin bumps DONE
      (simox `Makefile` shrunk to dev-only; `bin/deploy.sh`,
      `bin/update-cron-manifest`, `bin/prod/*` removed; `README.md` +
      `doc/prod-user-setup.md` updated; `flake.lock` + `composer.lock`
      pinned to `6883f00`). Uncommitted.

### Next actions (in order)
1. Phase 2 (simox): create `etc/deploy.conf` (committed); update
   `bin/dev/init-local-env.sh` if `PROD_USER` stops being generated into
   `.env`. **Ask the user before committing.** — DONE, committed `a298e43`.
2. Phase 3: relocate `cron-manifest` / `gen-env`; decide `prod-init` approach;
   create simox `bin/deploy/post-swap.sh` (currently missing — simox deploy
   would skip .env regeneration and cron install until it exists).
   — DONE, with a design revision: the ordering issue (cron generated before
   the nix php/phprun are on the remote) made the swap-time hook the wrong
   home for .env+cron, so the framework `deploy` gained a second optional
   consumer hook `bin/deploy/post-nix.sh` (after nix closure + composer
   deps). Simox ships **post-nix.sh**, not post-swap.sh. Uncommitted (both
   repos). **Ask the user before committing/pushing.**
3. Phase 4: staging test, remove old scripts, update docs, bump pins
   (framework must be pushed first). — Mostly DONE: framework `6883f00`
   pushed; old scripts removed; Makefile shrunk; docs updated; pins bumped
   (uncommitted in simox). Remaining: end-to-end `deploy` test on a staging
   host (needs the remote host + branch `main` — see Gotchas), then commit
   the simox Phase 4 changes.

### Gotchas for the next session
- **Commit policy:** never stage/commit without explicit user approval; always
  propose the commit message first.
- Deploy config lives in the **committed `etc/deploy.conf`**; `.env` is for
  machine values. Do not move `DEPLOY_*` back into `.env`.
- When editing `bin/deploy`, do NOT hand-retype the SSH-heredoc escaping —
  transform the known-good `simox/bin/deploy.sh` text in place (Phase 1
  verified byte-identical escaping).
- The old simox `bin/deploy.sh --init` ran `make prod-init` locally (bug); the
  framework version runs `DEPLOY_INIT_CMD` over SSH on the remote.
- Framework repo has pre-existing untracked files (`hooks/`,
  `.pre-commit-config.yaml`) — leave them untouched.

## Phases

### Phase 1 — Framework `deploy` CLI — DONE, committed `275a795` (incl. `etc/deploy.conf` correction)

- Extracted the generic deploy flow from simox `bin/deploy.sh` into
  `php_daas_framework/bin/deploy` (self-contained bash; sources `.env` from
  the consumer repo root, same as `phprun`).
- Parameterized simox-specific paths as `DEPLOY_*` config vars; replaced the
  gen-env + cron piggyback blocks with an optional consumer hook
  `bin/deploy/post-swap.sh`; `--init` now runs `DEPLOY_INIT_CMD` over SSH
  (fixes the local-vs-remote `make prod-init` bug).
- Shipped via `flake.nix` `packages.default` and `composer.json` `bin`;
  README section added.
- Verified: `bash -n`, byte-equality of heredoc escaping vs the original,
  `nix build` (artifact ships `phprun` + `deploy`), CLI smoke tests
  (usage / unknown flag / missing-config errors).
- **Correction (committed in `275a795`):** `bin/deploy` sources a committed
  `etc/deploy.conf` (required, fails loudly if missing) in addition to `.env`
  (which provides `REPO_PATH`). `PROD_USER` + `DEPLOY_*` now come from
  `etc/deploy.conf`. Framework ships `etc/deploy.conf.template`. Header +
  README updated. Verified again with `bash -n` and smoke tests.

### Phase 2 — Config contract in simox — DONE, committed `a298e43`

- Create simox `etc/deploy.conf` (committed) with the simox values:
  `PROD_USER=simox`, `DEPLOY_TARGET_DIR=/srv/apps/simox`,
  `DEPLOY_LOG_DIR=/var/log/simox`,
  `DEPLOY_NIX_RESULT_DIR=/usr/local/simox`,
  `DEPLOY_NIX_GCROOT=/nix/var/nix/gcroots/simox`. (Phase 3 moved
  `DEPLOY_INIT_CMD` from `make prod-init` to `bash bin/provision.sh` and
  added `CRON_FILE`/`CRON_USER`.)
- `.env` stays machine-specific: `REPO_PATH`, `REPO_LOG`, `REUTER_INI`,
  `EMA_TARGET`, `MYSQL_*`, `DBUSER`. `PROD_USER` removed from
  `bin/dev/init-local-env.sh` (carried by `etc/deploy.conf`).
- Makefile sources `etc/deploy.conf` (`PROD_USER ?= simox` fallback).

### Phase 3 — Relocate the remaining deploy tools — DONE (uncommitted in both repos)

- `bin/update-cron-manifest` → framework `cron-manifest` (bash wrapper +
  `src/cron_manifest.php`, phprun-style): config-driven `CRON_USER`
  (default `root`), `CRON_NIX_BIN` (default
  `$DEPLOY_NIX_RESULT_DIR/result/bin`); prints the crontab to stdout.
  Output verified byte-identical to the old script (with the same env).
- `bin/prod/gen-env.sh` → framework `gen-env [target-dir]`: copies the
  consumer's committed `etc/env.prod` template → `.env` with a fail-fast
  guard (every content line of the template must be present in the file).
  Simox ships `etc/env.prod`.
- **Ordering fix (design revision):** the cron-before-nix-php issue meant
  the swap-time hook was the wrong home for .env+cron, so framework
  `deploy` now runs a second optional consumer hook `bin/deploy/post-nix.sh`
  after the nix closure + composer deps (and after `--init` provisioning).
  Simox ships `bin/deploy/post-nix.sh` (gen-env → cron-manifest →
  `CRON_FILE` install + cron restart); the swap-time `post-swap.sh` hook
  contract is unchanged but simox does not ship it (nothing swap-time to do).
- **Provisioning decision (open decision #1 resolved):** consumer hook —
  simox `bin/provision.sh` consolidates `assert-user.sh`/`create-dirs.sh`/
  `init-cluster.sh` (provisioning is machine-state data; a framework
  `provision` CLI would need a new config schema for marginal benefit).
  `DEPLOY_INIT_CMD="bash bin/provision.sh"`. Note: prod keeps
  `mariadb-install-db` without the dev `--auth-root-authentication-method`
  flag (unix_socket auth vs dev sandbox).
- Framework packaging: `packages.default` ships the four CLIs (`phprun`,
  `deploy`, `gen-env`, `cron-manifest`) + `src/`; composer `bin` lists all
  four; `etc/deploy.conf.template` + README document `CRON_FILE`/`CRON_USER`
  and both hooks.
- Verified: `bash -n`/`php -l`; cron-manifest byte-parity; gen-env fixture
  (write + missing-template failure); deploy CLI smoke tests; sandbox
  end-to-end run of simox `post-nix.sh` (stubbed systemctl, real framework
  CLIs); framework `nix build` artifact inspection.

### Phase 4 — Validation & simox cleanup (mostly done; staging test pending)

- [ ] End-to-end `deploy` test on a staging host; `--init` provisions via
      hook. **Requires:** a reachable remote host (root + PROD_USER ssh
      access) and the simox branch `main` (the deploy flight checks refuse
      other branches). Run `deploy --init <host>` from the repo root inside
      `nix develop`; verify repo swap, `.env` regeneration, cron install
      (`/etc/cron.d/simo-orchestrator`), and provisioned dirs.
- [x] Remove `bin/deploy.sh`, `bin/prod/*`, `bin/update-cron-manifest`; shrink
      the Makefile to dev-only (prod provisioning now lives in
      `bin/provision.sh`, driven by the framework `deploy --init`).
- [x] Update docs: `README.md`, `doc/prod-user-setup.md`, this file.
- [x] Bump simox pins (`flake.lock` + `composer.lock`) to the new framework
      commit `6883f00` (pushed to origin/main first).

## Open decisions

1. ~~`prod-init` provisioning: framework-owned `provision` vs consumer hook.~~
   **Resolved (2026-08-14):** consumer hook — simox `bin/provision.sh` (see
   Phase 3). Provisioning is machine-state data, not framework mechanism.
2. Commit workflow: always ask the user before staging/committing.
3. Push the framework commit before the simox pin bump.

Resolved (2026-08-14): config location — committed `etc/deploy.conf`
(export-style) + `.env` for machine `REPO_PATH`; `PROD_USER` stays unprefixed
and moves into `etc/deploy.conf`.

Resolved (2026-08-14): deploy-time consumer hooks — two optional hooks:
swap-time `bin/deploy/post-swap.sh` (bash-only, before nix) and
`bin/deploy/post-nix.sh` (after nix closure + composer deps; framework CLIs
available). This resolves the cron-before-nix-php ordering issue; simox ships
only post-nix.sh.

## Notes

- The framework repo lives outside the CLI's write workspace; edits to it are
  staged via the temp dir + shell (allowed for any path).
- Pre-existing untracked files in the framework repo (`hooks/`,
  `.pre-commit-config.yaml`) were left untouched.
- The current simox `bin/deploy.sh --init` runs `make prod-init` **locally**
  (bug); the framework version runs `DEPLOY_INIT_CMD` over SSH on the remote.
