<?php
/*
 * Search service for Charter Acquisitions
 *
 * Author:  Jérôme Gasperi @ CNES
 * Date:    2012.12.03
 * 
 */
function microtime_float() {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
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
 * Correct a GeoJSON geometry across the DateLine and correct
 * from OpenLayers display problem by adding 360 degrees to negative values
 *
 * GeoJSON Geometry example 
 *      {
 *          "type":"Polygon",
 *          "coordinates":[[
 *              [176.18702,-37.20235],
 *              [176.54697,-37.25755],
 *              [176.41575,-37.78637],
 *              [176.05561,-37.73139],
 *              [176.18702,-37.20235]
 *          ]]
 *      }
 *
 * @param <GeoJSON> $geometry
 *
 * @return GeoJSON Geometry corrected from Date Line problem
 */
function correctDateLine($geometry) {

    /* Point */
    if ($geometry['type'] === 'Point') {
        return $geometry;
    }

    /* Line */
    if ($geometry['type'] === 'Line') {
        $coords = $geometry["coordinates"];
    }

    /* Polygon */
    else if ($geometry['type'] === 'Polygon') {
        $coords = $geometry["coordinates"][0];
    }

    /* Process coordinates pairs */
    $newCoords = array();
    $previousLongitude = $coords[0][0];
    for ($i = 1; $i < count($coords); $i++) {
        $currentLongitude = $coords[$i - 1][0];
        if (($previousLongitude - $currentLongitude) <= -180) {
            array_push($newCoords,array($currentLongitude - 360, $coords[$i - 1][1]));
            $previousLongitude = $currentLongitude - 360;
        }
        else if (($previousLongitude - $currentLongitude) >= 180) {
            array_push($newCoords, array($currentLongitude + 360, $coords[$i - 1][1]));
            $previousLongitude = $currentLongitude + 360;
        }
        else {
            array_push($newCoords, array($coords[$i - 1][0], $coords[$i - 1][1]));
            $previousLongitude = $coords[$i - 1][0];
        }
    }

    if ($geometry['type'] === 'Line') {
        return array(
            'type' => $geometry['type'],
            'coordinates' => $newCoords
        );
    }

    if ($geometry['type'] === 'Polygon') {
        return array(
            'type' => $geometry['type'],
            'coordinates' => array($newCoords)
        );
    }

}

// Include configuration file
include_once 'config.php';

/*
 * Execution time measurement
 */
$t0 = microtime_float();

/**
 * This script returns JSON
 */
header("Pragma: no-cache");
header("Expires: 0");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Content-type: application/json; charset=utf-8");

/**
 * Database connection
 */
$error = '"error":{"message":"Error : cannot connect to acquisitions catalog"}';
$dbh = pg_connect("host=".CHARTERNG_DB_HOST." dbname=".CHARTERNG_DB_NAME." user=".CHARTERNG_DB_USER." password=".CHARTERNG_DB_PASSWORD) or die(pg_last_error());
$where = " WHERE footprint IS NOT NULL";

// Set cursor. Default is 1
$cursor = isset($_REQUEST["nextRecord"]) ? $_REQUEST["nextRecord"] : 1;
if (!is_numeric($cursor)) {
    $cursor = 1;
}

// In postgresql offset starts at 0
$cursor = $cursor - 1;

// Set maxRecords. Default is RESULTS_PER_PAGE
$maxResults = isset($_REQUEST["count"]) ? $_REQUEST["count"] : RESULTS_PER_PAGE;
if (!is_numeric($maxResults)) {
    $maxResults = RESULTS_PER_PAGE;
}

/**
 * Optional input bbox
 */
if (isset($_REQUEST['bbox']) && $_REQUEST['bbox'] != "") {
    $where .= " AND ST_intersects(footprint, ST_GeomFromText('" . pg_escape_string(bboxToWKTExtent($_REQUEST['bbox'])) . "', 4326))";
}

/**
 * Optional callid
 */
if (isset($_REQUEST['callid']) && $_REQUEST['callid'] != "") {
    $where .= " AND callid='" . pg_escape_string($_REQUEST['callid']) . "'";
}
/**
 * Optional acquisition date
 */
if (isset($_REQUEST["startDate"]) && isISO8601($_REQUEST["startDate"])) {
    $where .= " AND startdate >= '" . pg_escape_string($_REQUEST['startDate']) . "'";
}
if (isset($_REQUEST["completionDate"])  && isISO8601($_REQUEST["completionDate"])) {
    $where .= " AND enddate <= '" . pg_escape_string($_REQUEST['completionDate']) . "'";
}

/**
 * Modification date (for harvesting)
 */
if (isset($_REQUEST["modified"]) && isISO8601($_REQUEST["modified"])) {
    $where .= " AND modifieddate >= '" . pg_escape_string($_REQUEST['modified']) . "'";
}

/*
 * Launch search query
 * 
 *   identifier        VARCHAR(250) PRIMARY KEY,     -- identifier
 *   parentidentifier  VARCHAR(250),                 -- parentIdentifier
 *   callid            VARCHAR(4),                   -- !! Attached disaster callid !!
 *   startdate         TIMESTAMP,                    -- beginPosition
 *   enddate           TIMESTAMP,                    -- endPosition
 *   platform          VARCHAR(250),                 -- Platform/shortName + Platform/identifier
 *   instrument        VARCHAR(250),                 -- Instrument/shortName
 *   metadata          TEXT,                         -- relative path from the CHARTERNG_ROOT_HTTP to the unzipped XML metadata file
 *   archive           TEXT,                         -- relative path from the CHARTERNG_ROOT_HTTP to the source image if available
 *   quicklook         VARCHAR(250),                 -- relative path from the CHARTERNG_ROOT_HTTP to the quicklook
 *   thumbnail         VARCHAR(250),                 -- relative path from the CHARTERNG_ROOT_HTTP to the thumbnail
 *   modifieddate      TIMESTAMP
 */

// First get the total number of results
$query = "SELECT count(*) as count FROM acquisitions" . $where;
$results = pg_query($dbh, $query) or die(pg_last_error());
$totalResults = 0;
while ($product = pg_fetch_assoc($results)) {
    $totalResults = $product['count'];
}

$query = "SELECT identifier, callid, startdate, enddate, platform, instrument, metadata, quicklook, thumbnail, modifieddate, ST_AsGeoJSON(footprint) AS geojson FROM acquisitions" . $where . " ORDER BY callid, startdate LIMIT " . $maxResults . " OFFSET " . $cursor;
$results = pg_query($dbh, $query) or die(pg_last_error());

/*
 * Initialize GeoJSON empty FeatureCollection
 */
$geojson = array(
    'type' => 'FeatureCollection',
    'totalResults' => $totalResults,
    'processingTime' => 0,
    'features' => array()
);

/*
 * Retrieve each products
 */
$count = 0;
while ($product = pg_fetch_assoc($results)) {

    /*
     * Avoid -180/180 problem
     */
    $geometry = json_decode($product['geojson'], true);
    $corrected = correctDateLine($geometry);

    $feature = array(
        'type' => 'Feature',
        'geometry' => $corrected,
        'properties' => array(
            'identifier' => $product['identifier'],
            'callid' => $product['callid'],
            'platform' => $product['platform'],
            'instrument' => $product['instrument'],
            'startDate' => str_replace(" ", "T", $product['startdate']),
            'completionDate' => str_replace(" ", "T", $product['enddate']),
            'modified' => str_replace(" ", "T", $product['modifieddate']),/*
            'services' => array(
                'download' => array(
                    'url' => CHARTERNG_METADATA_URL . $product['metadata'],
                    'mimeType' => 'application/xml'
                )
            ),*/
            'quicklook' => $product['quicklook'] ? (!filter_var($product['quicklook'], FILTER_VALIDATE_URL) ? CHARTERNG_QUICKLOOK_URL : '' ) . $product['quicklook'] : "",
            'thumbnail' => $product['thumbnail'] ? (!filter_var($product['thumbnail'], FILTER_VALIDATE_URL) ? CHARTERNG_QUICKLOOK_URL : '' ) . $product['thumbnail'] : CHARTERNG_QUICKLOOK_URL . "no_thumbnail.jpg",
            'metadata' =>  $product['metadata'] ? (!filter_var($product['metadata'], FILTER_VALIDATE_URL) ? CHARTERNG_QUICKLOOK_URL : '' ) . $product['metadata']: ""
        )
    );

    // Add feature array to feature collection array
    array_push($geojson['features'], $feature);
}

/*
 * Update number of results
 */
$t3 = microtime_float();
$geojson['processingTime'] = $t3 - $t0;

/*
 * Close database connection
 */
pg_close($dbh);

/*
 * Return GeoJSON result
 */
echo json_encode($geojson);

?>