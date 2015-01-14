<?php

/*
 * Domain where application is running
 */
define("CHARTERNG_DOMAIN", "engine.mapshup.info");

/*
 * URLs configuration
 */
define("CHARTERNG_QUICKLOOK_URL", "http://" . CHARTERNG_DOMAIN . "/charterng/md/");
define("CHARTERNG_METADATA_URL", "http://" . CHARTERNG_DOMAIN . "/charterng/md/");

/*
 * Charter NG database configuration
 */
define("CHARTERNG_DB_HOST", "localhost");
define("CHARTERNG_DB_NAME", "charterng");
define("CHARTERNG_DB_USER", "charterng");
define("CHARTERNG_DB_PASSWORD", "1234abcd");

/*
 * Maximum number of results per page
 */
define("RESULTS_PER_PAGE", 50);

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