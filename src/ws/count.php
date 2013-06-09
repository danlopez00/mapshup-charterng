<?php
/*
 * Count acquisitions for Charter Disasters
 *
 * Author:  Jérôme Gasperi @ CNES
 * Date:    201.04.11
 */

// Include configuration file
include_once 'config.php';

/**
 * This script returns text
 */
header("Pragma: no-cache");
header("Expires: 0");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Content-type: application/text; charset=utf-8");

/**
 * Database connection
 */
$error = 'Error : cannot connect to Charter catalog';
$dbh = pg_connect("host=".CHARTERNG_DB_HOST." dbname=".CHARTERNG_DB_NAME." user=".CHARTERNG_DB_USER." password=".CHARTERNG_DB_PASSWORD) or die(pg_last_error());

if (isset($_REQUEST['callid']) && $_REQUEST['callid'] != "") {
    $where .= " WHERE callid='" . pg_escape_string($_REQUEST['callid']) . "'";
}

$query = "select distinct count(*) as c, platform from acquisitions group by platform" . $where . " order by platform;";
$results = pg_query($dbh, $query) or die(pg_last_error());

/*
 * Retrieve each products
 */
$output = '';
while ($product = pg_fetch_assoc($results)) {
    $output .= $product['platform'] . " : " . $product['c'] . "\n";
}

pg_close($dbh);

/*
 * Return GeoJSON result
 */
echo $output;

?>