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

path=$root_dir/srv
workdir=/tmp/db_schemas_$(date +%Y-%m-%d_%H:%M:%S)
mkdir $workdir
file0=$workdir/schemas.sql
touch $file0
file1=$path/simo.sql
#file2=$path/cursorseq.sql
cat "$file1" >> "$file0"
#echo "" >> "$file0"
#cat "$file2" >> "$file0"

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
