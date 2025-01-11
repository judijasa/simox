#!/bin/bash

root_dir=$(git rev-parse --show-toplevel)
if [[ "$PWD" != $root_dir ]]
then
  echo "This command must be executed from the root directory of the repository."
  exit
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
workdir=/tmp/build_schemas_$(date +%Y-%m-%d_%H:%M:%S)
mkdir $workdir
temp_sql_file=$workdir/query.sql
touch $temp_sql_file

# Loop through the array
for schema in "${schemas_array[@]}"
do
    cat "$root_dir/pkg/$schema/upgrade.sql" >> "$temp_sql_file"
    echo "" >> "$temp_sql_file" # line break for readability
done

#mysql -u "${USER}" -p"${PASSWORD}" "${DATABASE}" <<EOF
#CREATE ...
#EOF

#USER="root"
#DBNAME="simo"
#mysql -u $USER -p $DBNAME < $temp_sql_file
sudo $DBMS $DBNAME < $temp_sql_file
rm -r $workdir
sudo $DBMS "${DBNAME}" -e "SHOW TABLES;" -B
