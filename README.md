mapshup-charterng
=================

The catalog application of the "International Charter Space and Major Disasters" - http://engine.mapshup.info/charterng/

Metadata format
===============

The metadata files imported within the catalog should be aligned with the following convention :

    1. The metadata file sould be a zip file called [CALLID]_[FORMAT]_XXXXX.ZIP where :
          - CALLID shall be the CALLID wich your product will be linked to
          - FORMAT shall be on of the following : OPT, SAR, DIMAP, PHR, F2, K2, RS1, RS2, SACC, IRS, LANDSAT
          - XXXX can be anything you want (usefull if you have more that one product linked to the same CALLID)

    2. The zip file should contain at least
             - a metadata file (format depends on Agencies)
	     - (optional) a thumbnail image (small preview of the product image - typically a 125x125 pixels image)
	     - (optional) a quicklook image (bigger preview of the product image - typically a 500x500 pixels image)
           
     NOTE : the callid must be on three digit (e.g. 53 must be written 053)
     For example if you want to link your product to CALLID 53, then a valid zip file will be called 053_01.ZIP

Installation
============

This document supposes that the current sources are locate within the $CHARTERNG_HOME directory and that the web application build from the sources will be installed in $CHARTERNG_TARGET directory

Apache configuration (Linux ubuntu)
--------------------------------------

1. Add the following rule to /etc/apache2/sites-available/default file

        Alias /charterng/ "/$CHARTERNG_TARGET/"
        <Directory "/$CHARTERNG_TARGET/">
            Options -Indexes -FollowSymLinks
            AllowOverride None
            Order allow,deny
            Allow from all
        </Directory>

Note: $CHARTERNG_TARGET should be replaced by the its value (i.e. if $CHARTERNG_TARGET=/var/www/charterng, then put /var/www/charterng in the apache configuration file)

2. Relaunch Apache

        sudo apachectl restart


Database installation and initialisation
----------------------------------------

1. Install database

        cd $CHARTERNG_HOME/manage/installation
        ./charterngInstallDB.sh -d path_to_postgis_directory -u database_user_name -p database_user_passwd

2. Edit $CHARTERNG_HOME/manage/config.php to set global variables values

3. Populate disasters table from ESA feed

        $CHARTERNG_HOME/manage/charterngInsertDisasters.php

4. Populate acquisitions table from zips archives

    It is assumed that CHARTERNG_ARCHIVES directory contains all metadata zips. These zips follow the naming convention described previously.

        $CHARTERNG_HOME/manage/charterIngestAll.php ALL ALL



Build CharterNG application
---------------------------

The first time, you need to peform a complete build

        ./build.sh -a -t $CHARTERNG_TARGET

Once mapshup is cloned and compiled, you need to perform a partial build each time you change a file from the src directory.

        ./build.sh -t $CHARTERNG_TARGET


Test application
----------------

Go to http://localhost/charterng/


Ingest new products
-------------------

To ingest a new $ZIP product

        $CHARTERNG_HOME/manage/charterIngestAcquisition $ZIP AUTO


