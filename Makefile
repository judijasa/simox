# Makefile
# After cloning repo, execute (only once)
# `make init`
# This to use the pre-commit framework (.pre-commit-config.yaml) instead of .git/hooks

init:
	pre-commit install
