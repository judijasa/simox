# simox Makefile (dev-init + deploy entrypoints).
# Production deploy is the consumer entrypoint bin/deploy.sh: it first runs
# simox's own private-config pipeline (bin/fetch-private-data materializes the
# real etc/ files, bin/deploy-private-config ships deploy.conf + reuter.ini to
# each host, where the DEPLOY_PRE_PROVISION_CMD hook
# bin/deploy/inject-private-config.sh restores them after the repo swap), then
# the framework `pf-deploy.sh` CLI (vendor/bin/pf-deploy.sh) — which also runs
# its built-in per-host steps (gen-env/db-check on every host, cron install on
# `worker` hosts) — and finally the consumer post-deploy step
# bin/deploy/server-side-post-deploy.sh, covering only simox's own tags
# (`web`). The provisioning extra stays bin/deploy/provision-extra.sh via
# DEPLOY_INIT_CMD.
# Generic dev-init steps delegate to the Composer-delivered scripts in
# vendor/bin (init-local-env.sh from the `judijasa/php-daas-framework` package);
# this Makefile keeps only the consumer-specific steps (git hooks, private
# config, hosts, ssh config) plus the `deploy` entrypoint. The dev MariaDB
# daemon is owned by ema's per-instance sandbox lifecycle (`ema sandbox` /
# `ema start` / `ema stop`) — this Makefile no longer initializes or starts a
# shared daemon.

SHELL := $(shell which bash 2>/dev/null)

# Machine paths are derived from the repo root and owned by this Makefile
# (not by the environment): .env is a regenerated snapshot and nothing
# exports these into the shell anymore. The defaults let every target run
# standalone, e.g. `make dev-init` via ssh where no env vars exist.
REPO_PATH = $(CURDIR)
REPO_VAR = $(REPO_PATH)/var
REPO_LOG = $(REPO_VAR)/log

_dev-init: DEV_LOG_DIR = $(REPO_LOG)
_dev-init: TAG_BEGIN = \# generated: simox-hosts
_dev-init: TAG_END   = \# end: simox-hosts

.PHONY: help dev-init deploy _dev-assert-nix _dev-init _dev-init-git-hooks _dev-create-dirs \
    _dev-init-composer _dev-init-private-config _dev-update-hosts _dev-ssh-config _dev-init-local-env

help:
	@echo "Available targets:"
	@echo "  dev-init   - Run ONCE after cloning locally to build the dev sandbox"
	@echo "  deploy     - Deploy to [prod] (args via ARGS)"

dev-init: _dev-assert-nix _dev-init

# Production deploy: run the consumer private-config pipeline, wrap the
# framework CLI, then run the consumer post-deploy step (`web`) per host. Pass
# deploy args via ARGS (empty = every [prod] host).
deploy:
	@bin/deploy.sh $(ARGS)

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
# each entry becomes `ssh simox-<name>` as root with the project key.
_dev-ssh-config:
	@vendor/bin/gen-ssh-config simox --user root --key ~/.ssh/simox-sshkey

_dev-init-local-env:
	@vendor/bin/init-local-env.sh
