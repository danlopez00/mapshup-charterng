<?php

/*
 * Archives directory is where zip files are stored
 */
define("CHARTERNG_ARCHIVES_DIR", "/Users/jrom/tmp/archives/");

/*
 * Metadata directory is where zip files are unzipped
 * !! This directory should be served by Apache - see src/ws/config.php !!
 */
define("CHARTERNG_METADATA_DIR", "/Users/jrom/tmp/md/");

/*
 * Mapserver URL is the url to access mapserver cgi-bin
 */
define("CHARTERNG_MAPSERVER_URL", "http://localhost/cgi-bin/mapserv");

/*
 * Charter NG database configuration
 */
define("CHARTERNG_DB_HOST", "localhost");
define("CHARTERNG_DB_NAME", "charterng");
define("CHARTERNG_DB_USER", "charterng");
define("CHARTERNG_DB_PASSWORD", "1234abcd");

/*
 * GDAL translate (for Radarsat Quicklook creation)
 */
define("GDAL_TRANSLATE_PATH", "/usr/bin/gdal_translate");

/**
 * If your webserver is behind a proxy set USE_PROXY to true
 * The PROXY_* parameters are only used if USE_PROXY
 * is set to true
 */
define("USE_PROXY", false);
define("PROXY_URL", "");
define("PROXY_PORT", "");
define("PROXY_USER", "");
define("PROXY_PASSWORD", "");

?>