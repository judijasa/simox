#!/bin/bash

# check you are in repo root directory
root_dir=/srv/git/SIMOExpress
if [[ "$PWD" != $root_dir ]]
then
  echo "This command must be executed from the root directory of the repository."
  exit
fi

path=/srv/git/SIMOExpress/srv
workdir=/tmp/schemas-B9U4319C
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

USER="root"
mysql -u $USER -p < $file0
rm -r $workdir
