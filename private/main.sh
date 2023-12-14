#!/bin/bash
####
## Exec this bash script:
# bash [filename].sh
## After $ chmod 755 [filename].sh
## exec as
# . [filename].sh
####

##### BEGIN BLOCK: CRON

## Extra commands required for crontab exec of the script

#####################################################
# $PATH is a persistent environment variable;
# crontab commands have a $PATH value (/usr/bin:/bin)
# different from that of Terminal.
# For this reason, some programs working in Terminal
# may not be found when exec from crontab
#echo $PATH
#
#export PHANTOMJS_EXECUTABLE=/usr/local/bin ## opt 1 (issue: requires knowing the name of env var for each program)
#export PATH=$PATH:/usr/local/bin           ## opt 2 (issue: repeated exec under same session creates redundancy in PATH)

# UNCOMMENT THIS OPTION (opt 3) WHEN USING CRON
#export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games:/snap/bin ## opt 3 (use Terminal's)
#####################################################

#####################################################
## By default, crontab execs the script from '/root' directory,
## preveting project's locally stored dependencies to be found.
## SOLUTION: Move to project's directory ($DIR)
## before exec of any dependency.
DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
cd $DIR
####################################################

##### END BLOCK: CRON

# fix bug related with phpcasperjs
export OPENSSL_CONF=dev/null

############################################
# If after running main.sh
# loading data incomplete, run again
# from new init page.
############################################

## Since we don't know ordering of simo table,
## always start scraping from page 1
init_pg=1  ## 1st page to be downloaded
#end_pg=$(php get_total_pages.php)
end_pg=2  ## Test (while testing, comment command `remove_outdated...`)

last_pg=$((init_pg - 1)) ## offset

if [ $init_pg -eq 1 ]; then
## Create table Jobs
php <<EOF
<?php
require 'functions.php';
make_table_Jobs_tmp();
?>
EOF
fi

####################################
# Everytime main.sh is executed,
# we will try to download ALL pages
# executing imax times the
# program: get_jobs.php
####################################

i=0

imax=5  # number of max loops

#waitunit=15

####################################################
# -lt : less than, -a : AND, -le : less or equal
# Single-bracket [...] syntax is the oldest and most compatible
# Good habit to quote str vars within conditionals (not our case)
# acloudguru.com/blog/engineering/conditions-in-bash-scripting-if-statements
#
## Args for php: stackoverflow.com/questions/6779576/how-to-pass-parameters-from-bash-to-php-script
###################################################

while [ $last_pg -lt $end_pg -a $i -lt $imax ]
do

i=$((i + 1))

## tee sends output to stdout (exec_time) and also to specified file (err.log)
#exec_time=$(php get_jobs.php -- "last=${last_pg}&end=${end_pg}" | tee err.log)

ti=`date +%s`
php get_jobs.php -- "last=${last_pg}&end=${end_pg}" | tee err.log
tf=`date +%s`
secs=$((tf-ti))
exec_time=$(printf '%02d:%02d:%02d' $((secs%86400/3600)) $((secs%3600/60)) $((secs%60)))

ex_last_pg=$last_pg

## Update last page loaded
last_pg=$(php <<EOF
<?php
require 'functions.php';
echo last_pg_loaded();
?>
EOF
)

## Record download attempts in table Activity_Monitor
php Activity_Monitor.php -- "time=${exec_time}&run=${i}&run_max=${imax}&ex_last=${ex_last_pg}&last=${last_pg}&end=${end_pg}"

if [ $last_pg -lt $end_pg ]; then
let last_pg=$((last_pg - 1))
## h(hours) m(mins) s(secs)
#sleep $((i * waitunit))h
fi
done

## Redirect to null message:
## mysql: [Warning] ...
## Comment during test
#bash remove_outdated_entries_from_Jobs_tmp.sh > /dev/null 2>&1

## Post-processing table Jobs: Keywords and Depts
#if [ $last_pg -eq $end_pg ]; then
    # php make_Static_Data.php // only once
    php update_Jobs_tmp_with_Static_Data.php
#fi

# make copy of Jobs_tmp named Jobs
bash make_public_table.sh > /dev/null 2>&1

## Email download status with summary
#opec=$(php sampling_new_jobs.php)
#php mail.php -- "opec=${opec}&run=${i}&run_max=${imax}&init=${init_pg}&last=${last_pg}&end=${end_pg}"
