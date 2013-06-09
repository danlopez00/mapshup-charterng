<?php
/*
 * Search service for Charter Disasters
 *
 * Author:  Jérôme Gasperi @ CNES
 * Date:    2012.12.03
 * 
 * Note :
 *  this search is not localized - i.e. setting bbox do nothing
 */


/*
 * Return color of the disaster 
 *
 * @param String Disaster type
 */
function getColor($type) {

    switch ($type) {
        case "LANDSLIDE":
            $color = '#800000';
            break;
        case "EARTHQUAKE":
            $color = '#DAA520';
            break;
        case "OIL_SPILL":
            $color = '#2F4F4F';
            break;
        case "FLOOD":
            $color = '#1E90FF';
            break;
        case "VOLCANIC_ERUPTION":
            $color = '#FF4500';
            break;
        case "CYCLONE":
            $color = '#B0E0E6';
            break;
        case "FIRE":
            $color = '#FF0000';
            break;
        default:
            $color = '#9ACD32';
    }

    return $color;
}

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
$error = '"error":{"message":"Error : cannot connect to activations catalog"}';
$dbh = pg_connect("host=".CHARTERNG_DB_HOST." dbname=".CHARTERNG_DB_NAME." user=".CHARTERNG_DB_USER." password=".CHARTERNG_DB_PASSWORD) or die(pg_last_error());
$where = " WHERE location IS NOT NULL";

// Type is not set by default
$typeIsSet = false;

/**
 * Optional searchterms
 */
if (isset($_REQUEST['q']) && $_REQUEST['q'] != "") {
    $keywords = explode(" ", $_REQUEST['q']);
    $or = array();

    foreach($keywords as $keyword) {

        // CALLID - if keyword is and integer then search on callid = 'xxx'
        if (is_numeric($keyword)) {
            array_push($or, "callid='" . $keyword . "'");
        }
        else {
            $arr = explode("type=", $keyword);
            if (count($arr) === 2) {
                if ($arr[1] === "ALL") {
                    array_push($or, "type LIKE '%'");
                }
                else {
                    array_push($or, "type='" . $arr[1] . "'");
                }
                $typeIsSet = true;
            }
            else {
                $v = "LIKE '%" . strtolower(pg_escape_string($keyword)) . "%'";
                array_push($or, "(lower(description) " . $v . " OR lower(title) " . $v. " OR lower(type) " . $v . ")");
            }
        }
    }


    $where .= " AND " . implode(" AND ", $or);
}

if (!$typeIsSet) {
    /**
     * Optional callid
     */
    if (isset($_REQUEST['callid']) && $_REQUEST['callid'] != "") {
        $where .= " AND callid='" . pg_escape_string($_REQUEST['callid']) . "'";
    }
}

/**
 * Optional activation date
 */
if (isset($_REQUEST["startDate"]) && isISO8601($_REQUEST["startDate"])) {
    $where .= " AND disasterdate >= '" . pg_escape_string($_REQUEST['startDate']) . "'";
}
if (isset($_REQUEST["completionDate"])  && isISO8601($_REQUEST["completionDate"])) {
    $where .= " AND disasterdate <= '" . pg_escape_string($_REQUEST['completionDate']) . "'";
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
 *      callid            VARCHAR(4) PRIMARY KEY,
 *      disasterdate      TIMESTAMP,
 *      title             TEXT,
 *      type              VARCHAR(50),
 *      description       TEXT,
 *      link              VARCHAR(250),
 *      image             VARCHAR(250),
 *      modifieddate      TIMESTAMP
 *      location          GEOMETRY (POINT)
 */
$query = "SELECT callid, disasterdate, title, type, description, link, modifieddate, ST_AsGeoJSON(location) AS geojson FROM disasters" . $where . " ORDER BY callid DESC";
$results = pg_query($dbh, $query) or die(pg_last_error());

/*
 * Initialize GeoJSON empty FeatureCollection
 */
$geojson = array(
    'type' => 'FeatureCollection',
    'totalResults' => 0,
    'processingTime' => 0,
    'features' => array()
);

/*
 * Retrieve each products
 */
$count = 0;
while ($product = pg_fetch_assoc($results)) {

    $feature = array(
        'type' => 'Feature',
        'geometry' => json_decode($product['geojson'], true),
        'properties' => array(
            'callid' => $product['callid'],
            'title' => $product['title'],
            'type' => $product['type'],
            'description' => $product['description'],
            'link' => $product['link'],
            'disasterdate' => str_replace(" ", "T", $product['disasterdate']),
            'modified' => str_replace(" ", "T", $product['modifieddate']),
            'color' => getColor($product['type'])
        )
    );

    // Add feature array to feature collection array
    array_push($geojson['features'], $feature);

    $count++;
}

/*
 * Update number of results
 */
$t3 = microtime_float();
$geojson['totalResults'] = $count;
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