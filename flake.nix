{
  description = "A Nix flake for the simox project";

  inputs = {
    # Tracking the unstable channel for the latest packages
    # Main input for your everyday, up-to-date packages
    nixpkgs.url = "github:nixos/nixpkgs/nixpkgs-unstable";

    utils.url = "github:numtide/flake-utils";
  };

  outputs = { self, nixpkgs, utils }:
    utils.lib.eachDefaultSystem (system:
      let
        pkgs = import nixpkgs { inherit system; };

        # The PHP extension set mirrors the framework's composer.json `ext-*`
        # contract (mysqli/pdo_mysql for the DB layer, bz2 for the
        # jakoch/phantomjs-installer composer side-effect): the flake makes
        # the extensions exist/loadable, and composer.json enforces them at
        # install (missing → hard failure).
        #
        # Security: PHP 8.4.27 fixes two critical unauthenticated RCEs
        # (CVE-2026-109618, CVE-2026-109619) present in <= 8.4.26. The pinned
        # nixpkgs still ships 8.4.23, so we pin the source to the 8.4.27
        # tarball until nixpkgs catches up.
        # TODO: revert to plain `pkgs.php84` once the pinned nixpkgs carries
        # php84 >= 8.4.27.
        #
        # Built straight from generic.nix (not `pkgs.php84.override`) so
        # `packageOverrides` reaches the php-packages scope: 8.4.27's new OOM
        # regression tests (CVE-2026-109618) spawn php-cgi subprocesses that
        # fail under the nix sandbox, aborting the build even though the
        # extension compiles and the fix ships in the source. Skip the filter
        # suite; the extension list mirrors nixpkgs' php84 default.
        php84Base = pkgs.callPackage (pkgs.path + "/pkgs/development/interpreters/php/generic.nix") {
          stdenv = if pkgs.stdenv.cc.isClang then pkgs.llvmPackages.stdenv else pkgs.stdenv;
          pcre2 = pkgs.pcre2.override { withJitSealloc = false; };
          version = "8.4.27";
          hash = "sha256-slMKh3xyL0sIyeF0owywi6wrfhkD7m6eMV8KqfX4LCQ=";
          packageOverrides = final: prev: {
            extensions = prev.extensions // {
              filter = prev.extensions.filter.override { doCheck = false; };
            };
          };
        };
        php84 = php84Base.withExtensions ({ all, ... }:
          with all;
          [
            bcmath calendar curl ctype dom exif fileinfo filter ftp gd gettext gmp iconv intl ldap mbstring mysqli mysqlnd openssl pcntl pdo pdo_mysql pdo_odbc pdo_pgsql pdo_sqlite pgsql posix readline session simplexml sockets soap sodium sysvsem sqlite3 tokenizer xmlreader xmlwriter zip zlib
          ]
          ++ pkgs.lib.optionals (pkgs.lib.versionOlder "8.4.27" "8.4") [ all.imap ]
          ++ pkgs.lib.optionals (pkgs.lib.versionOlder "8.4.27" "8.5") [ all.opcache ]
        );
        phpPkg = php84.withExtensions ({ all, enabled }:
          enabled ++ [
            all.mysqli
            all.pdo_mysql
            all.bz2
          ]
        );
        # Make sure Composer uses this php, as it has the required extensions.
        phpComposer = pkgs.php84Packages.composer.override {
          php = phpPkg;
        };
        # readline: fixes arrow keys in nix shell on NixOS (without it, arrows produce escape sequences)
        # ncurses: together with readline, it fixes arrow keys in nix shell + tmux on NixOS
        bashPkg = pkgs.bashInteractive;  # Includes readline + ncurses
        gitPkg = pkgs.git;
        mariadbPkg = pkgs.mariadb_118;
        phpLinter = pkgs.phpstan;  # Your choice for dev php linter
        pre-commit = pkgs.pre-commit; # pre-commit (Python) Framework
        # jq: runtime dependency of the `ema` CLI (pinned by the ema package),
        jqPkg = pkgs.jq;
        tmuxPkg = pkgs.tmux;
        # casperjs' shebang is `#!/usr/bin/env python`; python3 ships it.
        pythonPkg = pkgs.python3;

        # Common packages shared by dev and prod: the environment binaries
        # only. Framework + ema code (CLIs, dev scripts, pf-provision.sh) is
        # Composer-delivered to vendor/bin, so it is absent here; `make
        # dev-init` runs composer install to populate vendor/bin.
        commonPackages = [
          bashPkg
          phpPkg
          phpComposer
          jqPkg
          tmuxPkg
          pythonPkg
        ];
      in
      {
        # PRODUCTION ARTIFACT (Built when running 'nix build')
        # This builds the raw environment binaries, but DOES NOT spin up
        # background services.
        packages.default = pkgs.symlinkJoin {
          name = "prod-dependencies";
          paths = commonPackages;
        };

        # DEVELOPMENT ENVIRONMENT (Triggered via 'nix develop')
        devShells.default = pkgs.mkShell {
          buildInputs = commonPackages ++ [
            gitPkg
            mariadbPkg
            phpLinter
            pre-commit
          ];
          shellHook = ''
            # Set SHELL to bashInteractive so tmux uses bash with readline support
            export SHELL="${pkgs.bashInteractive}/bin/bash"

            # Private config freshness: materialize the real etc/ files from the
            # private repo (.private-source) on every shell entry, so a re-entered
            # shell never runs on stale etc/. Idempotent, and a no-op without
            # .private-source (a public checkout stays on its committed templates).
            # `make dev-init` remains the one-time bootstrap (composer install,
            # .env, hooks, hosts, ssh config).
            bin/fetch-private-data

            # PATH (vendor/bin, then bin — so the consumer `deploy` shadows the
            # framework's Composer-delivered one) is set by the framework's
            # dev-shell bootstrap sourced below: pf-shell-enter.sh owns the rule,
            # so this repo and the framework itself resolve their CLIs the same
            # way (including `ema`, which only exists in vendor/bin).
            # .env (git-ignored) is the single machine settings file,
            # generated by `make dev-init` via the framework's
            # init-local-env.sh (vendor/bin). pf-shell-enter.sh loads it (it
            # no longer starts/resumes a MariaDB daemon — the dev sandbox
            # lifecycle is owned by ema); it must be sourced (not executed)
            # from the repo root.
            [ -x vendor/bin/pf-shell-enter.sh ] && source vendor/bin/pf-shell-enter.sh simox

            # Run repo checks (non-mutating, warn-only) after entering the shell.
            # New checks are auto-discovered from checks/.
            for check in checks/*.sh; do
                [ -f "$check" ] && sh "$check"
            done

            # Customize the prompt (PS1)
            # Define ANSI color codes for readability
            CYAN='\033[0;36m'
            PURPLE='\033[0;35m'
            GREEN='\033[0;32m'
            NC='\033[0m' # No Color
            export PS1="\[$CYAN\] \u@\h:\[$GREEN\]\w\[$NC\]\$ "
          '';
        };
      }
    );
}
