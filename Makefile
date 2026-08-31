# simox Makefile (dev-init + deploy entrypoints).
# Production deploy is the consumer entrypoint bin/deploy.sh: it runs the
# framework `pf-deploy.sh` CLI (vendor/bin/pf-deploy.sh), then the per-host
# post-deploy step bin/deploy/post-pf-deploy.sh (provisioning extra stays
# bin/deploy/provision-extra.sh via DEPLOY_INIT_CMD).
# Generic dev-init steps delegate to the Composer-delivered scripts in
# vendor/bin (init-cluster.sh from the `judijasa/ema` package, init-local-env.sh
# from the `judijasa/php-daas-framework` package); this Makefile keeps only the
# consumer-specific steps (git hooks, hosts) plus the `deploy` entrypoint.

SHELL := $(shell which bash 2>/dev/null)

# Machine paths are derived from the repo root and owned by this Makefile
# (not by the environment): .env is a regenerated snapshot and nothing
# exports these into the shell anymore. The defaults let every target run
# standalone, e.g. `make dev-init` via ssh where no env vars exist.
REPO_PATH = $(CURDIR)
REPO_VAR = $(REPO_PATH)/var
REPO_LOG = $(REPO_VAR)/log
MYSQL_BASE_DIR = $(REPO_VAR)/mariadb
MYSQL_DATA_DIR = $(MYSQL_BASE_DIR)/data
MYSQL_UNIX_PORT = $(MYSQL_BASE_DIR)/mysql.sock
MYSQL_PID_FILE = $(MYSQL_BASE_DIR)/mysql.pid

_dev-init: DEV_VAR_DIR = $(REPO_VAR)
_dev-init: DEV_DB_DIR = $(MYSQL_BASE_DIR)
_dev-init: DEV_DB_DATA_DIR = $(MYSQL_DATA_DIR)
_dev-init: DEV_DB_UNIX_PORT = $(MYSQL_UNIX_PORT)
_dev-init: DEV_DB_PID_FILE = $(MYSQL_PID_FILE)
_dev-init: DEV_LOG_DIR = $(REPO_LOG)
_dev-init: TAG_BEGIN = \# generated: simox-hosts
_dev-init: TAG_END   = \# end: simox-hosts

.PHONY: help dev-init deploy _dev-assert-nix _dev-init _dev-init-git-hooks _dev-create-dirs \
    _dev-init-cluster _dev-init-composer _dev-update-hosts _dev-init-local-env

help:
	@echo "Available targets:"
	@echo "  dev-init   - Run ONCE after cloning locally to build the dev sandbox"
	@echo "  deploy     - Deploy to [prod] (args via ARGS, e.g. make deploy ARGS=\"--init\")"

dev-init: _dev-assert-nix _dev-init

# Production deploy: wrap the framework CLI, then run the consumer post-deploy
# step per host. Pass deploy args via ARGS (empty = every [prod] host).
deploy:
	@bin/deploy.sh $(ARGS)

_dev-assert-nix:
	@if [ -z "$$IN_NIX_SHELL" ]; then \
	    echo "ERROR: This target must be run inside 'nix develop'"; \
	    exit 1; \
	fi

# composer runs before cluster: init-cluster.sh is Composer-delivered
# (vendor/bin/init-cluster.sh), so vendor/bin must exist first.
_dev-init: _dev-init-git-hooks _dev-create-dirs _dev-init-composer _dev-init-cluster _dev-update-hosts _dev-init-local-env
	@echo "Developer environment successfully initialized."

_dev-init-git-hooks:
	@bin/dev/init-git-hooks.sh

_dev-create-dirs:
	@echo "Creating local logging and storage directories..."
	mkdir -p $(DEV_LOG_DIR) $(DEV_DB_DATA_DIR)

_dev-init-cluster:
	@vendor/bin/init-cluster.sh "$(DEV_DB_DATA_DIR)" "$(DEV_DB_PID_FILE)" "$(DEV_DB_UNIX_PORT)"

_dev-init-composer:
	@echo "Removing vendor/ if exists..." 
	-rm -rf vendor
	@echo "Running composer install..."; 
	composer install

_dev-update-hosts:
	@bin/dev/update-hosts.sh "$(TAG_BEGIN)" "$(TAG_END)"

_dev-init-local-env:
	@vendor/bin/init-local-env.sh
