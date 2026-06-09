{ pkgs ? import <nixpkgs> {} }:

pkgs.mkShell {
  buildInputs = [
    pkgs.mariadb
    # Keep your other existing packages here...
  ];

  shellHook = ''
    # Create dedicated repo var directory if missing
    export VAR_BASE_DIR=~/var/simox
    if [ ! -d "$VAR_BASE_DIR" ]; then
        mkdir -p "$VAR_BASE_DIR"
        mkdir -p $VAR_BASE_DIR/log
    fi

    # 1. Setup localized directory paths so data stays in your project folder
    export MYSQL_BASE_DIR=$VAR_BASE_DIR/mariadb
    export MYSQL_DATA_DIR=$MYSQL_BASE_DIR/data
    export MYSQL_UNIX_PORT=$MYSQL_BASE_DIR/mysql.sock
    export MYSQL_PID_FILE=$MYSQL_BASE_DIR/mysql.pid

    # 2. Initialize the database if this is the first time running the shell
    if [ ! -d "$MYSQL_DATA_DIR" ]; then
      echo "Initializing persistent MariaDB data directory in var/mariadb/..."
      mysql_install_db --auth-root-authentication-method=normal \
                       --datadir=$MYSQL_DATA_DIR \
                       --basedir=${pkgs.mariadb} \
                       --pid-file=$MYSQL_PID_FILE > /dev/null 2>&1
    fi

    # 3. Start the MariaDB daemon in the background
    echo "Starting MariaDB server..."
    mysqld --datadir=$MYSQL_DATA_DIR \
           --pid-file=$MYSQL_PID_FILE \
           --socket=$MYSQL_UNIX_PORT \
           --skip-networking > /dev/null 2>&1 &
    
    # Save the background process ID
    MARIADB_PID=$!

    # 4. Gracefully shut down the server when you exit the nix-shell
    trap "echo 'Stopping MariaDB server...'; kill $MARIADB_PID; wait $MARIADB_PID 2>/dev/null" EXIT

    # Connect instantly using: mariadb -u root"

    # Alias 'mariadb' so it automatically knows to use our local socket file
    alias mariadb="mariadb --socket=$MYSQL_UNIX_PORT"
  '';
}
