#!/bin/bash

# ---- YOU MUST SET THESE VALUES ----

CHARTERNG_HOME=

# DO NOT FORGET THE ENDING "/"

PURE_VIRTUAL_ROOT=

# -----------------------------------

# $1 is the path to the archive file (set by pure-ftpd after succesfull upload)

ROOT_LENGTH=${#PURE_VIRTUAL_ROOT}
FULL_LENGTH=${#1}
TMP_FORMAT=${1:$ROOT_LENGTH}
FORMAT=`echo $TMP_FORMAT | awk -F\/ '{print $1}'`

# Launch ingestion
$CHARTERNG_HOME/manage/charterngIngestAcquisition.php $CHARTERNG_HOME/manage $1 $FORMAT
