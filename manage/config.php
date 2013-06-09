<?php

/*
 * Paths configuration
 */
define("CHARTERNG_HOME", "/home/projects/charterng/");
define("CHARTERNG_TARGET", "/home/www/engine.mapshup.info/charterng/");
define("CHARTERNG_METADATA_PATH", CHARTERNG_TARGET . "/md/");

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