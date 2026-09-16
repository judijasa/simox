# simox Makefile (dev-init + deploy entrypoints).
# Production deploy is the consumer entrypoint bin/deploy.sh: it runs the
# framework `pf-deploy.sh` CLI (vendor/bin/pf-deploy.sh) — which also runs the
# built-in per-host steps (fetch-private-data/gen-env/db-check on every host,
# cron install on `worker` hosts) — then the consumer post-deploy step
# bin/deploy/server-side-post-deploy.sh, covering only simox's own tags
# (`web`). The provisioning extra stays bin/deploy/provision-extra.sh via
# DEPLOY_INIT_CMD.
# Generic dev-init steps delegate to the Composer-delivered scripts in
# vendor/bin (init-local-env.sh from the `judijasa/php-daas-framework` package);
# this Makefile keeps only the consumer-specific steps (git hooks, hosts, ssh
# config) plus the `deploy` entrypoint. The dev MariaDB daemon is owned by ema's
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

_dev-init: DEV_LOG_DIR = $(REPO_LOG)
_dev-init: TAG_BEGIN = \# generated: simox-hosts
_dev-init: TAG_END   = \# end: simox-hosts

.PHONY: help dev-init deploy _dev-assert-nix _dev-init _dev-init-git-hooks _dev-create-dirs \
    _dev-init-composer _dev-update-hosts _dev-ssh-config _dev-init-local-env

help:
	@echo "Available targets:"
	@echo "  dev-init   - Run ONCE after cloning locally to build the dev sandbox"
	@echo "  deploy     - Deploy to [prod] (args via ARGS)"

dev-init: _dev-assert-nix _dev-init

# Production deploy: wrap the framework CLI, then run the consumer post-deploy
# step (`web`) per host. Pass deploy args via ARGS (empty = every [prod] host).
deploy:
	@bin/deploy.sh $(ARGS)

_dev-assert-nix:
	@if [ -z "$$IN_NIX_SHELL" ]; then \
	    echo "ERROR: This target must be run inside 'nix develop'"; \
	    exit 1; \
	fi

_dev-init: _dev-init-git-hooks _dev-create-dirs _dev-init-composer _dev-init-local-env _dev-update-hosts _dev-ssh-config
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

# etc/hosts is private data, injected as a symlink by fetch-private-data
# (run by _dev-init-local-env); this must therefore follow it in _dev-init.
_dev-update-hosts:
	@bin/dev/update-hosts.sh "$(TAG_BEGIN)" "$(TAG_END)"

# The generated ssh config reads that same private etc/hosts (injected as a
# symlink by fetch-private-data via _dev-init-local-env), so it must follow
# that step in _dev-init as well: each entry becomes `ssh simox-<name>` as
# root with the project key.
_dev-ssh-config:
	@vendor/bin/gen-ssh-config simox --user root --key ~/.ssh/simox-sshkey

_dev-init-local-env:
	@vendor/bin/init-local-env.sh
