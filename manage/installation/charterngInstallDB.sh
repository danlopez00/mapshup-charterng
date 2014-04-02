#!/bin/bash
#
# CharterNG - Internation Charter Space and Major Disasters catalog application
# Catalog database installation
#
# Author : Jerome Gasperi @ CNES
# Date   : 2012.12.20
# Version: 1.0
#

# Paths are mandatory from command line
USER=charterng
SUPERUSER=postgres
DROPFIRST=NO
DB=charterng
usage="## CharterNG database installation\n\n  Usage $0 -d <PostGIS directory> -p <directory> [-u <database owner username> -s <database SUPERUSER> -F]\n\n  -d : absolute path to the directory containing postgis.sql\n  -p : database owner password\n  -u : dabase username (default "charterng")\n  -s : dabase SUPERUSER (default "postgres")\n  -F : WARNING - suppress existing charterng database\n"
while getopts "d:s:p:u:hF" options; do
    case $options in
        d ) ROOTDIR=`echo $OPTARG`;;
        p ) PASSWORD=`echo $OPTARG`;;
        u ) USER=`echo $OPTARG`;;
        s ) SUPERUSER=`echo $OPTARG`;;
        F ) DROPFIRST=YES;;
        h ) echo -e $usage;;
        \? ) echo -e $usage
            exit 1;;
        * ) echo -e $usage
            exit 1;;
    esac
done
if [ "$ROOTDIR" = "" ]
then
    echo -e $usage
    exit 1
fi
if [ "$PASSWORD" = "" ]
then
    echo -e $usage
    exit 1
fi
if [ "$DROPFIRST" = "YES" ]
then
    dropdb -U $SUPERUSER $DB
fi

postgis=`echo $ROOTDIR/postgis.sql`
projections=`echo $ROOTDIR/spatial_ref_sys.sql`

###### ADMIN ACCOUNT CREATION ######
psql -U $SUPERUSER -d template1 << EOF
CREATE USER $USER WITH PASSWORD '$PASSWORD' NOCREATEDB;
EOF

# Make db POSTGIS compliant
createdb $DB -U $SUPERUSER --o $USER
createlang -U $SUPERUSER plpgsql $DB
psql -d $DB -U $SUPERUSER -f $postgis
psql -d $DB -U $SUPERUSER -f $projections

# Install schema
psql -d $DB -U $SUPERUSER -f charterngDB.sql

# Rights
psql -U $SUPERUSER -d $DB << EOF
GRANT ALL ON geometry_columns to $USER;
GRANT ALL ON geography_columns to $USER;
GRANT SELECT on spatial_ref_sys to $USER;
GRANT SELECT,INSERT,UPDATE,DELETE ON disasters TO $USER;
GRANT SELECT,INSERT,UPDATE,DELETE ON kmls TO $USER;
GRANT SELECT,INSERT,UPDATE,DELETE ON acquisitions TO $USER;
GRANT SELECT,INSERT,UPDATE,DELETE ON aois TO $USER;
GRANT SELECT,UPDATE ON aois_gid_seq TO $USER;
EOF

# VACCUM
vacuumdb --full --analyze -U $SUPERUSER $DB
