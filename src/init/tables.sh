#!/bin/bash

root_dir=/srv/git/SIMOExpress
if [[ "$PWD" != $root_dir ]]
then
  echo "This command must be executed from the root directory of the repository."
  exit
fi

path=/srv/git/SIMOExpress/pkg
workdir=/tmp/schemas-A8U3209B
mkdir $workdir
file0=$workdir/schemas.sql
touch $file0
file1=$path/activity_monitor.sql
file2=$path/cursorseq.sql
file3a=$path/dpto_colombia.sql
file3b=$path/nivel.sql
file4=$path/estudio_basico.sql
file5=$path/estudio_basico_variaciones.sql
file6=$path/estudio_especializado.sql
file7=$path/estudio_especializado_variaciones.sql
file8=$path/otras_habilidades.sql
file9=$path/otras_habilidades_variaciones.sql
file10=$path/job_offer_snapshot.sql
file11=$path/job_offer.sql
file12=$path/vw_job_offer.sql
file13=$path/job_offer_snapshot_trg_upsert_job_offer.sql
file14=$path/job_offer_snapshot_trg_insert_nivel.sql
cat "$file1" >> "$file0"
echo "" >> "$file0"
cat "$file2" >> "$file0"
echo "" >> "$file0"
cat "$file3a" >> "$file0"
echo "" >> "$file0"
cat "$file3b" >> "$file0"
echo "" >> "$file0"
cat "$file4" >> "$file0"
echo "" >> "$file0"
cat "$file5" >> "$file0"
echo "" >> "$file0"
cat "$file6" >> "$file0"
echo "" >> "$file0"
cat "$file7" >> "$file0"
echo "" >> "$file0"
cat "$file8" >> "$file0"
echo "" >> "$file0"
cat "$file9" >> "$file0"
echo "" >> "$file0"
cat "$file10" >> "$file0"
echo "" >> "$file0"
cat "$file11" >> "$file0"
echo "" >> "$file0"
cat "$file12" >> "$file0"
echo "" >> "$file0"
cat "$file13" >> "$file0"
echo "" >> "$file0"
cat "$file14" >> "$file0"

#mysql -u "${USER}" -p"${PASSWORD}" "${DATABASE}" <<EOF
#CREATE ...
#EOF

USER="root"
DBNAME="simo"
mysql -u $USER -p $DBNAME < $file0
rm -r $workdir
