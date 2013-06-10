#!/usr/bin/php
<?php
/*
 * Charter NG - Insert disasters within "charterng" database
 *
 *  @author   Jerome Gasperi
 *  @date     2012.12.02
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
 *      <dch:disasters dch:updated="2012-11-08T23:00:00+0000">
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
 *          [...]
 *      </dch:disasters>
 * 
 * 
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
 *          modifieddate      TIMESTAMP
 *          location          GEOMETRY (POINT)
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

/**
 * Set the proxy if needed
 * @param <type> $url Input url to proxify
 *
 * Code from mapshup (http://mapshup.info)
 */
function initCurl($url) {

    /**
     * Init curl
     */
    $curl = curl_init();

    if (USE_PROXY) {
        curl_setopt($curl, CURLOPT_PROXY, PROXY_URL);
        curl_setopt($curl, CURLOPT_PROXYPORT, PROXY_PORT);
        curl_setopt($curl, CURLOPT_PROXYUSERPWD, PROXY_USER . ":" . PROXY_PASSWORD);
    }

    return $curl;
}

/**
 * Get Remote data from url using curl
 * @param <String> $url : input url to send GET request
 * @param <String> $useragent : useragent modification
 * @param <boolean> $info : set to true to return transfert info
 *
 * @return either a stringarray containing data and info if $info is set to true
 *
 * Code from mapshup (http://mapshup.info)
 */
function getRemoteData($url, $useragent, $info) {
    if (!empty($url)) {
        $curl = initCurl($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        if ($useragent != null) {
            curl_setopt($curl, CURLOPT_USERAGENT, $useragent);
        }
        $theData = curl_exec($curl);
        $info == true ? $theInfo = curl_getinfo($curl) : "";
        curl_close($curl);
        return $info == true ? array("data" => $theData, "info" => $theInfo) : $theData;
    }
    return $info == true ? array("data" => "", "info" => "") : "";
}


function xmlToDatabase($theData) {

    $doc = new DOMDocument();
    $doc->loadXML($theData);

    // Database connection to Charter NG database
    $dbh = pg_connect("host=".CHARTERNG_DB_HOST." dbname=".CHARTERNG_DB_NAME." user=".CHARTERNG_DB_USER." password=".CHARTERNG_DB_PASSWORD) or die(pg_last_error());

    // Check disasters
    $disasters = $doc->getElementsByTagname('disaster');

    if ($disasters->item(0) == null) {
        return false;
    }

    /*
     * Clean database
     */
    echo " >> Drop disasters table content \n";
    pg_query($dbh, "DELETE FROM disasters") or die("Error in SQL query: " . pg_last_error());

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
    $fields = "(callid,type,disasterdate,title,description,link,image,modifieddate,location)";
        
    foreach ($disasters as $disaster) {
        
        // Get longitude and latitude coordinates
        $lonlat = explode(" ", $disaster->getElementsByTagname('pos')->item(0)->nodeValue);
        
        /*
         * Automatic correction of incorrect disasters
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
        $title = $disaster->getElementsByTagname('title')->item(0)->nodeValue;
        $type = $disaster->getElementsByTagname('type')->item(0)->nodeValue;
        
        if ($type == "OTHER") {
            
            /*
             * Check for disaster type within title
             * Hypothesis is that disaster type is the first word within the title
             */
             $words = explode (' ', $title);
             
             foreach ($words as $word) {
                 
                $word = strtolower(str_replace(",", "", trim($word)));
                
                if (in_array($word, array('flood','flooding','floods'))) {
                    $type = 'FLOOD';
                    break;
                }
                else if (in_array($word, array('ocean','tsunami','huricane','hurricane-force'))) {
                    $type = 'CYCLONE';
                    break;
                }
                else if (in_array($word, array('landslide','landslides'))) {
                    $type = 'LANDSLIDE';
                    break;
                }
                else if (in_array($word, array('earthquake'))) {
                    $type = 'EARTHQUAKE';
                    break;
                }
                /*
                else if (in_array($first, ['snow','ice'])) {
                    $type = '';
                    break;
                }
                else if (in_array($first, ['debris'])) {
                    $type = '';
                    break;
                }*/
                
             }
             
        }
        $values = "'"
                . pg_escape_string($disaster->getElementsByTagname('call-id')->item(0)->nodeValue) . "','"
                . pg_escape_string($type) . "','"
                . pg_escape_string($disaster->getElementsByTagname('date')->item(0)->nodeValue) . "','"
                . pg_escape_string($title) . "','"
                . pg_escape_string($disaster->getElementsByTagname('description')->item(0)->nodeValue) . "','"
                . pg_escape_string($disaster->getElementsByTagname('link')->item(0)->nodeValue) . "','"
                . pg_escape_string($disaster->getElementsByTagname('image')->item(0)->nodeValue) . "',"
                . "now(),"
                . "ST_GeomFromText('POINT(" . floatval($lonlat[1]) . " " . floatval($lonlat[0]) . ")', 4326)";

        
        echo " >> Insert disaster " . $disaster->getElementsByTagname('call-id')->item(0)->nodeValue . "\n";
        $query = "INSERT INTO disasters " . $fields . " VALUES (" . $values . ")";
        pg_query($dbh, $query) or die("Error in SQL query: " . pg_last_error());
            
    }
    
    echo " >> Insertion completed \n";

    // Close database connexion
    pg_close($dbh);

    return true;
}

/* ===================== END FUNCTIONS ========================== */

// Set Timezone
date_default_timezone_set("Europe/Paris");

// This application can only be called from a shell (not from a webserver)
if (empty($_SERVER['SHELL'])) {
    exit;
}

// Get format and callid
if (!$_SERVER['argv'][1]) {
    echo "\n    Usage : " . $_SERVER['argv'][0] . " [CHARTERNG_MANAGE_DIR]\n";
    echo "      Insert disasters description from ESA feed\n\n"; 
    exit;
}

// Configuration files
include_once $_SERVER['argv'][1] . '/config.php';

// ESA XML feed
$url = 'http://www.disasterscharter.org/DisasterCharter/CnesXml?articleType=activation&locale=en_US&companyId=1&communityId=10729';

// Go !
xmlToDatabase(getRemoteData($url, null, false));

?>