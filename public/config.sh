
##################################################
# A popular file format for configuration files is INI
# I choose it in order to have a common config file for my BASH and PHP files.
# Making comments within INI file format:
# www.w3schools.io/file/ini-comments-syntax/
#
# Difference between servername and hostname:
# www.differencebetween.net/technology/difference-between-hostname-and-server-name/
#
# To show database profile:
# serverfault.com/questions/129635/how-do-i-find-out-what-my-ip-address-of-my-mysql-host-is
# To show database username:
# stackoverflow.com/questions/4093603/how-do-i-find-out-my-mysql-url-host-port-and-username/56656162
# Default local IP is '127.0.0.1' and or 'localhost'
#
# The mysql user in this script must have
# read and write privileges
#
# WARNING: Comments in this script must have
# their own line
#
# This script must have read/write/exec
# privilege to access class 'user' and
# none to any other access class
# How to manage file privileges at
# website: kb.iu.edu/d/abdb
#
# Show privileges: ls -l filename
# Output structure: user -- group -- other
#
##################################################

PATH2CASPER="vendor/bin/"
#SITE="https://simo-ppal.cnsc.gov.co/#ofertaEmpleo"
#https://simo.cnsc.gov.co/#homeCiudadano # Sometimes #ofertaEmpleo redirects to #homeCiudadano
SITE="https://simo.cnsc.gov.co/#ofertaEmpleo"
#SITE="https://simo.cnsc.gov.co" # redirects to https://simo.cnsc.gov.co/#homeCiudadano
# Using inet server, localhost or 127.0.0.1, because db server in same location as web server
SERVER="127.0.0.1"
DBNAME="simo"
