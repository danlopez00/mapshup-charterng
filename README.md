mapshup-charterng
=================

The catalog application of the "International Charter Space and Major Disasters" - http://engine.mapshup.info/charterng/

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

Note: $TARGET should be replaced by the $TARGET value (i.e. if $CHARTERNG_TARGET=/var/www/charterng, then put /var/www/charterng in the apache configuration file)

2. Relaunch Apache

        sudo apachectl restart


Build CharterNG application
---------------------------

The first time, you need to peform a complete build

        ./build.sh -a -t $CHARTERNG_TARGET

Once mapshup is cloned and compiled, you need to perform a partial build each time you change a file from the src directory.

        ./build.sh -t $CHARTERNG_TARGET


Manage scripts configuration
----------------------------

1. Install CharterNG database

        cd $CHARTERNG_HOME/manage/installation
        ./charterngInstallDB.sh -d path_to_postgis_directory -u database_user_name -p database_user_passwd

2. Edit $CHARTERNG_HOME/manage/config.php to set global variables values

