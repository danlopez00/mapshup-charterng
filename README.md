mapshup-charterng
=================

The catalog application of the "International Charter Space and Major Disasters" - http://engine.mapshup.info/charterng/

Metadata format
===============

The metadata files imported within the catalog should be aligned with the following convention :

    1. The metadata file sould be a zip file called [CALLID]_[FORMAT]_XXXXX.ZIP where :
          - CALLID shall be the CALLID wich your product will be linked to
          - FORMAT : is the metadata format that is describe within the ZIP file
                DIMAP       (SPOT format used by CNES, DMC)
                F2          (Formosat2)
                K2          (Kompsat-2)(same as OPT)
                SACC
                RS1         (Radarsat1)
                RS2         (Radarsat2)
                SAR         (HMA SAR used by DLR, ESA)
                OPT         (HMA OPT used by ESA, JAXA, KARI, CBERS)
                IRS
                LANDSAT
                PHR	    (Pléiades image used by CNES)
          - XXXX can be anything you want (usefull if you have more that one product linked to the same CALLID)

     NOTE : the callid must be on three digit (e.g. 53 must be written 053)
     For example if you want to link a DIMAP product to CALLID 53, then a valid zip file will be called 053_DIMAP_01.ZIP


    2. The zip file should contain at least
             - a metadata file (format depends on Agencies)
	     - (optional) a thumbnail image (small preview of the product image - typically a 125x125 pixels image) usually called ICON.JPG
	     - (optional) a quicklook image (bigger preview of the product image - typically a 500x500 pixels image) usually called PREVIEW.JPG
           

     NOTE on files :
             - JAXA don't have PREVIEW.JPG and ICON.JPG
             - CBERS have "quicklook.jpg" instead of PREVIEW.JPG and don't have ICON.JPG
             - CBERS zipped files are within a directory (not zipped directly at the root)
             - EOP metadata file can be in uppercase or lowercase
             - IRS have variable metadata and jpeg filenames (can be .jpeg or .jpg)
             - DIMAP and F2 files are METADATA.DIM, ICON.JPG and PREVIEW.JPG

Installation
============

This document supposes that the current sources are located within the $CHARTERNG_HOME directory and that the web application build from the sources will be installed in $CHARTERNG_TARGET directory

Prerequesites
-------------

A linux server with at least 20 Go of hardrive free space and the following applications running :

* PHP (v5.3.6+)	both in command line and in Apache
* PHP Curl (v7.21.3+)
* Apache (v2.2.17+) with PHP support
* PostgreSQL (v8.4+)
* PostGIS (v1.5.1+)
* mapserver (v6+)   (optional)
* GDAL (1.8+)
* pure-ftpd compiled with uploadscript


Install and configure database
------------------------------

1. Install database

        cd $CHARTERNG_HOME/manage/installation
        ./charterngInstallDB.sh -d path_to_postgis_directory -u database_user_name -p database_user_passwd

2. Edit $CHARTERNG_HOME/manage/config.php to set global variables values

3. Populate disasters table from ESA feed

        $CHARTERNG_HOME/manage/charterngInsertDisasters.php $CHARTERNG_HOME/manage

4. Populate acquisitions table from zips archives

    It is assumed that CHARTERNG_ARCHIVES directory contains all metadata zips. These zips follow the naming convention described previously.

        $CHARTERNG_HOME/manage/charterIngestAll.php $CHARTERNG_HOME/manage ALL ALL

5. Populate aois table from aois shapefile

    First delete aois table content :

        psql -U postgres -d charterng << EOF
        DELETE FROM aois;
        EOF

     Insert aois from shapefile (you must get a shapefile of AOIs otherwise skip steps 5. and 6.)

        shp2pgsql -s 4326 -W LATIN1 -a -g the_geom $CHARTERNG_HOME/aois/Charter_activations_aois.shp aois > dump.txt
        psql -U postgres -d charterng -h localhost -f dump.txt
        /bin/rm dump.txt

6. Create AOIs mapfile under $CHARTERNG_HOME/mapserver
    
        $CHARTERNG_HOME/manage/charterngCreateMapfile.php $CHARTERNG_HOME/manage $CHARTERNG_HOME/mapserver


Build mapshup client
--------------------

1. Edit $CHARTERNG_HOME/src/ws/config.php to set global variables values

2. Launch complete 

        ./build.sh -a -t $CHARTERNG_TARGET


Configure Apache
----------------

Add the following rule to /etc/apache2/sites-available/default file

        Alias /charterng/ "/$CHARTERNG_TARGET/"
        <Directory "/$CHARTERNG_TARGET/">
            Options -Indexes -FollowSymLinks
            AllowOverride None
            Order allow,deny
            Allow from all
        </Directory>

*Note 1 : depending on the OS (linux, mac, etc.) the apache configuration file can have a different name. The example is valid for Ubuntu server*

*Note 2 : $CHARTERNG_TARGET should be replaced by the its value (i.e. if $CHARTERNG_TARGET=/var/www/charterng, then put /var/www/charterng in the apache configuration file)*

Relaunch Apache

        sudo apachectl restart

Configure pure-ftpd
-------------------

*This step can be skipped if you don't want to set an ftp server. The ftp server is used by agencies to upload metadata file.*

We suppose that pure-ftpd is correctly installed and PURE_VIRTUAL_ROOT is the ftp home directory root for virtual users

1. Login as "root" user

2. Stop pure-ftpd

        /etc/init.d/pure-ftpd stop

3. Tell pure-ftpd to use uploadscript

        echo "yes" > /etc/pure-ftpd/conf/CallUploadScript

4. Edit /etc/default/pure-ftpd-common (or /etc/default/pure-ftpd depending on your OS configuration)
        
        # Replace $CHARTERNG_HOME by its value
        UPLOADSCRIPT=$CHARTERNG_HOME/manage/charterngPureUploadScript.sh

5. Edit $CHARTERNG_HOME/manage/users.txt file with your users


6. Edit $CHARTERNG_HOME/manage/charterngPureUploadScript.sh

        Set CHARTERNG_HOME and PURE_VIRTUAL_ROOT

7. Create ftpgroup and ftpuser

        groupadd ftpgroup
        useradd -g ftpgroup -d /dev/null -s /etc ftpuser

8. Create virtual users

        pure-pw useradd user1 -u ftpuser -g ftpgroup -d /ftphomedir/user1 -N 100
        pure-pw useradd user2 -u ftpuser -g ftpgroup -d /ftphomedir/user2 -N 100

9. Remove quotas !!
        
        pure-pw usermod user -N ''
        pure-pw usermod user -n ''
        
10. Rebuild password database

        pure-pw mkdb


Configure automatic tasks
-------------------------

*This step can be skipped if you don't want to automatically update disasters description from ESA feed.*

1. Edit the cron jobs

        crontab -e

2. add the following line ($CHARTERNG_HOME should be replaced by the right path)

        # Note that this script will be executed every day at 01:00 AM
        00 1 * * * /usr/bin/php $CHARTERNG_HOME/manage/charterngInsertDisasters.php $CHARTERNG_HOME/manage

3. Restart cron

        service cron restart (or /etc/init.d/cron restart)


FAQ
---

1. How to test the application

        Go to http://localhost/charterng/

2. How to manually ingest a new $ZIP product

        $CHARTERNG_HOME/manage/charterIngestAcquisition $CHARTERNG_HOME/manage $ZIP AUTO

3. Check location of disasters against AOIs footprints
 
        The following command detects which disasters location are NOT inside the footprint
        of the corresponding AOIs. This is a usefull test to validate AOIs file.

              1. Enter database prompt

              psql -U postgres -d charterng

              2. Check which disasters location are NOT inside AOIs footprint

                      SELECT callid, gid
                      FROM disasters, aois
                      WHERE disasters.callid = ''|| aois.call_id_1
                      AND NOT ST_Contains(aois.the_geom, disasters.location)
                      ORDER BY callid;

              3. Quit database prompt

              \q

4. How to set hstore keywords

        CREATE EXTENSION hstore;
        ALTER TABLE acquisitions ADD COLUMN keywords hstore;
        UPDATE acquisitions SET keywords = ('"' || (SELECT distinct lower(type) FROM disasters WHERE disasters.callid = acquisitions.callid) || '" => "DISASTER"')::hstore;
