# simox Makefile

SHELL := $(shell which bash 2>/dev/null)

# Machine paths are derived from the repo root and owned by this Makefile
# (not by the environment): .env is a regenerated snapshot and nothing
# exports these into the shell anymore. The defaults let every target run
# standalone, e.g. `make prod-init` via ssh where no env vars exist.
REPO_PATH = $(CURDIR)
REPO_VAR = $(REPO_PATH)/var
REPO_LOG = $(REPO_VAR)/log
MYSQL_BASE_DIR = $(REPO_VAR)/mariadb
MYSQL_DATA_DIR = $(MYSQL_BASE_DIR)/data
MYSQL_UNIX_PORT = $(MYSQL_BASE_DIR)/mysql.sock
MYSQL_PID_FILE = $(MYSQL_BASE_DIR)/mysql.pid
PROD_USER = simox

_dev-init: DEV_VAR_DIR = $(REPO_VAR)
_dev-init: DEV_DB_DIR = $(MYSQL_BASE_DIR)
_dev-init: DEV_DB_DATA_DIR = $(MYSQL_DATA_DIR)
_dev-init: DEV_DB_UNIX_PORT = $(MYSQL_UNIX_PORT)
_dev-init: DEV_DB_PID_FILE = $(MYSQL_PID_FILE)
_dev-init: DEV_LOG_DIR = $(REPO_LOG)
_dev-init: TAG_BEGIN = \# generated: simox-hosts
_dev-init: TAG_END   = \# end: simox-hosts

prod-init: PROD_DB_DIR = /var/lib/simox/mariadb
prod-init: PROD_DB_DATA_DIR = $(PROD_DB_DIR)/data
prod-init: PROD_DB_UNIX_PORT = $(PROD_DB_DIR)/mysql.sock
prod-init: PROD_DB_PID_FILE = $(PROD_DB_DIR)/mysql.pid
prod-init: PROD_LOG_DIR = /var/log/simox

.PHONY: help dev-init _dev-assert-nix _dev-init _dev-init-git-hooks _dev-create-dirs \
    _dev-init-cluster _dev-init-composer _dev-update-hosts _dev-init-local-env \
    prod-init _prod-assert-user _prod-create-dirs _prod-init-cluster prod-gen-env

help:
	@echo "Available initialization targets:"
	@echo "  dev-init   - Run ONCE after cloning locally to build the dev sandbox"
	@echo "  prod-init  - Run ONCE on a brand-new production server to provision system state"

dev-init: _dev-assert-nix _dev-init

_dev-assert-nix:
	@if [ -z "$$IN_NIX_SHELL" ]; then \
	    echo "ERROR: This target must be run inside 'nix develop'"; \
	    exit 1; \
	fi

_dev-init: _dev-init-git-hooks _dev-create-dirs _dev-init-cluster _dev-init-composer _dev-update-hosts _dev-init-local-env
	@echo "Developer environment successfully initialized."

_dev-init-git-hooks:
	@bin/dev/init-git-hooks.sh

_dev-create-dirs:
	@echo "Creating local logging and storage directories..."
	mkdir -p $(DEV_LOG_DIR) $(DEV_DB_DATA_DIR)

_dev-init-cluster:
	@bin/dev/init-cluster.sh "$(DEV_DB_DATA_DIR)" "$(DEV_DB_PID_FILE)" "$(DEV_DB_UNIX_PORT)"

_dev-init-composer:
	@echo "Removing vendor/ if exists..." \
	-rm -rf vendor
	@echo "Running composer install..."; \
	composer install

_dev-update-hosts:
	@bin/dev/update-hosts.sh "$(TAG_BEGIN)" "$(TAG_END)"

_dev-init-local-env:
	@bin/dev/init-local-env.sh

###########################
# PRODUCTION INITIALIZATION (Runs directly as root over remote SSH stream)
###########################

prod-init: _prod-assert-user _prod-create-dirs _prod-init-cluster
	@echo "Deploying simox..."

# Regenerate the production .env on the prod server (as root). deploy.sh also
# does this automatically on every deploy, before cron is installed.
prod-gen-env:
	@bin/prod/gen-env.sh

_prod-assert-user:
	@bin/prod/assert-user.sh "$(PROD_USER)"

_prod-create-dirs:
	@bin/prod/create-dirs.sh "$(PROD_LOG_DIR)" "$(PROD_DB_DATA_DIR)" "$(PROD_USER)"

_prod-init-cluster:
	@bin/prod/init-cluster.sh "$(PROD_DB_DATA_DIR)" "$(PROD_USER)"
