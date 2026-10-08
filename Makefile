# simox Makefile (dev-init only; production deploy is the bare `deploy`
# command, not a make target).
# Production deploy is the consumer entrypoint bin/deploy: it first runs
# simox's own private-config materialization (bin/fetch-private-data copies the
# real etc/ files in locally), then the framework `deploy` CLI
# (vendor/bin/deploy) — which ships DEPLOY_PRIVATE_FILES (reuter.ini ema.conf) to
# each host, replays the deploy.conf environment to every remote step, and runs
# its built-in per-host steps (gen-env/db-check on every host, cron install on
# `worker` hosts) — and finally the consumer post-deploy step
# bin/deploy-steps/server-side-post-deploy.sh, covering only simox's own tags
# (`web`). The provisioning extra stays bin/deploy-steps/provision-extra.sh via
# DEPLOY_INIT_CMD.
# Generic dev-init steps delegate to the Composer-delivered scripts in
# vendor/bin (init-local-env.sh from the `judijasa/php-daas-framework` package);
# this Makefile keeps only the consumer-specific steps (git hooks, private
# config, hosts, ssh config). The dev MariaDB daemon is owned by ema's
# per-instance sandbox lifecycle (`ema sandbox` / `ema start` / `ema stop`) —
# this Makefile no longer initializes or starts a shared daemon.

SHELL := $(shell which bash 2>/dev/null)

# Machine paths are derived from the repo root and owned by this Makefile
# (not by the environment): .env is a regenerated snapshot and nothing
# exports these into the shell anymore. The defaults let every target run
# standalone, e.g. `make dev-init` via ssh where no env vars exist.
REPO_PATH = $(CURDIR)
REPO_VAR = $(REPO_PATH)/var
REPO_LOG = $(REPO_VAR)/log

# The app layer's config file, named explicitly because a web server cannot
# honour the repo's "run from the repo root" convention: php -S chdirs into
# its document root, so the app layer's $PWD/etc/reuter.ini fallback resolves
# to public/etc/reuter.ini and misses. Same knob prod's php-fpm pool sets via
# env[REUTER_INI]; `?=` keeps an explicit REUTER_INI=… make web in charge.
REUTER_INI ?= $(REPO_PATH)/etc/reuter.ini

_dev-init: DEV_LOG_DIR = $(REPO_LOG)
_dev-init: TAG_BEGIN = \# generated: simox-hosts
_dev-init: TAG_END   = \# end: simox-hosts

.PHONY: help dev-init web _dev-assert-nix _dev-init _dev-init-git-hooks _dev-create-dirs \
    _dev-init-composer _dev-init-private-config _dev-update-hosts _dev-ssh-config _dev-init-local-env

help:
	@echo "Available targets:"
	@echo "  dev-init   - Run ONCE after cloning locally to build the dev sandbox"
	@echo "  web        - Run the local PHP built-in server"

dev-init: _dev-assert-nix _dev-init

# Local website: run the same nix-built PHP the web server uses (the php84
# closure php-fpm is built from) as a built-in server. `-t public` matches
# prod's DocumentRoot; REUTER_INI compensates for the chdir that comes with it
# (see the definition above).
web: _dev-assert-nix
	@REUTER_INI=$(REUTER_INI) php -S localhost:8000 -t public

_dev-assert-nix:
	@if [ -z "$$IN_NIX_SHELL" ]; then \
	    echo "ERROR: This target must be run inside 'nix develop'"; \
	    exit 1; \
	fi

_dev-init: _dev-init-git-hooks _dev-create-dirs _dev-init-composer _dev-init-private-config _dev-init-local-env _dev-update-hosts _dev-ssh-config
	@echo "Developer environment successfully initialized."

_dev-init-git-hooks:
	@bin/dev/init-git-hooks.sh

_dev-create-dirs:
	@echo "Creating local logging and storage directories..."
	mkdir -p $(DEV_LOG_DIR)

_dev-init-composer:
	@echo "Removing vendor/ if exists..."
	-rm -rf vendor
	@echo "Running composer install..."
	composer install

# The private config repo is the source of truth for etc/reuter.ini,
# etc/machines.ini, etc/team.ini, etc/hosts and (when the private source
# provides them) etc/deploy.conf and etc/host-hardening.php: this copies them
# here as real files, overwriting them on every run, through the
# `.private-source` pointer. Without that pointer the step is a no-op.
_dev-init-private-config:
	@bin/fetch-private-data "$(REPO_PATH)"

# etc/hosts is private data, materialized as a real file by
# _dev-init-private-config; this must therefore follow it in _dev-init.
_dev-update-hosts:
	@bin/dev/update-hosts.sh "$(TAG_BEGIN)" "$(TAG_END)"

# The generated ssh config reads that same private etc/hosts (materialized by
# _dev-init-private-config), so it must follow that step in _dev-init as well:
# each entry becomes `ssh <repo-dir>-<name>` as root with the project key. The
# app name (alias prefix) is the repo directory name — distinctive per repo,
# never typed — and gen-ssh-config derives it, the key
# (~/.ssh/<repo-dir>-sshkey) and the `root` user from the current directory,
# so no arguments are passed.
_dev-ssh-config:
	@vendor/bin/gen-ssh-config

# The framework's init-local-env.sh derives REPO_PATH/REPO_LOG and relays the
# consumer-chosen dev values (DBUSER, SSL_DIR) into .env — from the committed
# etc/dev.default.conf, overridden by the materialized etc/dev.conf when present
# — this Makefile owns no dev values of its own.
_dev-init-local-env:
	@vendor/bin/init-local-env.sh
