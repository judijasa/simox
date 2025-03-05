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

workdir=/tmp/db_schemas_$(date +%Y-%m-%d_%H:%M:%S)
mkdir $workdir

agg_upgrades_pseudo_sql_file=${workdir}/pseudo_query.sql # query with placeholders
agg_upgrades_sql_file=${workdir}/query.sql

cat "${root_dir}/srv/simo.sql" >> "$agg_upgrades_pseudo_sql_file"

# Replace placeholders in the SQL file with actual values
sed "s/{{dbname}}/${DBNAME}/g; s/{{servername}}/${SERVER}/g; s/{{admin_password}}/${ADMIN_PASSWORD}/g; s/{{reader_password}}/${READER_PASSWORD}/g;" "$agg_upgrades_pseudo_sql_file" > "$agg_upgrades_sql_file"

#mysql -u "${USER}" -p"${PASSWORD}" "${DATABASE}" <<EOF
#CREATE ...
#EOF

# USER="root"
# mysql -u $USER -p < $agg_upgrades_pseudo_sql_file
#sudo mariadb -u $USER < $agg_upgrades_pseudo_sql_file
# sudo mariadb < $agg_upgrades_pseudo_sql_file
sudo $DBMS < $agg_upgrades_sql_file
rm -r $workdir
sudo $DBMS "${DBNAME}"

exit 0
