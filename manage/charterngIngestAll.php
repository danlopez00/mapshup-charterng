#!/usr/bin/php
<?php
/*
 * Charter NG - Insert historical ZIPs metadata
 *
 *  @author   Jerome Gasperi
 *  @date     2012.12.02
 *
 */

/* 
 *
 * Acquisitions are stored within the "acquisitions" table of the "charterng" database
 * 
 *      CREATE TABLE acquisitions (
 *          identifier        VARCHAR(250) PRIMARY KEY,     -- identifier
 *          parentidentifier  VARCHAR(250),                 -- parentIdentifier
 *          callid            VARCHAR(4),                   -- !! Attached disaster callid !!
 *          startdate         TIMESTAMP,                    -- beginPosition
 *          enddate           TIMESTAMP,                    -- endPosition
 *          platform          VARCHAR(250),                 -- Platform/shortName + Platform/identifier
 *          instrument        VARCHAR(250),                 -- Instrument/shortName
 *          metadata          TEXT,                         -- relative path from the CHARTERNG_ROOT_HTTP to the unzipped XML metadata file
 *          archive           TEXT,                         -- relative path from the CHARTERNG_ROOT_HTTP to the source image if available
 *          quicklook         VARCHAR(250),                 -- relative path from the CHARTERNG_ROOT_HTTP to the quicklook
 *          thumbnail         VARCHAR(250),                 -- relative path from the CHARTERNG_ROOT_HTTP to the thmbnail
 *          modifieddate      TIMESTAMP,
 *          footprint         GEOMETRY (POLYGON)
 *      );
 * 
 */

// Remove PHP NOTICE
error_reporting(E_PARSE);

// Root directory is current directory
include_once getcwd() . '/config.php';
include_once getcwd() . '/lib/functions.php';
include_once getcwd() . '/lib/formatsReader.php';

// Set Timezone
date_default_timezone_set("Europe/Paris");

// This application can only be called from a shell (not from a webserver)
if (empty($_SERVER['SHELL'])) {
    exit;
}

// Get format and callid
if (!$_SERVER['argv'][2]) {
    echo "\n    Usage : " . $_SERVER['argv'][0] . " [CALLID] [FORMAT]\n";
    echo "      Setting 'ALL' to [CALLID] or [FORMAT] will not filter them\n\n"; 
    exit;
}

$_CALLID = $_SERVER['argv'][1];
$_FORMAT = $_SERVER['argv'][2];

// Database connection to Charter NG database
$dbh = pg_connect("host=".CHARTERNG_DB_HOST." dbname=".CHARTERNG_DB_NAME." user=".CHARTERNG_DB_USER." password=".CHARTERNG_DB_PASSWORD) or die(pg_last_error());
    
// Unzip archives directory within the md directory
$srcDir = CHARTERNG_HOME . "/archives/";
$zips = getFilesList($srcDir, array("zip"));
foreach ($zips as $zip) {

    // The disaster CallId and the metadata format are inferred from zip file name
    $infos = explode("_", $zip);
    $callid = $infos[0];
    $format = $infos[1];

    // Filter on CALLID
    if ($_CALLID !== "ALL") {
        if ($callid !== $_CALLID) {
            continue;
        }
    }
    // Filter on FORMAT
    if ($_FORMAT !== "ALL") {
        if ($format !== $_FORMAT) {
            continue;
        }
    }

    $relativeDir = substr($zip, 0, -4) . "/";
    $targetDir = CHARTERNG_METADATA_PATH . $relativeDir;

    echo "Processing $zip\n";

    // If the file is successfully unzipped - process the metadata
    if (unzip($srcDir . $zip, $targetDir, true, true)) {

        echo "  >> unzip to $targetDir\n";

        $json = null;

        // Process metadata depending on metadata format
        switch ($format) {

            /*
             * DIMAP format means 3 files
             *      METADATA.DIM (metadata XML file)
             *      ICON.JPG (thumbnail)
             *      PREVIEW.JPG (quicklook)
             */
            case "DIMAP":

                // METADATA
                $dimaps = getFilesList($targetDir, array("dim"));
                $json = readDIMAP($targetDir . $dimaps[0], false);

                if (!$json["identifier"]) {
                    echo "  >> ERROR! Metadata file is non well formed \n";
                    continue;
                }
                
                $json["metadata"] = $relativeDir . $dimaps[0];

                // THUMBNAIL AND QUICKLOOK
                $images = getFilesList($targetDir, array("jpg", "jpeg"));
                foreach($images as $image) {

                    // Thumbnail
                    if (strpos(strtolower($image), 'icon') === 0) {
                        $json["thumbnail"] = $image;
                    }

                    // Quicklook
                    if (strpos(strtolower($image), 'preview') === 0) {
                        $json["quicklook"] = $image;
                    }
                }

                break;
            /*
             * PHR = DIMAP v2 format means 3 files
             *      METADATA.DIM (metadata XML file)
             *      ICON.JPG (thumbnail)
             *      PREVIEW.JPG (quicklook)
             */
            case "PHR":

                // METADATA
                $dimaps = getFilesList($targetDir, array("dim"));
                $json = readDIMAPv2($targetDir . $dimaps[0]);

                if (!$json["identifier"]) {
                    echo "  >> ERROR! Metadata file is non well formed \n";
                    continue;
                }
                
                $json["metadata"] = $relativeDir . $dimaps[0];

                // THUMBNAIL AND QUICKLOOK
                $images = getFilesList($targetDir, array("jpg", "jpeg"));
                foreach($images as $image) {

                    // Thumbnail
                    if (strpos(strtolower($image), 'icon') === 0) {
                        $json["thumbnail"] = $image;
                    }

                    // Quicklook
                    if (strpos(strtolower($image), 'preview') === 0) {
                        $json["quicklook"] = $image;
                    }
                }

                break;
            
            /*
             * FORMOSAT format means 3 files
             *      METADATA.DIM (metadata XML file)
             *      ICON.JPG (thumbnail)
             *      PREVIEW.JPG (quicklook)
             */
            case "F2":

                // METADATA
                $dimaps = getFilesList($targetDir, array("dim"));
                $json = readF2($targetDir . $dimaps[0], false);
                
                if (!$json["identifier"]) {
                    echo "  >> ERROR! Metadata file is non well formed \n";
                    continue;
                }
                
                $json["metadata"] = $relativeDir . $dimaps[0];

                // THUMBNAIL AND QUICKLOOK
                $images = getFilesList($targetDir, array("jpg", "jpeg"));
                foreach($images as $image) {

                    // Thumbnail
                    if (strpos(strtolower($image), 'icon') === 0) {
                        $json["thumbnail"] = $image;
                    }

                    // Quicklook
                    if (strpos(strtolower($image), 'preview') === 0) {
                        $json["quicklook"] = $image;
                    }
                }

                break;

            /*
             * SACC format means 2 files
             *      ???.txt (metadata text file)
             *      ???.jpg (quicklook)
             */
            case "SACC":

                // METADATA
                $txts = getFilesList($targetDir, array("txt"));
                if (count($txts) === 0) {
                    echo "  >> ERROR! ZIP file is empty \n";
                    continue;
                }
                $json = readSACC($targetDir . $txts[0]);
                $json["metadata"] = $relativeDir . $txts[0];

                // QUICKLOOK
                $images = getFilesList($targetDir, array("jpg", "jpeg"));
                foreach($images as $image) {
                    // Quicklook
                    $json["quicklook"] = $image;
                }
                break;

            /*
             * Radarsat 1 format means 2 files
             *      ???.xml (metadata text file)
             *      ???.tif (quicklook)
             */
            case "RS1":

                // METADATA
                $xmls = getFilesList($targetDir, array("xml"));
                if (count($xmls) === 0) {
                    echo "  >> ERROR! ZIP file is empty \n";
                    continue;
                }
                $json = readRS1($targetDir . $xmls[0]);

                if (!$json["identifier"]) {
                    echo "  >> ERROR! Metadata file is non well formed \n";
                    continue;
                }
                
                $json["metadata"] = $relativeDir . $xmls[0];

                // QUICKLOOK - first check for a jpeg image
                $images = getFilesList($targetDir, array("jpg", "jpeg"));
                foreach($images as $image) {
                    // Quicklook
                    $json["quicklook"] = $image;
                }
                if (!$json["quicklook"]) {
                    $images = getFilesList($targetDir, array("tif"));
                    foreach($images as $image) {

                        // Create a JPEG quicklook with GDAL
                        $jpeg = str_replace('.tif', '.jpg', $image);
                        exec(GDAL_TRANSLATE_PATH . ' -of JPEG ' . $targetDir . $image . ' ' . $targetDir . $jpeg);
                        unlink($targetDir . $jpeg . ".aux.xml");
                        echo "  >> create quicklook from $image\n";

                        // Quicklook
                        $json["quicklook"] = $jpeg;
                    }
                }

                break;

            /*
             * Radarsat 2 format means 2 files
             *      ???.xml (metadata text file)
             *      ???.tif (quicklook)
             */   
            case "RS2":

                // METADATA
                $xmls = getFilesList($targetDir, array("xml"));
                if (count($xmls) === 0) {
                    echo "  >> ERROR! ZIP file is empty \n";
                    continue;
                }
                $json = readRS2($targetDir . $xmls[0]);

                if (!$json["identifier"]) {
                    echo "  >> ERROR! Metadata file is non well formed \n";
                    continue;
                }
                
                $json["metadata"] = $relativeDir . $xmls[0];

                // QUICKLOOK - first check for a jpeg image
                $images = getFilesList($targetDir, array("jpg", "jpeg"));
                foreach($images as $image) {
                    // Quicklook
                    $json["quicklook"] = $image;
                }
                if (!$json["quicklook"]) {
                    $images = getFilesList($targetDir, array("tif"));
                    foreach($images as $image) {

                        // Create a JPEG quicklook with GDAL
                        $jpeg = str_replace('.tif', '.jpg', $image);
                        exec(GDAL_TRANSLATE_PATH . ' -of JPEG ' . $targetDir . $image . ' ' . $targetDir . $jpeg);
                        unlink($targetDir . $jpeg . ".aux.xml");
                        echo "  >> create quicklook from $image\n";

                        // Quicklook
                        $json["quicklook"] = $jpeg;

                    }
                }
                break;

            /*
             * SAR format means 1, 2 or 3 files
             *      EOP.xml (metadata XML file)
             *      PREVIEW.JPG or PREVIEW.jpg (quicklook)
             *      ICON.JPG or ICON.jpg (thumbnail)
             */   
            case "SAR":
                
                // METADATA
                $xmls = getFilesList($targetDir, array("xml"));
                if (count($xmls) === 0) {
                    echo "  >> ERROR! ZIP file is empty \n";
                    continue;
                }
                $json = readEOP($targetDir . $xmls[0]);

                if (!$json["identifier"]) {
                    echo "  >> ERROR! Metadata file is non well formed \n";
                    continue;
                }
                
                $json["metadata"] = $relativeDir . $xmls[0];

                // THUMBNAIL AND QUICKLOOK
                $images = getFilesList($targetDir, array("jpg", "jpeg"));
                foreach($images as $image) {

                    // Thumbnail
                    if (strpos(strtolower($image), 'icon') === 0) {
                        $json["thumbnail"] = $image;
                    }

                    // Quicklook
                    if (strpos(strtolower($image), 'preview') === 0) {
                        $json["quicklook"] = $image;
                    }
                }
                break;

            case "OPT":
                
                // First find the metadata XML file
                $xmls = getFilesList($targetDir, array("xml"));

                // Special case for incorrect ZIP file (i.e. CBERS)
                if (count($xmls) === 0) {
                    $dirs = getDirectoryList($targetDir);
                    if (count($dirs) === 0) {
                        echo "  >> ERROR! ZIP file is empty \n";
                        continue;
                    }
                    $relativeDir = $relativeDir . $dirs[0] . '/';
                    $targetDir = $targetDir . '/' . $dirs[0] . '/';
                    $xmls = getFilesList($targetDir, array("xml"));
                }

                $json = readEOP($targetDir . $xmls[0]);

                if (!$json["identifier"]) {
                    echo "  >> ERROR! Metadata file is non well formed \n";
                    continue;
                }
                
                $json["metadata"] = $relativeDir . $xmls[0];
                
                // THUMBNAIL AND QUICKLOOK
                $images = getFilesList($targetDir, array("jpg", "jpeg"));
                foreach($images as $image) {

                    // Thumbnail
                    if (strpos(strtolower($image), 'icon') === 0) {
                        $json["thumbnail"] = $image;
                    }

                    // Quicklook
                    if (strpos(strtolower($image), 'preview') === 0) {
                        $json["quicklook"] = $image;
                    }
                }
                break;
            
            /*
             * KOMPSAT-2 follows OPT format
             */
            case "K2":
                
                // First find the metadata XML file
                $xmls = getFilesList($targetDir, array("xml"));

                // Special case for incorrect ZIP file (i.e. CBERS)
                if (count($xmls) === 0) {
                    $dirs = getDirectoryList($targetDir);
                    if (count($dirs) === 0) {
                        echo "  >> ERROR! ZIP file is empty \n";
                        continue;
                    }
                    $relativeDir = $relativeDir . $dirs[0] . '/';
                    $targetDir = $targetDir . '/' . $dirs[0] . '/';
                    $xmls = getFilesList($targetDir, array("xml"));
                }

                $json = readEOP($targetDir . $xmls[0]);

                if (!$json["identifier"]) {
                    echo "  >> ERROR! Metadata file is non well formed \n";
                    continue;
                }
                
                $json["metadata"] = $relativeDir . $xmls[0];
                
                // THUMBNAIL AND QUICKLOOK
                $images = getFilesList($targetDir, array("jpg", "jpeg"));
                foreach($images as $image) {

                    // Thumbnail
                    if (strpos(strtolower($image), 'icon') === 0) {
                        $json["thumbnail"] = $image;
                    }

                    // Quicklook
                    if (strpos(strtolower($image), 'preview') === 0) {
                        $json["quicklook"] = $image;
                    }
                }
                break;

            /*
             * IRS format means 1, 2 files
             *      ???.txt (metadata txt file)
             *      ???.JPEG (quicklook)
             */
            case "IRS":

                // First find the metadata text file
                $txts = getFilesList($targetDir, array("txt"));
                if (count($txts) === 0) {
                    echo "  >> ERROR! ZIP file is empty \n";
                    continue;
                }
                $json = readIRS($targetDir . $txts[0]);
                $json["metadata"] = $relativeDir . $txts[0];

                // QUICKLOOK
                $images = getFilesList($targetDir, array("jpg", "jpeg"));
                foreach($images as $image) {
                    // Quicklook
                    $json["quicklook"] = $image;
                }
                break;

            /*
             * LANDSAT format means 3 files
             *      ???.txt (metadata txt file)
             *      ICON.JPG (thumbnail)
             *      PREVIEW.JPG (quicklook)
             */
            case "LANDSAT":
                
                // METADATA
                $txts = getFilesList($targetDir, array("txt"));
                if (count($txts) === 0) {
                    echo "  >> ERROR! ZIP file is empty \n";
                    continue;
                }
                $json = readLANDSAT($targetDir . $txts[0]);
                $json["metadata"] = $relativeDir . $txts[0];

                // THUMBNAIL AND QUICKLOOK
                $images = getFilesList($targetDir, array("jpg", "jpeg"));
                foreach($images as $image) {

                    // Thumbnail
                    if (strpos(strtolower($image), 'icon') === 0) {
                        $json["thumbnail"] = $image;
                    }

                    // Quicklook
                    if (strpos(strtolower($image), 'preview') === 0) {
                        $json["quicklook"] = $image;
                    }
                }
                break;
            default:
                echo " >> ERROR! Format unknown for $zip \n";
        }

        /*
         * JSON is valid -> insert product in database
         *
         * Acquisitions table structure :
         *
         *              identifier        VARCHAR(250) PRIMARY KEY,     -- identifier
         *              parentidentifier  VARCHAR(250),                 -- parentIdentifier
         *              callid            VARCHAR(4),                   -- !! Attached disaster callid !!
         *              startdate         TIMESTAMP,                    -- beginPosition
         *              enddate           TIMESTAMP,                    -- endPosition
         *              platform          VARCHAR(250),                 -- Platform/shortName + Platform/identifier
         *              instrument        VARCHAR(250),                 -- Instrument/shortName
         *              metadata          TEXT,                         -- relative path from the CHARTERNG_ROOT_HTTP to the unzipped XML metadata file
         *!NOT USE!     archive           TEXT,                         -- relative path from the CHARTERNG_ROOT_HTTP to the source image if available
         *              quicklook         VARCHAR(250),                 -- relative path from the CHARTERNG_ROOT_HTTP to the quicklook
         *              thumbnail         VARCHAR(250),                 -- relative path from the CHARTERNG_ROOT_HTTP to the thumbnail
         *              modifieddate      TIMESTAMP
         *              creationdate      TIMESTAMP
         *              footprint         GEOMETRY (POLYGON)
         */
        if ($json) {
            
            // Set the CALLID
            $json["callid"] = $callid;

            // If quicklook is set but no thumbnail, create it with gdal
            if ($json["quicklook"] && !$json["thumbnail"]) {
                $thumb = 'th_' . $json["quicklook"];
                exec(GDAL_TRANSLATE_PATH . ' -of JPEG -outsize 25% 25% ' . $targetDir . $json["quicklook"] . ' ' . $targetDir . $thumb);
                echo "  >> create tumbnail from quicklook\n";
                $json["thumbnail"] = $thumb;
            }

            // Add relative directory to thumbnails and quicklooks
            if ($json["quicklook"]) {
                $json["quicklook"] = $relativeDir . $json["quicklook"];
            }
            if ($json["thumbnail"]) {
                $json["thumbnail"] = $relativeDir . $json["thumbnail"];
            }

            // Check if already exist
            $query = "SELECT identifier FROM acquisitions WHERE identifier=" . formatForDB($json["identifier"]);
            $result = pg_query($dbh, $query);
            $exist = 0;
            if ($result) {
                while (pg_fetch_row($result)) {
                    $exist = 1;
                }
            }
            // UPDATE
            if ($exist === 1) {

                $set = "identifier=" . formatForDB($json["identifier"]). ","
                . "parentIdentifier=" . formatForDB($json["parentIdentifier"]) . ","
                . "callid=" . formatForDB($json["callid"]) . ","
                . "startdate=" . formatForDB($json["startdate"]) . ","
                . "enddate=" . formatForDB($json["enddate"]) . ","
                . "platform=" . formatForDB($json["platform"]) . ","
                . "instrument=" . formatForDB($json["instrument"]) . ","
                . "metadata=" . formatForDB($json["metadata"]) . ","
                . "quicklook=" . formatForDB($json["quicklook"]) . ","
                . "thumbnail=" . formatForDB($json["thumbnail"]) . ","
                . "modifieddate=now(),"
                . "footprint=ST_GeomFromText('" . $json["footprint"] . "', 4326)";
                $query = "UPDATE acquisitions SET " . $set . " WHERE identifier=" . formatForDB($json["identifier"]);
                pg_query($dbh, $query);
                echo "  >> Update in database\n\n";
            }
            // INSERT
            else {
                $fields = "(identifier,parentIdentifier,callid,startdate,enddate,platform,instrument,metadata,quicklook,thumbnail,creationdate,modifieddate,footprint)";
                $values = formatForDB($json["identifier"]) . ","
                . formatForDB($json["parentIdentifier"]) . ","
                . formatForDB($json["callid"]) . ","
                . formatForDB($json["startdate"]) . ","
                . formatForDB($json["enddate"]) . ","
                . formatForDB($json["platform"]) . ","
                . formatForDB($json["instrument"]) . ","
                . formatForDB($json["metadata"]) . ","
                . formatForDB($json["quicklook"]) . ","
                . formatForDB($json["thumbnail"]) . ","
                . "now(),"
                . "now(),"
                . "ST_GeomFromText('" . $json["footprint"] . "', 4326)";
        
                $query = "INSERT INTO acquisitions " . $fields . " VALUES (" . $values . ")";
                
                pg_query($dbh, $query);
                echo "  >> Insert in database\n\n";
            }
            
        }

    }
    else {
        echo " >> ERROR! Cannot process $zip \n";
    }
}

// Close database connexion
pg_close($dbh);


?>