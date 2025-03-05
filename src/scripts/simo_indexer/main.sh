#!/bin/bash
####
## Exec this bash script:
# bash [filename].sh
## After $ chmod 755 [filename].sh
## exec as
# . [filename].sh
####

source /etc/environment  # SIMO_REPO_PATH
logFile="$SIMO_REPO_PATH/log/crawler.log"

if [ -n "$CRON" ]; then

  ## Extra commands required for crontab exec of the script

  #####################################################
  # $PATH is a persistent environment variable;
  # crontab commands have a $PATH value (/usr/bin:/bin),
  # that sometimes is not in Terminal's $PATH value.
  # For this reason, some programs working in Terminal
  # may not be found when exec from crontab.
  #echo $PATH
  #
  #export PHANTOMJS_EXECUTABLE=/usr/local/bin ## opt 1 (issue: requires knowing the name of env var for each program)
  #export PATH=$PATH:/usr/local/bin           ## opt 2 (issue: repeated exec under same session creates redundancy in PATH)

  ## opt 3: If necessary, UNCOMMENT this option (best so far) when using cron
  # export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games:/snap/bin ## opt 3 (use Terminal's)

  #####################################################
  ## By default, crontab execs the script from '/root' directory,
  ## preveting project's locally stored dependencies to be found.
  ## SOLUTION: Move to project's directory ($DIR)
  ## before exec of any dependency.
  #DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd ) # redirect to file location
  #cd $DIR
  ## Now that you are in the location of the file, redirect to the root location of the repo...
  # $SIMO_REPO_PATH=$(git rev-parse --show-toplevel) # repo root directory path
  cd $SIMO_REPO_PATH
else
  # If exec not using CRON. check if you're in repo's root dir
  # Using git rev-parse is not good because its value assumes execution is
  # somewhere within the repo.
  # $SIMO_REPO_PATH=$(git rev-parse --show-toplevel) # repo root directory path
  if [[ "$PWD" != "$SIMO_REPO_PATH" ]]
  then
    echo "This command must be executed from the repository's root directory."
    exit
  fi
fi

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

if [ ! -f "$logFile" ]; then
  touch "$logFile"
fi

# without concurrency
#php .../simo_indexer/get_jobs.php... | tee -a $logFile # Redirect with append and show result in terminal too
#php .../simo_indexer/get_jobs.php... > $logFile # test: Redirect without append stdout
#php .../simo_indexer/get_jobs.php... >> $logFile 2>&1 # test: Redirect with append stdout and stderr


# concurrency = 1
cmd="require \"src/scripts/simo_indexer/get_jobs.php\"; indexer(0,1);"
msg1="$(date '+%Y-%m-%d %H:%M:%S') - Starting crawler..."
msg2="$(date '+%Y-%m-%d %H:%M:%S') - Finished crawling."

# Check if running in an interactive terminal
if [ -t 1 ]; then
  echo "$msg1" 2>&1 | tee -a $logFile
  php -r "$cmd" 2>&1 | tee -a "$logFile"
  echo "$msg1" 2>&1 | tee -a $logFile
else
  # Running in background: Only log (log file) output
  echo "$msg1" >> $logFile 2>&1
  php -r "$cmd" >> $logFile 2>&1
  echo "$msg2" >> $logFile 2>&1
fi

# concurrency = 4
# When you separate commands with the ampersand (&) it tells the shell to execute
# those commands in the background (except the very last one, unless it has & in front),
# independently and simultaneously. They shared the output stream, whence you get
# intermixed output.
#
# To interact with a program (send signals eg stop, kill, etc.), you can bring a
# background command to the foreground. To do this, use command `jobs` to list
# all background jobs. The command `fg %[ID]` (e.g. fg %3) brings a background
# job (with process ID: [ID]) to the foreground. The commmand `bg` (no args)
# sends it back to the background.

#php -r "require 'src/scripts/simo_indexer/get_jobs.php'; indexer(0, 4);" &
#php -r "require 'src/scripts/simo_indexer/get_jobs.php'; indexer(1, 4);" &
#php -r "require 'src/scripts/simo_indexer/get_jobs.php'; indexer(2, 4);" &
#php -r "require 'src/scripts/simo_indexer/get_jobs.php'; indexer(3, 4);"
# jobs # list background processes
# wait # waits for all processes to finish before proceeding
#exit # test

#tf=`date +%s`
#secs=$((tf-ti))
#exec_time=$(printf '%02d:%02d:%02d' $((secs%86400/3600)) $((secs%3600/60)) $((secs%60)))
#echo "Execution time: $exec_time";

## Record download attempts in activity_monitor tbl
# php src/activity_monitor.php -- "time=${exec_time}&run=${i}&run_max=${imax}&ex_last=${ex_last_pg}&last=${last_pg}&end=${end_pg}" # uncomment after test

## postprocessing of job_offer tbl

msg1="$(date '+%Y-%m-%d %H:%M:%S') - Starting post-crawling process..."
msg2="$(date '+%Y-%m-%d %H:%M:%S') - Finished post-crawling process."

# Check if running in an interactive terminal
if [ -t 1 ]; then
  echo "$msg1" 2>&1 | tee -a $logFile
  php src/scripts/simo_indexer/update_job_offer.php 2>&1 | tee -a $logFile
  echo "$msg2" 2>&1 | tee -a $logFile
else
  # Running in background: Only log (log file) output
  echo "$msg1" >> $logFile 2>&1
  php src/scripts/simo_indexer/update_job_offer.php >> $logFile 2>&1
  echo "$msg2" >> $logFile 2>&1
fi
## Email download status with summary
#opec=$(php get_new_jobs.php)
#php src/scripts/simo_indexer/mail.php -- "opec=${opec}&run=${i}&run_max=${imax}&init=${init_pg}&last=${last_pg}&end=${end_pg}"

exit 0
