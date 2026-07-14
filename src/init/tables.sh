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

# json with schema dependencies sorted topologically
schemas_json=$(php -r "require 'src/utils/sort_schemas.php'; echo json_encode(build_dependency_graph('simo-C196A24801D24B16')->topological_sort());")

# Convert json to bash array
schemas_array=($(echo "$schemas_json" | jq -r '.[]'))

# Submit all schemas in one temp file
workdir=/tmp/table_schemas_$(date +%Y-%m-%d_%H:%M:%S)
mkdir $workdir

agg_upgrades_pseudo_sql_file=${workdir}/pseudo_query.sql # query with placeholders
agg_upgrades_sql_file=${workdir}/query.sql

# Loop through the array
for schema in "${schemas_array[@]}"
do
    cat "$root_dir/pkg/$schema/upgrade.sql" >> "$agg_upgrades_pseudo_sql_file"
    echo "" >> "$agg_upgrades_pseudo_sql_file" # line break for readability
done

# Replace placeholders in the SQL file with actual values
# Wrap with FK checks disabled so CREATE OR REPLACE on parent tables doesn't fail
# when child tables with FKs already exist in the DB from a previous run.
{ echo "SET FOREIGN_KEY_CHECKS=0;"; \
  sed "s/{{dbname}}/${DBNAME}/g; s/{{servername}}/${SERVER}/g;" "$agg_upgrades_pseudo_sql_file"; \
  echo "SET FOREIGN_KEY_CHECKS=1;"; } > "$agg_upgrades_sql_file"

"$DBMS" -u root "$DBNAME" 2>/dev/null < $agg_upgrades_sql_file || \
sudo env PATH="$PATH" MYSQL_UNIX_PORT="$MYSQL_UNIX_PORT" "$DBMS" "$DBNAME" < $agg_upgrades_sql_file

rm -r $workdir

"$DBMS" -u root "$DBNAME" -e "SHOW TABLES;" 2>/dev/null || \
sudo env PATH="$PATH" MYSQL_UNIX_PORT="$MYSQL_UNIX_PORT" "$DBMS" "$DBNAME" -e "SHOW TABLES;"

exit 0
