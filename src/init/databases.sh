#!/bin/bash

if [[ ! -n $IN_NIX_SHELL ]]; then
  source /etc/environment  # SIMO_REPO_PATH, SIMO_LOG_PATH
fi

root_dir=$SIMO_REPO_PATH

if [[ "$PWD" != $root_dir ]]
then
  echo "This command must be executed from the root directory of the repository."
  exit
fi

if [[ $EUID -eq 0 ]]; then
    echo "Execute without sudo."
    exit 1
fi

# rand alphanum str to use as suffix in some directory names
# not used at the moment
#randir() {
    #local res=$(cat /proc/sys/kernel/random/uuid | sed 's/[-]//g' | head -c 16 | tr '[:lower:]' '[:upper:]'; echo;)
    #echo "$res"
#}

source src/config.sh

workdir=/tmp/db_schemas_$(date +%Y-%m-%d_%H:%M:%S)
mkdir $workdir

agg_upgrades_pseudo_sql_file=${workdir}/pseudo_query.sql # query with placeholders
agg_upgrades_sql_file=${workdir}/query.sql

cat "${root_dir}/srv/simo.sql" >> "$agg_upgrades_pseudo_sql_file"

# Replace placeholders in the SQL file with actual values
sed "s/{{dbname}}/${DBNAME}/g; s/{{servername}}/${SERVER}/g; s/{{admin_password}}/${ADMIN_PASSWORD}/g; s/{{reader_password}}/${READER_PASSWORD}/g;" "$agg_upgrades_pseudo_sql_file" > "$agg_upgrades_sql_file"

# Execute SQL
"$DBMS" -u root < "$agg_upgrades_sql_file" 2>/dev/null || \
sudo env PATH="$PATH" "$DBMS" < $agg_upgrades_sql_file

rm -r $workdir

"$DBMS" -u root "${DBNAME}" 2>/dev/null || sudo env PATH="$PATH" "$DBMS" "${DBNAME}"

exit 0
