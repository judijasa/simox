# Single code delivery path — Plan & Progress

Date: 2026-08-29
Repos: simox (this repo), php_daas_framework (framework/upstream), ema (upstream)
Status: Research complete — all three repos mapped; open items resolved below;
        execution split into phases (one chat session each).

## Decision

php_daas_framework **and** ema each reach simox through two independent
resolvers today. Composer delivers the framework code (src/ + the
phprun/deploy/gen-env/gen-reuter/cron-manifest CLIs + the phantomjs/casperjs
side-effects), while nix delivers the environment **and** a second copy of
that same code: `frameworkBin` copies `src/` + the CLIs for the framework, and
`packages.default` wraps the `ema` CLI for ema. Each resolver pins its own
commit, so each package must be kept in lock-step across two pins or the two
copies can disagree.

Make Composer the sole delivery path for the **code of both packages**. The
nix path keeps only the **environment binaries** — php runtime + extensions,
composer, mariadb, bash, tmux, jq (plus git/phpstan/pre-commit in the dev
shell) — declared locally in simox's `flake.nix`, importing neither upstream
flake. The dev bootstrap scripts (`shell-enter.sh`, `init-cluster.sh`,
`init-local-env.sh`, `provision.sh`) are **also** Composer-delivered via each
package's `"bin"` array: none is a true pre-composer dependency —
`shell-enter.sh` is sourced conditionally (skips when `vendor/bin` is absent)
and `init-cluster.sh` runs after `composer install` once the Makefile is
reordered.

The framework's native compatibility contract (php version + C extensions)
moves into its own `composer.json` as `ext-*` requirements — co-versioned with
the code and enforced at install — so it no longer spans two lockfiles.

Result: neither package is dual-pinned; the nix env rev and each Composer code
rev are independent. Out of scope: merging `flake.lock` and `composer.lock`
(impossible across resolvers).

## Mechanism

| concern | before | after |
|---|---|---|
| framework code (src/ + CLIs) | nix `frameworkBin` **and** Composer | Composer only |
| ema code (src/ + `ema` CLI) | nix `packages.default` | Composer only |
| dev bootstrap scripts | nix `frameworkBin` / `packages.default` | Composer `"bin"` (`vendor/bin`) |
| native contract (php + ext) | nix `php84.withExtensions` | `composer.json` `ext-*` |
| environment binaries | nix `packages.*` re-exports | nix, declared locally in simox |

`ext-*` = PHP platform packages: Composer strips `ext-` and checks
`extension_loaded(<name>)`. `flake.nix` `withExtensions` makes the extension
exist/loadable; `composer.json` enforces it at install (missing → hard
failure). No binary/name matching is involved.

## Implementation approach

Resolves the open items with these defaults:

- **ema version**: Composer VCS `dev-main` (matching the framework's VCS
  pattern). Final commit pin is set once ema's `composer.json` is
  committed/pushed.
- **`provision.sh`**: goes in the framework's `"bin"` array →
  `vendor/bin/provision.sh`; `deploy --init` repointed to it.
- **`ext-*` set**: `mysqli` / `pdo_mysql` / `bz2` (mirrors the flake's
  `withExtensions`; `pdo_mysql` is the code-level driver, `bz2` is the
  phantomjs-installer composer side-effect).
- **Load-bearing deploy fix**: the deploy CLI currently skips `composer
  install` when `composer.json` is unchanged — but `git archive` wipes
  `vendor/` on every deploy, so `vendor/bin` would be empty. `composer
  install` must run on **every** deploy (a consequence of Composer-only code
  delivery).

## Phases

Execution order (one chat session per phase): ema ships first (simox pins it
via Composer), then the framework, then simox. Each phase ends at a
verifiable checkpoint.

### Phase 0 — This document — DONE

### Phase 1 — ema becomes a Composer package (repo: `../ema`)

- [x] `composer.json` (new): type `library`, `"bin": ["ema", "bin/dev/init-cluster.sh"]`, ship `pkg/` + `src/`.
- [x] `src/` + `composer.json`: multi-provider `find_package()` locator reading `vendor/composer/installed.php`.
- [x] `.gitattributes`: export-ignore tests/docs/dev-only files.
- [x] `flake.nix`: remove `packages.default` makeWrapper (CLI + `init-cluster.sh` now Composer); keep a dev-only shell if one is still needed.
- [x] Commit + push ema — **prerequisite**: simox can't pin it until its `composer.json` is committed/pushed.

### Phase 2 — php_daas_framework: native contract + scripts + deploy (repo: `../php_daas_framework`) — DONE

- [x] `composer.json`: add `ext-mysqli`, `ext-pdo_mysql`, `ext-bz2` to `require` (`php >=8.1` already present); verify the set is complete by grepping `src/` (only `PDO`+`mysql:` DSN → `pdo_mysql`; no other non-bundled ext in `src/`/`bin/`).
- [x] `composer.json`: add `bin/dev/shell-enter.sh`, `bin/dev/init-local-env.sh`, `bin/provision.sh` to `"bin"` (join the 5 CLIs).
- [x] deploy CLI (`bin/deploy`): run `composer install` on **every** deploy — currently skipped when `composer.json` is unchanged, but `git archive` wipes `vendor/` each deploy (load-bearing). Also dropped the now-unused `git_target_changed` helper.
- [x] deploy CLI (`bin/deploy` `--init`): re-point `bin/provision.sh` → Composer-delivered `vendor/bin/provision.sh`.
- [x] `flake.nix`: remove `frameworkBin` (`cp ${./src}` + CLI copies) and `packages.default`; drop the `packages.*` re-exports (`runtime`/`php`/`composer`/`ema`/`bash`/`mariadb`); keep `devShells.default` (internal dev uses relative paths). Also dropped the `ema` input and pruned the stale `ema`/`nixpkgs_2`/`utils_2`/`systems_2` nodes from `flake.lock` (nixpkgs/utils pins unchanged).
- [x] `hooks/pre-commit-lock-sync.sh`: **delete** (ema is Composer-only now); removed its `lock-sync` entry from `.pre-commit-config.yaml`.
- [x] `README.md`: reflect the single Composer pin (Distribution, Dev-init machinery, consumer-flake example).

### Phase 3 — simox consumes both via Composer only (repo: simox)

- [x] `flake.nix`: drop the `php_daas_framework` **and** `ema` inputs.
- [x] `flake.nix`: declare the environment locally — `php84.withExtensions` (mysqli, pdo_mysql, bz2) + `composer.override`, `mariadb_118`, `bash`, `tmux`, `jq` (git/phpstan/pre-commit stay in `devShell`); no `packages.*` re-exports.
- [x] `flake.nix` shellHook: re-point `shell-enter.sh` to `[ -x vendor/bin/shell-enter.sh ] && source vendor/bin/shell-enter.sh`; add `[ -d vendor/bin ] && export PATH="$PWD/vendor/bin:$PATH"`.
- [x] `flake.nix`: re-label the `jqPkg` comment (jq is now an `ema` CLI runtime dep, not a lock-sync hook).
- [x] `Makefile`: reorder `_dev-init` so `_dev-init-composer` runs before `_dev-init-cluster`; repoint `_dev-init-cluster` → `vendor/bin/init-cluster.sh`, `_dev-init-local-env` → `vendor/bin/init-local-env.sh`.
- [x] `composer.json`: add a VCS `repositories` entry for `judijasa/ema` and `require` it at `dev-main` (pin the final commit once Phase 1 pushes ema).
- [x] `bin/deploy/post-nix.sh`: source gen-env/gen-reuter/cron-manifest from `$DEPLOY_TARGET_DIR/vendor/bin` (not `$DEPLOY_NIX_RESULT_DIR/result/bin`); cron-manifest phprun path becomes vendor/bin-based.
- [x] `checks/check-lock-sync.sh` + `hooks/pf/pre-commit-lock-sync.sh`: **delete**.
- [x] `.pre-commit-config.yaml`: remove the `lock-sync` hook id.
- [x] `README.md` "Dependency Pinning": rewrite to reflect single Composer pins for both packages.

### Phase 4 — Validation (end-to-end)

- [ ] ema standalone: `composer install` from VCS resolves; `ema` CLI + `find_package()` work against a consumer vendor tree.
- [ ] framework standalone: `composer install` succeeds with the `ext-*` set; `vendor/bin` holds the 5 CLIs + 3 dev scripts + `provision.sh`.
- [ ] simox: `make dev-init` end state unchanged (dirs → composer → cluster → `.env`); re-entering `nix develop` puts `vendor/bin` on PATH.
- [ ] Deploy: `deploy --init` runs `composer install` (not skipped) and reaches `vendor/bin/provision.sh`.

## Open items

Resolved by the implementation approach above:

- **ema version** → VCS `dev-main`; final commit pin once ema pushes.
- **`ext-*` list** → `mysqli` / `pdo_mysql` / `bz2`.
- **`provision.sh` location** → framework `"bin"` → `vendor/bin/provision.sh`.

Still open:

- **PATH timing**: the shellHook runs once at `nix develop` entry; `vendor/bin`
  only exists after `make dev-init` runs `composer install`. Re-entering the
  shell (or the next entry) picks up `vendor/bin` on PATH — confirm the dev
  flow tolerates this (or print a hint from `make dev-init`).
- **composer-before-cluster reorder**: `init-cluster.sh` only calls
  `mariadb-install-db`/`mysqld` (nix binaries), so it looks safe, but verify
  against `_dev-create-dirs` / log-dir assumptions during Phase 3.

## Notes

- Supersedes `doc/plans/2026-08-28-lock-sync.md` entirely (both packages are now single-pinned).
- Cross-repo: framework edits live in `../php_daas_framework`, ema edits in `../ema`; single-repo counterpart plans can be split out later if a package's standalone story needs them.
- The `deploy --init` provision repoint and the composer-install-on-every-deploy
  fix are both edits to `php_daas_framework/bin/deploy` (the deploy CLI is
  framework-owned), so they live in Phase 2, not simox.
- Phases are chat-session sized (context-bounded): stop between phases; each
  phase header names its repo and ends at a verifiable checkpoint.
- Commit policy: never stage/commit without explicit approval; propose the commit message first.
