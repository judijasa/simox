{
  description = "A Nix flake for the simox project";

  inputs = {
    # Tracking the unstable channel for the latest packages
    # Main input for your everyday, up-to-date packages
    nixpkgs.url = "github:nixos/nixpkgs/nixpkgs-unstable";

    # A specific historical commit chosen because it contains the exact version you need
    # (in this case jq)
    # Not all packages have an explicit version attribute like php84 or mariadb_118
    # nixpkgs-pinned.url = "github:nixos/nixpkgs/e6f23dc08d3624daab7094b701aa3954923c6bbb";

    utils.url = "github:numtide/flake-utils";

    ema.url = "github:judijasa/ema";

    # Public repo. Fetched through git (respects .gitignore), so runtime
    # artifacts such as var/mariadb/mysql.sock never enter the Nix store
    # (Nix rejects sockets with "unsupported type").
    php_daas_framework.url = "github:judijasa/php_daas_framework";
  };

  outputs = { self, nixpkgs, utils, ema, php_daas_framework }:
    utils.lib.eachDefaultSystem (system:
      let
        pkgs = import nixpkgs { inherit system; };
        # Pinned packages evaluated strictly from our historical commit input
        # pkgsPinned = import nixpkgs-pinned { inherit system; };
        
        #bashPkg = pkgsPinned.bash;
        bashPkg = pkgs.bash;
        gitPkg = pkgs.git;
        # gnumakePkg = pkgs.gnumake; # reproducible makefiles
        # jqPkg = pkgsPinned.jq;
        jqPkg = pkgs.jq;
        mariadbPkg = pkgs.mariadb_118;
        # phpPkg = pkgs.php84;  # without extensions
        phpPkg = pkgs.php84.withExtensions ({ all, enabled }: 
          enabled ++ [
            all.mysqli 
            all.pdo_mysql
            all.bz2  # required by jerome-breton composer dependency
          ]
        );
        # Make sure Composer uses this php, as it has the required extensions.
        # phpComposer = pkgs.php84Packages.composer; (discarded)
        phpComposer = pkgs.php84Packages.composer.override {
          php = phpPkg;
        };
        phpLinter = pkgs.phpstan;  # Your choice for dev php linter
        pre-commit = pkgs.pre-commit; # pre-commit (Python) Framework
        tmuxPkg = pkgs.tmux;
        emaPkg = ema.packages.${system}.default;
        phpDaasFrameworkPkg = php_daas_framework.packages.${system}.default;

        # simox↔php_daas_framework integration point: phprun reads PHPRUN_*
        # only (no SIMOX_* fallback in the framework). Each wrapper below
        # provides those vars from simox context, so no env var needs to be
        # set on the server or duplicated in /etc/environment.
        #
        # Dev: derive PHPRUN_* from the SIMOX_* exports of the dev shell.
        phprunDev = pkgs.writeShellScriptBin "phprun" ''
          export PHPRUN_REPO_PATH="''${PHPRUN_REPO_PATH:-''${SIMOX_REPO_PATH:-}}"
          export PHPRUN_LOG_PATH="''${PHPRUN_LOG_PATH:-''${SIMOX_LOG_PATH:-}}"
          export PHPRUN_REUTER_INI="''${PHPRUN_REUTER_INI:-''${SIMOX_REPO_PATH:-}/etc/reuter.ini}"
          exec ${phpDaasFrameworkPkg}/bin/phprun "$@"
        '';
        # Prod: the repo layout is fixed, so paths are baked in at build time.
        # Overridable for ad-hoc testing. EMA_TARGET selects the [prod] section
        # of reuter.ini for DB-using agents.
        phprunProd = pkgs.writeShellScriptBin "phprun" ''
          export PHPRUN_REPO_PATH="''${PHPRUN_REPO_PATH:-/srv/apps/simox}"
          export PHPRUN_LOG_PATH="''${PHPRUN_LOG_PATH:-/var/log/simox}"
          export PHPRUN_REUTER_INI="''${PHPRUN_REUTER_INI:-/etc/simox/reuter.ini}"
          export EMA_TARGET="''${EMA_TARGET:-prod}"
          exec ${phpDaasFrameworkPkg}/bin/phprun "$@"
        '';

        # Common packages shared by dev and prod. phpDaasFrameworkPkg is NOT
        # listed: phprunDev/phprunProd wrap its binary, pulling it into the
        # closure through the exec reference above.
        commonPackages = [
          bashPkg  # If removed, modify SHELL in etc/cron.d/orchestrate
          # gnumakePkg
          jqPkg
          # mariadbPkg  # nix build for stateful systems is anti-pattern
          # vendor/ is in .gitignore. Generate vendor/ (via composer)
          # in prod server to avoid accidental dirty deployments.
          emaPkg
          phpComposer
          phpPkg
          tmuxPkg
        ];
      in
      {
        # PRODUCTION ARTIFACT (Built when running 'nix build')
        # This builds the raw binaries, but DOES NOT spin up background services.
        packages.default = pkgs.symlinkJoin {
          name = "prod-dependencies";
          paths = commonPackages ++ [ phprunProd ];
        };

        # DEVELOPMENT ENVIRONMENT (Triggered via 'nix develop')
        devShells.default = pkgs.mkShell {
          buildInputs = commonPackages ++ [
            phprunDev
            gitPkg
            mariadbPkg
            phpLinter
            pre-commit
          ];
          shellHook = ''
            # Dynamic path: binds variables natively to your local repository directory
            export SIMOX_REPO_PATH="$PWD"
            export SIMOX_VAR_PATH="$SIMOX_REPO_PATH/var"
            export SIMOX_LOG_PATH="$SIMOX_VAR_PATH/log"
            export PROD_USER="simox"

            # Localizing paths securely to avoid any Production server interference
            export MYSQL_BASE_DIR="$SIMOX_VAR_PATH/mariadb"
            export MYSQL_DATA_DIR="$MYSQL_BASE_DIR/data"
            export MYSQL_UNIX_PORT="$MYSQL_BASE_DIR/mysql.sock"
            export MYSQL_PID_FILE="$MYSQL_BASE_DIR/mysql.pid"

            # Initialize the database if missing
            if [ -d "$MYSQL_DATA_DIR" ] && [ ! -S "$MYSQL_UNIX_PORT" ]; then
              # Start the daemon in the background safely
              echo "Starting isolated MariaDB server..."
              mysqld --datadir="$MYSQL_DATA_DIR" --pid-file="$MYSQL_PID_FILE" --socket="$MYSQL_UNIX_PORT" --skip-networking > /dev/null 2>&1 &
              
              MARIADB_PID=$!

              # Clean up background execution seamlessly upon exiting the shell
              trap "echo 'Stopping local MariaDB server...'; kill $MARIADB_PID; wait $MARIADB_PID 2>/dev/null" EXIT
            fi

            [[ -f "$SIMOX_REPO_PATH/.env" ]] && source "$SIMOX_REPO_PATH/.env"

            export EMA_TARGET="local"

            # Customize the prompt (PS1)
            # Define ANSI color codes for readability
            CYAN='\033[0;36m'
            PURPLE='\033[0;35m'
            GREEN='\033[0;32m'
            NC='\033[0m' # No Color
            export PS1="\[$CYAN\] \u@\h:\[$GREEN\]\w\[$NC\]\$ "
       
            # Inherit nix shell env in tmux (doesn't include PS1)
            # Requires `set -g default-command ...` in .tmux.conf
            PROJECT_NAME="simox"
            alias tmux="command tmux -L \$PROJECT_NAME new-session -A -s \$PROJECT_NAME"
          '';
        };
      }
    );
}
