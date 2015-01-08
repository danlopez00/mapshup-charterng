<?php

/**
 * REST Utilities functions
 */
class RestUtil {

    /**
     * Return an array of posted/put files or POST stream within HTTP request Body
     *
     * @param array $params - query parameters
     *
     * @return array
     * @throws Exception
     */
    public static function readInputData() {

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
}
