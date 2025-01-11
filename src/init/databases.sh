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

path=${root_dir}/srv
workdir=/tmp/db_schemas_$(date +%Y-%m-%d_%H:%M:%S)
mkdir $workdir

# Replace placeholders in the SQL file with actual values
sed "s/{{dbname}}/${DBNAME}/g; s/{{servername}}/${SERVER}/g; s/{{admin_password}}/${ADMIN_PASSWORD}/g; s/{{reader_password}}/${READER_PASSWORD}/g;" "${path}/simo.sql" > temp.sql

file0=${workdir}/schemas.sql
file1=temp.sql

touch $file0
cat "$file1" >> "$file0"
rm temp.sql

#mysql -u "${USER}" -p"${PASSWORD}" "${DATABASE}" <<EOF
#CREATE ...
#EOF

# USER="root"
# mysql -u $USER -p < $file0
#sudo mariadb -u $USER < $file0
# sudo mariadb < $file0
sudo $DBMS < $file0
rm -r $workdir
sudo $DBMS "${DBNAME}"
