#!/usr/bin/php
<?php
/*
 * Charter NG - Insert acquisition metadata
 *
 *  @author   Jerome Gasperi
 *  @date     2013.06.09
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

// Set Timezone
date_default_timezone_set("Europe/Paris");

// This application can only be called from a shell (not from a webserver)
if (empty($_SERVER['SHELL'])) {
    exit;
}

// Get format and callid
if (!$_SERVER['argv'][4]) {
    echo "\n    Usage : " . $_SERVER['argv'][0] . " [CHARTERNG_MANAGE_DIR] [ZIP FILE] [FORMAT] [EMAIL]\n";
    echo "      Ingest [ZIP FILE] with [FORMAT]. [ZIP FILE] is copied within CHARTERNG_ARCHIVES directory \n\n";
    echo "         Note : Format can be set to AUTO if zip file name follow the [CALLID]_[FORMAT]_XXXX.zip convention\n";
    echo "         Possible formats are : (AUTO), OPT, SAR, DIMAP, PHR, F2, K2, RS1, RS2, SACC, IRS, LANDSAT\n\n";
    echo "         If EMAIL value is 'none' then no mail are sent, otherwise a mail indicated if the ingestion is OK or KO is sent to EMAIL\n\n";
    exit;
}

// Configuration files
include_once $_SERVER['argv'][1] . '/config.php';
include_once $_SERVER['argv'][1] . '/lib/functions.php';
include_once $_SERVER['argv'][1] . '/lib/formatsReader.php';

$zip = $_SERVER['argv'][2];
$format = $_SERVER['argv'][3];
$email = $_SERVER['argv'][4];
$error = "none";

// Database connection to Charter NG database
$dbh = pg_connect("host=" . CHARTERNG_DB_HOST . " dbname=" . CHARTERNG_DB_NAME . " user=" . CHARTERNG_DB_USER . " password=" . CHARTERNG_DB_PASSWORD) or die(pg_last_error());

// The disaster CallId and the metadata format are inferred from zip file name
$infos = explode("_", basename($zip));
$callid = $infos[0];
if ($format === "AUTO") {
    $format = $infos[1];
}

// Zip is uncompressed within the CHARTERNG_METADATA_PATH directory
$relativeDir = basename(substr($zip, 0, -4)) . "/";
$targetDir = CHARTERNG_METADATA_DIR . $relativeDir;

echo "Processing $zip\n";
// If the file is successfully unzipped - process the metadata
if (unzip($zip, $targetDir, true, true)) {

    echo "  >> unzip to $targetDir\n";

    $json = null;
    
    if ($format === 'RSAT1') {
        $format = 'RS1';
    }

    if ($format === 'RSAT2') {
        $format = 'RS2';
    }
            
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
            foreach ($images as $image) {

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
            foreach ($images as $image) {

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
            foreach ($images as $image) {

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
            foreach ($images as $image) {
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
            foreach ($images as $image) {
                // Quicklook
                $json["quicklook"] = $image;
            }
            if (!$json["quicklook"]) {
                $images = getFilesList($targetDir, array("tif"));
                foreach ($images as $image) {

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
            foreach ($images as $image) {
                // Quicklook
                $json["quicklook"] = $image;
            }
            if (!$json["quicklook"]) {
                $images = getFilesList($targetDir, array("tif"));
                foreach ($images as $image) {

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
            foreach ($images as $image) {

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
            foreach ($images as $image) {

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
            foreach ($images as $image) {

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
            foreach ($images as $image) {
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
            foreach ($images as $image) {

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
     * !NOT USE!     archive           TEXT,                         -- relative path from the CHARTERNG_ROOT_HTTP to the source image if available
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

            $set = "identifier=" . formatForDB($json["identifier"]) . ","
                    . "parentIdentifier=" . formatForDB($json["parentIdentifier"]) . ","
                    . "callid=" . formatForDB($json["callid"]) . ","
                    . "startdate=" . formatForDB($json["startdate"]) . ","
                    . "enddate=" . formatForDB($json["enddate"]) . ","
                    . "platform=" . formatForDB(normalizeName($json["platform"])) . ","
                    . "instrument=" . formatForDB(normalizeName($json["instrument"])) . ","
                    . "metadata=" . formatForDB($json["metadata"]) . ","
                    . "quicklook=" . formatForDB($json["quicklook"]) . ","
                    . "thumbnail=" . formatForDB($json["thumbnail"]) . ","
                    . "modifieddate=now(),"
                    . "footprint=ST_GeomFromText('" . $json["footprint"] . "', 4326)";
            $query = "UPDATE acquisitions SET " . $set . " WHERE identifier=" . formatForDB($json["identifier"]) . " RETURNING identifier";
            try {
                $t = pg_query($dbh, $query);
                if (!$t) {
                    throw new Exception('ERROR UPDATING', 405);
                }
                echo "  >> Update in database\n";
            } catch (Exception $e) {
                echo "  >> Error in update\n";
                $error = "yes";
            }
        }
        // INSERT
        else {
            $fields = "(identifier,parentIdentifier,callid,startdate,enddate,platform,instrument,metadata,quicklook,thumbnail,creationdate,modifieddate,footprint)";
            $values = formatForDB($json["identifier"]) . ","
                    . formatForDB($json["parentIdentifier"]) . ","
                    . formatForDB($json["callid"]) . ","
                    . formatForDB($json["startdate"]) . ","
                    . formatForDB($json["enddate"]) . ","
                    . formatForDB(normalizeName($json["platform"])) . ","
                    . formatForDB(normalizeName($json["instrument"])) . ","
                    . formatForDB($json["metadata"]) . ","
                    . formatForDB($json["quicklook"]) . ","
                    . formatForDB($json["thumbnail"]) . ","
                    . "now(),"
                    . "now(),"
                    . "ST_GeomFromText('" . $json["footprint"] . "', 4326)";

            $query = "INSERT INTO acquisitions " . $fields . " VALUES (" . $values . ") RETURNING identifier";
            
            try {
                $t = pg_query($dbh, $query);
                if (!$t) {
                    throw new Exception('ERROR INSERTING', 405);
                }
                echo "  >> Insert in database\n\n";
            } catch (Exception $e) {
                echo "  >> Error in insert;";
                $error = "yes";
            }
            
        }

        $archived = basename($zip); 
        if ($format !== "AUTO") {
            $archived = $callid . "_" . $format . substr($archived, 3);
        }
        if (copy($zip, CHARTERNG_ARCHIVES_DIR . $archived)) {
            echo "  >> Copy zip file to " . CHARTERNG_ARCHIVES_DIR . " directory\n";
        } else {
            echo "  >> WARNING ! zip file was not copied into " . CHARTERNG_ARCHIVES_DIR . " directory\n";
        }
    }
} else {
    echo " >> ERROR! Cannot process $zip \n";
    $error = "yes";
}

// Close database connexion
pg_close($dbh);

if ($email !== "none") {
    
    $subject = "[mapshup] Requested password for user " . $email;
    
    $headers = "From: root@disasterschartercatalog.org\r\n" .
            "Reply-To:  root@disasterschartercatalog.org\r\n" .
            "X-Mailer: PHP/" . phpversion();
    if ($error === "yes") {
        $subject = "[Charter][ERROR] Upload of " . $zip;
        $message = "KO";
    }
    else {
        $subject = "[Charter][SUCCESS] Upload of " . $zip;
        $message = "OK";
    }
    
    // Envoi du mail
    mail($email, $subject, $message, $headers);
    
    echo " >> Send email to $email \n";
}
echo "Done !\n";
?>