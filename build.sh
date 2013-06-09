#!/bin/bash
#
# Take 5 - A SPOT 4 experiment
# Web client build script
#
# Author : Jerome Gasperi @ CNES
# Date   : 2013.06.01
# Version: 1.0
#

# Set default values - can be superseeded by command line
SRC=`pwd`
PROJECT=charterng
COMPILE=NO
CLONE=NO

# TARGET directory is mandatory from command line
usage="## Web client build script\n\n  Usage $0 -t <target directory> [-s <source directory> -p <project name> -a -c]\n\n  -a : performs steps 1. mapshup git clone, 2. mapshup compile and 3. build\n  -c : perform steps 1. mapshup compile and 2.build\n  (By default, only build step is performed)\n"
while getopts "act:s:h" options; do
    case $options in
        a ) CLONE=YES
            COMPILE=YES;;
        c ) COMPILE=YES;;
        t ) TARGET=`echo $OPTARG`;;
        s ) SRC=`echo $OPTARG`;;
        p ) PROJECT=`echo $OPTARG`;;
        h ) echo -e $usage;;
        \? ) echo -e $usage
            exit 1;;
        * ) echo -e $usage
            exit 1;;
    esac
done
if [ "$TARGET" = "" ]
then
    echo -e $usage
    exit 1
fi

# git clone
if [ "$CLONE" = "YES" ]
then
    echo -e " -> Clone mapshup git repository to $SRC/mapshup directory"   
    git clone https://github.com/jjrom/mapshup.git $SRC/mapshup
    rm -Rf $SRC/mapshup/.git
fi

if [ "$COMPILE" = "YES" ]
then
    echo -e " -> Compile mapshup to $TARGET directory"
    /bin/rm -Rf $TARGET
    $SRC/mapshup/utils/packer/pack.sh $SRC/mapshup $TARGET default $SRC/src/config.js $SRC/src/buildfile.txt 0
    rm -Rf $TARGET/s/README_INSTALL.txt
    rm -Rf $TARGET/s/_installdb
fi

echo -e " -> Copy $PROJECT files to $TARGET directory"
cp $SRC/src/config.php $TARGET/s/config.php
if [ ! -d $TARGET/$PROJECT ]
then
    mkdir $TARGET/$PROJECT
fi
cp $SRC/src/index.html $TARGET
cp $SRC/src/CharterNG.js $TARGET/$PROJECT
cp $SRC/src/*.css $TARGET/$PROJECT
cp -R $SRC/src/img $TARGET/$PROJECT
cp $SRC/src/aois.html $TARGET
cp $SRC/src/aois.js $TARGET/$PROJECT

mkdir $TARGET/s/plugins/$PROJECT
cp $SRC/src/ws/* $TARGET/s/plugins/$PROJECT

echo -e " -> done!\n"
