#!/bin/bash

# ---- YOU MUST SET THIS VALUES ----

CHARTERNG_HOME=

# -----------------------------------
#
# $1 is the path to the archive file (set by pure-ftpd after succesfull upload)
#
# $2 is the ftp user name
#
# $CHARTERNG_HOME/manage/users.txt should contains a list of users with
# the following structure
#       username|ftphomedir|format|email
#

# Old code - not used
# ROOT_LENGTH=${#PURE_VIRTUAL_ROOT}
# FULL_LENGTH=${#1}
# TMP_FORMAT=${1:$ROOT_LENGTH}
# FORMAT=`echo $TMP_FORMAT | awk -F\/ '{print $1}'`

EMAIL=`cat $CHARTERNG_HOME/manage/users.txt | grep $2 | awk -F\| '{print $4}'`
FORMAT=`cat $CHARTERNG_HOME/manage/users.txt | grep $2 | awk -F\| '{print $3}'`

# Launch ingestion
$CHARTERNG_HOME/manage/charterngIngestAcquisition.php $CHARTERNG_HOME/manage $1 $FORMAT $EMAIL
