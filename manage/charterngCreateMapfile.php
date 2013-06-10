#!/usr/bin/php
<?php
/*
 * Charter NG - Create AOIs mapfile
 *
 *  @author   Jerome Gasperi
 *  @date     2012.12.02
 *
 */

// This application can only be called from a shell (not from a webserver)
if (empty($_SERVER['SHELL'])) {
    exit;
}

// Get format and callid
if (!$_SERVER['argv'][2]) {
    echo "\n    Usage : " . $_SERVER['argv'][0] . " [CHARTERNG_MANAGE_DIR] [CHARTERNG_MAPSERVER_DIR]\n";
    echo "      Create aois.map mapfile under CHARTERNG_MAPSERVER_DIR directory \n\n";
    echo "         Note : the CHARTERNG_MAPSERVER_DIR is usually CHARTERNG_HOME/mapserver\n\n";
    exit;
}

// Configuration file
include_once $_SERVER['argv'][1] . '/config.php';

define(CHARTERNG_MAPSERVER_DIR, $_SERVER['argv'][2]);

$mapfile = '
MAP
    EXTENT -180.0 -90.0 180.0 90.0
    IMAGECOLOR 255 255 255
    IMAGEQUALITY 100
    IMAGETYPE PNG
    INTERLACE ON
    SIZE 256 256
    STATUS ON
    TRANSPARENT ON
    UNITS DD
    DEBUG 0
    FONTSET "' . CHARTERNG_MAPSERVER_DIR . '/font.list"
    MAXSIZE 3000
    OUTPUTFORMAT
        NAME "AGGPNG"
        DRIVER "AGG/PNG"
        EXTENSION "png"
        MIMETYPE "image/png"
        IMAGEMODE RGBA
        TRANSPARENT ON
        FORMATOPTION "TRANSPARENT=ON"
    END
    WEB
      IMAGEPATH "/tmp/"
      IMAGEURL "/"
      METADATA
        "ows_enable_request"   "*"
        "wms_title"          "WMS for International Disasters Charter"
        "wms_onlineresource" "' . CHARTERNG_MAPSERVER_URL . '?map=' . CHARTERNG_MAPSERVER_DIR . '/aois.map&"
        "wms_srs"            "EPSG:4326 EPSG:3857"
        "wms_feature_info_mime_type"    "text/html"
        "wms_extent"          "-180.0,-90.0,180.0,90.0"
      END
    END
    PROJECTION
      "init=epsg:3857"
    END
    LAYER
        NAME "aois"
        METADATA
           "wms_title" "aois"
           "wms_srs"   "EPSG:4326"
           "wms_extent" "-180.0 -90.0 180.0 90.0"
           "wms_timeformat" "YYYY-MM-DD, YYYY-MM-DDT00:00:00"
           "wms_timeextent" "2000-01-01/2015-01-01"
           "wms_timeitem" "act_date_1"
        END
        DATA "the_geom FROM aois using unique gid using srid = 4326"
        TYPE POLYGON
        EXTENT -180.0 -90.0 180.0 90.0
        LABELITEM "call_id_1"
        CLASS
          NAME "aois"
          STYLE
              WIDTH 1
              OUTLINECOLOR 255 255 255
              COLOR 255 255 0
              OPACITY 30
          END
          LABEL
            COLOR 0 0 0
            FONT arialbd
            POSITION CC
            SIZE 10
            TYPE TRUETYPE
          END
        END
        PROJECTION
            "proj=latlong"
            "ellps=WGS84"
            "datum=WGS84"
        END
        CONNECTION "user=' . CHARTERNG_DB_USER . ' password=' . CHARTERNG_DB_PASSWORD . ' dbname=' . CHARTERNG_DB_NAME . ' host=localhost port=5432"
        CONNECTIONTYPE POSTGIS
        #PROCESSING "CLOSE_CONNECTION=DEFER"
    END
END';

echo 'Writing ' . CHARTERNG_MAPSERVER_DIR . "/aois.map\n"; 
$handle = fopen(CHARTERNG_MAPSERVER_DIR . "/aois.map", 'w');
fwrite($handle, $mapfile);
fclose($handle);
echo "Done!\n"
?>