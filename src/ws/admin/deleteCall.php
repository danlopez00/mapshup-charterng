<?php

/*
 * Charter NG - Delete disaster within "charterng" database
 *
 *  @author   Jerome Gasperi
 *  @date     2014.04.02
 *
 */

require_once realpath(dirname(__FILE__)) . '/../config.php';

/*
 * Initialize empty response
 */
$out = array();

/**
 * This script returns JSON
 */
header("Pragma: no-cache");
header("Expires: 0");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Content-type: application/json; charset=utf-8");

/*
 * No input callid
 */
if (!isset($_GET['callid'])) {
    $out['status'] = 'KO';
    $out['message'] = 'Invalid input disaster file';
}
/*
 * Delete callid from database
 */
else {

    // Database connection to Charter NG database
    $dbh = pg_connect("host=" . CHARTERNG_DB_HOST . " dbname=" . CHARTERNG_DB_NAME . " user=" . CHARTERNG_DB_USER . " password=" . CHARTERNG_DB_PASSWORD) or die(pg_last_error());
    pg_query($dbh, "DELETE FROM kmls WHERE callid='" . pg_escape_string($_GET['callid']) . "'");
    pg_query($dbh, "DELETE FROM disasters WHERE callid='" . pg_escape_string($_GET['callid']) . "'");
    
    $out['status'] = 'OK';
    $out['message'] = 'Delete callid ' . $_GET['callid'];
}

echo json_encode($out);