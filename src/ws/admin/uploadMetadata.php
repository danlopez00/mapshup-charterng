<?php
/*
 * Metadata upload service for Charter Acquisitions
 *
 * Author:  Jérôme Gasperi @ CNES
 * Date:    2013.04.11
 * 
 */

/**
 * Safe insert into SQL
 */
function formatForDB($str) {
  if ($str) {
    return "'" . pg_escape_string($str) . "'";
  }
  return "NULL";
}

/**
 * Return true if input date string is ISO 8601 formatted
 * i.e. in the form YYYY-MM-DDTHH:MM:SS
 */
function isISO8601($dateStr) {
    return preg_match( '/\d{4}-\d{2}-\d{2}T\d{2}\:\d{2}\:\d{2}/i', $dateStr );
}

/**
 * Return POLYGON WKT from bbox
 * @param <string> $bbox "lonmin,latmin,lonmax,latmax"
 */
function bboxToWKTExtent($bbox) {
    $coords = preg_split('/,/', $bbox);
    $lonmin = $coords[0];
    $latmin = $coords[1];
    $lonmax = $coords[2];
    $latmax = $coords[3];
    return "POLYGON((" . $lonmin . " " . $latmin . "," . $lonmin . " " . $latmax . "," . $lonmax . " " . $latmax . "," . $lonmax . " " . $latmin . "," . $lonmin . " " . $latmin . "))";
}

/**
 * Return an array of posted/put files or POST stream within HTTP request Body
 *
 * @param array $params - query parameters
 *
 * @return array
 * @throws Exception
 */
function readInputData() {
    $output = null;
    /*
     * True by default, False if no file is posted but data posted through parameters
     */
    $isFile = true;
    /*
     * No file is posted
     */
    if (count($_FILES) === 0 || !is_array($_FILES['file'])) {
        /*
         * Is data posted within HTTP request body ?
         */
        $body = file_get_contents('php://input');
        if (isset($body)) {
            $isFile = false;
            $tmpFiles = array($body);
        }
        /*
         * Nothing posted
         */
        else {
            return $output;
        }
    }
    /*
     * A file is posted
     */
    else {
        /*
         * Read file assuming this is ascii file (i.e. plain text, GeoJSON, etc.)
         */
        $tmpFiles = $_FILES['file']['tmp_name'];
        if (!is_array($tmpFiles)) {
            $tmpFiles = array($tmpFiles);
        }
    }
    if (count($tmpFiles) > 1) {
        throw new Exception('Only one file can be posted at a time', 500);
    }
    /*
     * Assume that input data format is JSON by default
     */
    try {
        /*
         * Decode json data
         */
        if ($isFile) {
            $output = json_decode(join('', file($tmpFiles[0])), true);
        }
        else {
            $output = json_decode($tmpFiles[0], true);
        }
    } catch (Exception $e) {
        throw new Exception('Invalid posted file(s)', 500);
    }

    /*
     * The data's format is not JSON
     */
    if ($output === null) {

        /*
         * Push the file content in return array.
         * The file content is transformed as array by file function
         */
        if ($isFile) {
            $output = file($tmpFiles[0]);
        }
        /*
         * By default, the exploding character is "\n"
         */
        else {
            $output = explode("\n", $tmpFiles[0]);
        }
    }
    return $output;
}

/*
 * Include configuration file
 */
require_once realpath(dirname(__FILE__)) . '/../config.php';

/**
 * This script returns JSON
 */
header("Pragma: no-cache");
header("Expires: 0");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Content-type: application/json; charset=utf-8");

/*
 * Read POST data
 */
$data = readInputData();
if (is_array($data) && count($data) > 0) {
    $json = $data["metadata"];
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
 *              archive           TEXT,                         -- relative path from the CHARTERNG_ROOT_HTTP to the source image if available
 *              quicklook         VARCHAR(250),                 -- relative path from the CHARTERNG_ROOT_HTTP to the quicklook
 *              thumbnail         VARCHAR(250),                 -- relative path from the CHARTERNG_ROOT_HTTP to the thumbnail
 *              modifieddate      TIMESTAMP
 *              creationdate      TIMESTAMP
 *              footprint         GEOMETRY (POLYGON)
 */
if ($json && $json !== "") {
    
    /**
     * Database connection
     */
    $error = '{"success":"false","message":"Error : cannot connect to acquisitions catalog"}';
    $dbh = pg_connect("host=".CHARTERNG_DB_HOST." dbname=".CHARTERNG_DB_NAME." user=".CHARTERNG_DB_USER." password=".CHARTERNG_DB_PASSWORD) or die($error);
    
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
        . "parentidentifier=" . formatForDB($json["identifier"]) . ","
        . "callid=" . formatForDB($json["callId"]) . ","
        . "startdate=" . formatForDB($json["startDate"]) . ","
        . "enddate=" . formatForDB($json["completionDate"]) . ","
        . "platform=" . formatForDB($json["platform"]) . ","
        . "instrument=" . formatForDB($json["instrument"]) . ","
        . "quicklook=" . formatForDB($json["quicklookUrl"]) . ","
        . "thumbnail=" . formatForDB($json["thumbnailUrl"]) . ","
        . "metadata=" . formatForDB($json["originalMetadataUrl"]) . ","
        . "archive=" . formatForDB($json["productUrl"]) . ","
        . "modifieddate=now(),"
        . "footprint=ST_GeomFromText('" . $json["wkt"] . "', 4326)";
        $query = "UPDATE acquisitions SET " . $set . " WHERE identifier=" . formatForDB($json["identifier"]);
        pg_query($dbh, $query) or die('{"success":"false", "message":"Update failed"}');
    }
    // INSERT
    else {
        $fields = "(identifier,parentidentifier,callid,startdate,enddate,platform,instrument,quicklook,thumbnail,metadata,archive,creationdate,modifieddate,footprint)";
        $values = formatForDB($json["identifier"]) . ","
        . formatForDB($json["identifier"]) . ","
        . formatForDB($json["callId"]) . ","
        . formatForDB($json["startDate"]) . ","
        . formatForDB($json["completionDate"]) . ","
        . formatForDB($json["platform"]) . ","
        . formatForDB($json["instrument"]) . ","
        . formatForDB($json["quicklookUrl"]) . ","
        . formatForDB($json["thumbnailUrl"]) . ","
        . formatForDB($json["originalMetadataUrl"]) . ","
        . formatForDB($json["productUrl"]) . ","
        . "now(),"
        . "now(),"
        . "ST_GeomFromText('" . $json["wkt"] . "', 4326)";

        $query = "INSERT INTO acquisitions " . $fields . " VALUES (" . $values . ")";
        
        pg_query($dbh, $query) or die('{"success":"false", "message":"Insert failed"}');
    }

    /*
     * Close database connection
     */
    pg_close($dbh);

    echo '{"success":"true"}';
}
else {
    echo '{"success":"false", "message":"Not valid metadata format"}'; 
}
