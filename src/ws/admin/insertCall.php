<?php

/*
 * Charter NG - Insert disaster within "charterng" database
 *
 *  @author   Jerome Gasperi
 *  @date     2014.04.02
 *
 */

/*
 *
 * XML activations feed are retrieved here 
 * 
 *  http://www.disasterscharter.org/DisasterCharter/CnesXml?articleType=activation&locale=en_US&companyId=1&communityId=10729 
 * 
 * The structure is the following :
 * 
 *          <dch:disaster>
 *              <dch:title>Earthquake Guatemala</dch:title>
 *              <dch:date>2012-11-08T23:00:00+0000</dch:date>
 *              <dch:call-id>420</dch:call-id>
 *              <dch:type>EARTHQUAKE</dch:type>
 *              <dch:description>....</dch:description>
 *              <dch:link>http://www.disasterscharter.org/web/charter/activation_details?p_r_p_1415474252_assetId=ACT-420</dch:link>
 *              <dch:image>http://www.disasterscharter.org/image/journal/article?img_id=136977</dch:image>
 *              <dch:location>
 *                  <gml:Point gml:id="p420" srsName="urn:ogc:def:crs:EPSG:6.6:4326">
 *                      <gml:pos dimension="2">15.6 -91.54</gml:pos>
 *                  </gml:Point>
 *              </dch:location>
 *          </dch:disaster>
 * 
 * XML feeds are stored within the "disasters" table of the "charterng" database
 * 
 *      CREATE TABLE disasters (
 *          callid            VARCHAR(4) PRIMARY KEY,
 *          disasterdate      TIMESTAMP,
 *          title             TEXT,
 *          type              VARCHAR(50),
 *          description       TEXT,
 *          link              VARCHAR(250),
 *          image             VARCHAR(250),
 *          modifieddate      TIMESTAMP,
 *          location          GEOMETRY (POINT),
 *          footprint         GEOMETRY (POLYGON)
 *      );
 * 
 */

/* ===================== FUNCTIONS ========================= */

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

/*
 * Insert disaster within database
 */

function insertDisaster($xmlFile) {

    $disaster = new DOMDocument();
    $disaster->load($xmlFile);

    // Database connection to Charter NG database
    $dbh = pg_connect("host=" . CHARTERNG_DB_HOST . " dbname=" . CHARTERNG_DB_NAME . " user=" . CHARTERNG_DB_USER . " password=" . CHARTERNG_DB_PASSWORD) or die(pg_last_error());

    /*
     * Process all disasters
     *    
     * Add an entry for each disaster within the "disasters" table
     *
     * Table structure :
     *
     *          callid            VARCHAR(4) PRIMARY KEY,
     *          disasterdate      TIMESTAMP,
     *          title             TEXT,
     *          type              VARCHAR(50),
     *          description       TEXT,
     *          link              VARCHAR(250),
     *          image             VARCHAR(250),
     *          modifieddate      TIMESTAMP
     *          location          GEOMETRY (POINT)
     *
     */
    $fields = "(callid,type,disasterdate,title,description,link,image,modifieddate,location,footprint)";

    /*
     * Generic metadata
     */
    $title = $disaster->getElementsByTagname('title')->item(0)->nodeValue;
    $callid = $disaster->getElementsByTagname('callid')->item(0)->nodeValue;

    /*
     * Point location
     */
    $lonlat = explode(" ", $disaster->getElementsByTagname('pos')->item(0)->nodeValue);

    /*
     * Footprint
     */
    $bbox = null;
    $posList = $disaster->getElementsByTagname('posList')->item(0);
    if ($posList->length !== 0) {
        $bbox = str_replace(' ', ',', trim($posList->nodeValue));
    }

    /*
     * Disaster type with automatic correction of incorrect disasters
     * 
     * Possible values
     *  EARTHQUAKE
     *  FLOOD
     *  FIRE
     *  ICE
     *  LANDSLIDE
     *  OCEAN_STORM (CYCLONE, HURRICANE, TYPHOON)
     *  OIL_SPILL
     *  OCEAN_WAVE (TSUNAMI)
     *  VOLCANIC_ERUPTION
     *  OTHER (INDUSTRIAL_ACCIDENT, WIND_STORM, TORNADO...)
     * 
     */
    $type = $disaster->getElementsByTagname('type')->item(0)->nodeValue;
    if ($type == "OTHER") {

        /*
         * Check for disaster type within title
         * Hypothesis is that disaster type is the first word within the title
         */
        $words = explode(' ', $title);

        foreach ($words as $word) {

            $word = strtolower(str_replace(",", "", trim($word)));

            if (in_array($word, array('flood', 'flooding', 'floods'))) {
                $type = 'FLOOD';
                break;
            } else if (in_array($word, array('ocean', 'tsunami', 'huricane', 'hurricane-force'))) {
                $type = 'CYCLONE';
                break;
            } else if (in_array($word, array('landslide', 'landslides'))) {
                $type = 'LANDSLIDE';
                break;
            } else if (in_array($word, array('earthquake'))) {
                $type = 'EARTHQUAKE';
                break;
            }
        }
    }

    /*
     * Remove (eventual) existing disaster from database
     */
    pg_query($dbh, "DELETE FROM kmls WHERE callid='" . $callid . "'");
    pg_query($dbh, "DELETE FROM disasters WHERE callid='" . $callid . "'");

    /*
     * Insert disaster
     */
    $values = "'"
            . pg_escape_string($callid) . "','"
            . pg_escape_string($type) . "','"
            . pg_escape_string($disaster->getElementsByTagname('date')->item(0)->nodeValue) . "','"
            . pg_escape_string($title) . "','"
            . pg_escape_string($disaster->getElementsByTagname('description')->item(0)->nodeValue) . "','"
            . pg_escape_string($disaster->getElementsByTagname('link')->item(0)->nodeValue) . "','"
            . pg_escape_string($disaster->getElementsByTagname('image')->item(0)->nodeValue) . "',"
            . "now(),"
            . "ST_GeomFromText('POINT(" . floatval($lonlat[1]) . " " . floatval($lonlat[0]) . ")', 4326),"
            . ($bbox ? "ST_GeomFromText('" . bboxToWKTExtent($bbox) . "', 4326)" : 'NULL');

    try {
        $query = pg_query($dbh, "INSERT INTO disasters " . $fields . " VALUES (" . $values . ")");
        if (!$query) {
            pg_close($dbh);
            throw new Exception();
        }

        /*
         * Insert kmlurls
         */
        $kmls = $disaster->getElementsByTagname('kmlUrl');
        if ($kmls->length > 0) {
            foreach ($kmls as $kml) {
                $query = "INSERT INTO kmls (callid, kmlurl) VALUES ('" . $callid . "','" . $kml->nodeValue . "')";
                pg_query($dbh, $query);
            }
        }
        
    } catch (Exception $e) {
        return false;
    }

    // Close database connexion
    pg_close($dbh);

    return true;
}

/* ===================== END FUNCTIONS ========================== */

require_once realpath(dirname(__FILE__)) . '/../config.php';

/*
 *  Set Timezone
 */
date_default_timezone_set("Europe/Paris");

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
 * No input file
 */
if (count($_FILES) == 0 || !is_array($_FILES['file'])) {
    $out['status'] = 'KO';
    $out['message'] = 'Invalid input disaster file';
}
/*
 * Input file is set - insert into database
 */ else {

    $ok = 0;
    $error = 0;

    /*
     * Read file
     */
    $tmpFiles = $_FILES['file']['tmp_name'];
    if (!is_array($tmpFiles)) {
        $tmpFiles = array($tmpFiles);
    }
    for ($i = 0, $l = count($tmpFiles); $i < $l; $i++) {

        if (insertDisaster($tmpFiles[$i])) {
            $ok++;
        } else {
            $error++;
        }
    }

    if ($error > 0) {
        $out['status'] = 'KO';
        $out['message'] = $ok . ' disaster(s) inserted and ' . $error . ' disasters in error';
    } else {
        $out['status'] = 'OK';
        $out['message'] = $ok . ' disaster(s) inserted';
    }
}

echo json_encode($out);