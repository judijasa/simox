#!/bin/bash
####
## Exec this bash script:
# bash [filename].sh
## After $ chmod 755 [filename].sh
## exec as
# . [filename].sh
####

# If exec not using CRON, check if you're in repo's root dir
root_dir=/srv/git/SIMOExpress
if [[ "$PWD" != $root_dir ]]
then
  echo "This command must be executed from the repository's root directory."
  exit
fi

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
#DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd ) # redirect to file location
# DIR=$root_dir # redirect to repo's root dir
#cd $DIR
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
#init_pg=1  ## 1st page to be downloaded
#end_pg=$(php get_total_pages.php)
#end_pg=5  ## remove after test # relocated to get_jobs.py

#last_pg=$((init_pg - 1)) ## offset # relocated to get_jobs.py

####################################################
# -lt : less than, -a : AND, -le : less or equal
# Single-bracket [...] syntax is the oldest and most compatible
# Good habit to quote str vars within conditionals (not our case)
# acloudguru.com/blog/engineering/conditions-in-bash-scripting-if-statements
#
## Args for php: stackoverflow.com/questions/6779576/how-to-pass-parameters-from-bash-to-php-script
###################################################

ti=`date +%s`
php src/scripts/simo_indexer/get_jobs.php | tee src/scripts/simo_indexer/err.log # without concurrency
#php src/scripts/simo_indexer/get_jobs.php > src/scripts/simo_indexer/err.log # test

# with concurrency
php -r "require 'src/scripts/simo_indexer/get_jobs.php'; indexer(0, 4);" &
php -r "require 'src/scripts/simo_indexer/get_jobs.php'; indexer(1, 4);" &
php -r "require 'src/scripts/simo_indexer/get_jobs.php'; indexer(2, 4);" &
php -r "require 'src/scripts/simo_indexer/get_jobs.php'; indexer(3, 4);"
#exit # test
tf=`date +%s`
secs=$((tf-ti))
exec_time=$(printf '%02d:%02d:%02d' $((secs%86400/3600)) $((secs%3600/60)) $((secs%60)))
echo "Execution time: $exec_time"; # remove after test

## Record download attempts in activity_monitor tbl
# php src/activity_monitor.php -- "time=${exec_time}&run=${i}&run_max=${imax}&ex_last=${ex_last_pg}&last=${last_pg}&end=${end_pg}" # uncomment after test

## postprocessing of job_offer tbl
php src/scripts/simo_indexer/update_job_offer.php

## Email download status with summary
#opec=$(php get_new_jobs.php)
#php src/scripts/simo_indexer/mail.php -- "opec=${opec}&run=${i}&run_max=${imax}&init=${init_pg}&last=${last_pg}&end=${end_pg}"
