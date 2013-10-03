#!/bin/bash

# ---- YOU MUST SET THIS VALUES ----

CHARTERNG_HOME=

# Important - put an ending "/"
PURE_VIRTUAL_ROOT=

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

ROOT_LENGTH=${#PURE_VIRTUAL_ROOT}
FULL_LENGTH=${#1}
TMP_HOMEDIR=${1:$ROOT_LENGTH}
HOMEDIR=`echo $TMP_HOMEDIR | awk -F\/ '{print $1}'`

EMAIL=`cat $CHARTERNG_HOME/manage/users.txt | grep "/"$HOMEDIR"|" | awk -F\| '{print $4}'`
FORMAT=`cat $CHARTERNG_HOME/manage/users.txt | grep "/"$HOMEDIR"|" | awk -F\| '{print $3}'`

# 2013.10.03 - Added for Spotimage
USER=`cat $CHARTERNG_HOME/manage/users.txt | grep "/"$HOMEDIR"|" | awk -F\| '{print $1}'`
if [[ $USER == "cnes_dimap" ]]
	then FORMAT=AUTO
fi

# Launch ingestion
$CHARTERNG_HOME/manage/charterngIngestAcquisition.php $CHARTERNG_HOME/manage $1 $FORMAT $EMAIL
