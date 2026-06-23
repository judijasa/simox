{
  description = "A Nix flake for the simox project";

  inputs = {
    # Tracking the unstable channel for the latest packages
    # Main input for your everyday, up-to-date packages
    nixpkgs.url = "github:nixos/nixpkgs/nixpkgs-unstable";

    # A specific historical commit chosen because it contains the exact version you need
    # Not all packages have an explicit version attribute like php84 or mariadb_118
    nixpkgs-pinned.url = "github:nixos/nixpkgs/e6f23dc08d3624daab7094b701aa3954923c6bbb";

    utils.url = "github:numtide/flake-utils";
  };

  outputs = { self, nixpkgs, nixpkgs-pinned, utils }:
    utils.lib.eachDefaultSystem (system:
      let
        pkgs = import nixpkgs { inherit system; };
        # Pinned packages evaluated strictly from our historical commit input
        pkgsPinned = import nixpkgs-pinned { inherit system; };
        
        bashPkg = pkgsPinned.bash;
        gitPkg = pkgs.git;
        # Explicitly pinning our chosen package versions
        # Pulling jq from the pinned input instead of the main one
        jqPkg = pkgsPinned.jq;
        mariadbPkg = pkgs.mariadb_118;
        # phpPkg = pkgs.php84; # without extensions
        phpComposer = pkgs.php84Packages.composer;
        phpLinter = pkgs.phpstan; # Your choice for dev php linter
        phpWithExtensions = pkgs.php84.withExtensions ({ all, enabled }: 
          enabled ++ [
            all.mysqli 
            all.pdo_mysql 
          ]
        );
        pre-commit = pkgs.pre-commit; # pre-commit (Python) Framework
        tmuxPkg = pkgs.tmux;
      in
      {
        # 1. PRODUCTION ARTIFACT (Built when running 'nix build')
        # This builds the raw binaries, but DOES NOT spin up background services.
        packages.default = pkgs.symlinkJoin {
          name = "prod-dependencies";
          paths = [
            bashPkg
            jqPkg
            mariadbPkg
            phpWithExtensions
            tmuxPkg
          ];
        };

        # 2. DEVELOPMENT ENVIRONMENT (Triggered via 'nix develop')
        devShells.default = pkgs.mkShell {
          buildInputs = [
            bashPkg
            gitPkg
            jqPkg
            mariadbPkg
            phpComposer
            phpLinter  # Dev ONLY tool
            phpWithExtensions
            pre-commit  # Dev ONLY tool
            tmuxPkg
          ];

          shellHook = ''
            # Dynamic path: binds variables natively to your local repository directory
            export SIMO_REPO_PATH="$PWD"
            mkdir -p "$SIMO_REPO_PATH/var"
            export SIMO_VAR_PATH="$SIMO_REPO_PATH/var"
            mkdir -p "$SIMO_VAR_PATH/log"
            export SIMO_LOG_PATH="$SIMO_VAR_PATH/log"

            # Localizing paths securely to avoid any Production server interference
            export MYSQL_BASE_DIR="$SIMO_VAR_PATH/mariadb"
            export MYSQL_DATA_DIR="$MYSQL_BASE_DIR/data"
            export MYSQL_UNIX_PORT="$MYSQL_BASE_DIR/mysql.sock"
            export MYSQL_PID_FILE="$MYSQL_BASE_DIR/mysql.pid"

            # Initialize the database if missing
            if [ ! -d "$MYSQL_DATA_DIR" ]; then
              echo "Initializing persistent local MariaDB data directory..."
              mysql_install_db --auth-root-authentication-method=normal \
                               --datadir="$MYSQL_DATA_DIR" \
                               --basedir="${mariadbPkg}" \
                               --pid-file="$MYSQL_PID_FILE" > /dev/null 2>&1
            fi

            # Start the daemon in the background safely
            echo "Starting isolated MariaDB server..."
            mysqld --datadir="$MYSQL_DATA_DIR" \
                   --pid-file="$MYSQL_PID_FILE" \
                   --socket="$MYSQL_UNIX_PORT" \
                   --skip-networking > /dev/null 2>&1 &
            
            MARIADB_PID=$!

            # Clean up background execution seamlessly upon exiting the shell
            trap "echo 'Stopping local MariaDB server...'; kill $MARIADB_PID; wait $MARIADB_PID 2>/dev/null" EXIT

            # Local alias ensuring connections point to the workspace socket
            alias mariadb="mariadb --socket=$MYSQL_UNIX_PORT" 
          '';
        };
      }
    );
}
