# Deploy Location Migration — `/home` → `/srv` (Plan & Progress)

## Decision

Move the production deployment target from
`/home/$PROD_USER/apps/simox` to **`/srv/apps/simox`**.

The website is **not** separable from the repo — `public/index.php` and
`public/insight.php` do `require_once __DIR__ . '/../vendor/autoload.php'` and
use the `Utils\` PSR-4 namespace (`src/utils/`, see `composer.json`). So the
Apache `DocumentRoot` must always point *into* a full repo checkout
(`.../simox/public`). This was never a `/home` vs `/var/www/html` choice — the
"copy `public/*` to `/var/www/html`" idea (still described in the now-stale
`doc/deployments.md`) cannot work because the served files need `vendor/` and
`src/` as siblings. The real question was only **where the whole repo lives**.

### Why `/srv` over `/home`

| | `/home/$PROD_USER/apps/simox` (old) | `/srv/apps/simox` (new) |
|---|---|---|
| FHS intent | `/home` = interactive user data | `/srv` = "site-specific data served by this system" ✅ |
| Disk isolation | shares partition with root/home | **dedicated partition** on this server ✅ (partial — see caveat) |
| Apache traversal | needs `chmod o+x` on the **user's home dir** (makes home world-traversable) | `/srv`, `/srv/apps` traversable; home untouched ✅ |
| Web-attack confinement | via file ownership + worker uid + MAC | **same** — path prefix is irrelevant to confinement |
| Migration cost | none | small, contained (see steps) |

The original rationale for `/home` — "keep a web-borne attack away from
system-level files" — does **not** favor `/home`. Confinement comes from the
app dir being owned by an unprivileged `PROD_USER`, the Apache worker uid, and
MAC — not from the path prefix. `/srv` is data, not a system-binary/config area
like `/usr` or `/etc`, and it avoids loosening permissions on a login user's
home directory.

**Server context:** Debian (AppArmor, not SELinux). Debian ships no restrictive
AppArmor profile for `apache2` by default, so — unlike Fedora/RHEL + SELinux,
where serving from `/home` needs the broad `httpd_enable_homedirs` boolean and
`/srv` needs an `httpd_sys_content_t` label — **no relabeling or MAC tweaks are
required** on this server for either path. The case for `/srv` here rests on
FHS correctness, the dedicated partition, and not touching home-dir perms.

**Runtime data decision:** the app's writable state stays on **system paths**,
not co-located under `/srv/apps/simox/var`:

- logs → `/var/log/simox`  (`SIMO_LOG_PATH`)
- MariaDB → `/var/lib/simox/mariadb`

This matches what `Makefile prod-init` already provisions, keeps mutable state
cleanly separated from the code (which is wiped and replaced on every deploy),
and means the atomic repo swap never has to preserve a `var/` subtree.

**Disk-isolation caveat (honest):** because the DB lives on `/var/lib` (on the
root/`var` partition) and not on `/srv`, the dedicated `/srv` partition isolates
only repo + `vendor/` growth — not the database, which is the component most
likely to grow. Relocating the DB/logs to the `/srv` partition is explicitly
**out of scope** here; revisit separately if `/var` disk pressure becomes real.

## How prod resolves its paths today

Prod cron jobs and scripts do **not** read `flake.nix` (that shellHook is
dev-only, deriving `SIMO_VAR_PATH=$PWD/var`). Instead, when not in a nix shell
they `source /etc/environment` for `SIMO_REPO_PATH` and `SIMO_LOG_PATH`
(`bin/phprun:6`, `src/scripts/indexer/main.sh:6`). Nothing in the repo writes
`/etc/environment` — it is a **manual, one-time** server file and therefore a
migration touchpoint.

Deploy-time path assumptions live in `bin/deploy.sh`:
- `REMOTE_TARGET_DIR="/home/${PROD_USER}/apps/simox"`  (`main`, ~line 269)
- `VAR_DIR='/home/$PROD_USER/var'`, `LOG_DIR="$VAR_DIR/simox/log"`
  (inside `deploy_repo_remotely`, ~lines 93–94) — used only for
  `deploy_version.log`; today this diverges from `SIMO_LOG_PATH`. The migration
  reconciles it onto `/var/log/simox`.

Nix is unaffected: it stays under `~/.nix-profile` / `~/.nix-gcroots`, bridged
to `/usr/local/simox/result`. Only the **app repo** moves.

## Implementation steps

### Phase 1 — `bin/deploy.sh`

- [x] Change target: `REMOTE_TARGET_DIR="/srv/apps/simox"` (drop the
      `$PROD_USER` dependence in the path; the dir is still *owned* by
      `PROD_USER`).
- [x] Reconcile deploy-log paths: replace `VAR_DIR='/home/$PROD_USER/var'` /
      `LOG_DIR="$VAR_DIR/simox/log"` with `LOG_DIR='/var/log/simox'` so
      `deploy_version.log` lands next to the app logs (`SIMO_LOG_PATH`). Update
      the `chown -R ... "$VAR_DIR"` line accordingly (chown `/var/log/simox` to
      `PROD_USER`, as `prod-init` already does).
- [x] Confirm the atomic-swap still holds: `BASE_DIR=$(dirname REMOTE_TARGET_DIR)`
      becomes `/srv/apps`, `mktemp -d -p "$BASE_DIR"` and the final `mv` stay
      within the `/srv` partition → rename remains atomic. Requires `/srv/apps`
      to exist beforehand (Phase 2).
- [x] Update the `deploy_website` comment block: parent dirs to make
      traversable are now `/srv` and `/srv/apps` (one-time); note Debian/AppArmor
      needs no relabeling. The per-deploy `chmod o+x '$REMOTE_TARGET_DIR'` stays.

### Phase 2 — `Makefile` (`prod-init`)

- [x] `prod-init` already sets `PROD_LOG_DIR=/var/log/simox` and
      `PROD_DB_DIR=/var/lib/simox/mariadb` — no change needed there; the
      "system paths" decision is already encoded.
- [x] Extend `_prod-create-dirs` (or a new prereq) to create the deploy parent:
      `mkdir -p /srv/apps` and `chown $PROD_USER:$PROD_USER /srv/apps`, plus
      `chmod o+x /srv /srv/apps` for Apache traversal (idempotent one-time).

### Phase 3 — Server one-time manual steps

- [ ] `/etc/environment`: set `SIMO_REPO_PATH=/srv/apps/simox` and
      `SIMO_LOG_PATH=/var/log/simox`. (Only these two are consumed by
      `phprun` / `main.sh` in prod; `SIMO_VAR_PATH` is dev-only.)
- [ ] Apache vhost: point `DocumentRoot` and `<Directory>` to
      `/srv/apps/simox/public`; keep `SetEnv SIMOX_REUTER_INI <path>`.
- [ ] First cutover: deploy once to `/srv/apps/simox` (`bin/deploy.sh --init`),
      verify, then remove the old `/home/$PROD_USER/apps/simox` and its
      `..._backup`, and drop the `chmod o+x` that had been applied to the home
      dir (restore home to non-traversable).

### Phase 4 — Docs

- [x] `doc/web_setup.md`: change the `DocumentRoot` example from
      `/home/user/my-project/public` to `/srv/apps/simox/public`.
- [x] `doc/deployments.md`: rewrite or delete — it describes the abandoned
      "copy `public/*` into `/var/www/html`" approach, which contradicts the
      current (and new) design.

### Phase 5 — Verification

- [ ] `bin/deploy.sh <host>` deploys to `/srv/apps/simox`; atomic swap works.
- [ ] Website served correctly from `/srv/apps/simox/public` (autoload +
      `Utils\` classes resolve; `SIMOX_REUTER_INI` picked up).
- [ ] Cron jobs run: `/etc/cron.d/simo-orchestrator` regenerated with
      `cd /srv/apps/simox`, logs written to `/var/log/simox`.
- [ ] `deploy_version.log` and `.deploy_version` land in the expected places.
- [ ] Old `/home/$PROD_USER/apps/simox` removed; home dir no longer `o+x`.

## Notes

- **Nix is untouched** — `~/.nix-profile`, `~/.nix-gcroots`,
  `/usr/local/simox/result`. Only the app repo relocates.
- **Ownership unchanged** — `/srv/apps/simox` is owned by the unprivileged
  `PROD_USER`; Apache runs as its own worker uid. Same confinement as before.
- **Dev is unaffected** — `flake.nix` derives paths from `$PWD`; the dev shell
  keeps `var/` inside the repo. Only prod uses `/var/log/simox` (via
  `/etc/environment`). This dev/prod divergence already exists and is intended.
- Related: [[project_db_routing]] (`doc/db-routing.md`) — same "server has no
  git, repo dir is overwritten each deploy" model this migration preserves.
