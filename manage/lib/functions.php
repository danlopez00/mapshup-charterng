<?php

/**
 * Safe insert into SQL
 */
function formatForDB($str) {
  if ($str) {
    return "'" . pg_escape_string($str) . "'";
  }
  return "NULL";
}

/**
 * Return an array of all files of type $type within $directory
 *
 * @param   string      The path to the directory
 * @param   string      The file extension to search for (without the ".") - if not set, then all files are returned
 *  
 * @return  array       Array of files
 *
 * Code from http://php.net/manual/fr/ref.zip.php
 *
 */
function getFilesList($directory, $types) {

    // create an array to hold directory list
    $results = array();

    // create a handler for the directory
    $handler = opendir($directory);

    // open directory and walk through the filenames
    while ($file = readdir($handler)) {

      // if file isn't this directory or its parent, add it to the results
      if ($file != "." && $file != "..") {
        if (!$types) {
            $results[] = $file;
        }
        else {
          foreach($types as $type) {
            $parts = explode(".", $file);
            if (strtolower($parts[count($parts) - 1]) === $type) {
              $results[] = $file;
            }
          }
        }
      }

    }

    // tidy up: close the handler
    closedir($handler);

    // done!
    return $results;
}

function getDirectoryList($directory) {

    // create an array to hold directory list
    $results = array();

    // create a handler for the directory
    $handler = opendir($directory);

    // open directory and walk through the filenames
    while ($file = readdir($handler)) {

      // if file isn't this directory or its parent, add it to the results
      if ($file != "." && $file != "..") {
        $results[] = $file;
      }

    }

    // tidy up: close the handler
    closedir($handler);

    // done!
    return $results;
}

/**
 * Unzip the source_file in the destination dir
 *
 * @param   string      The path to the ZIP-file.
 * @param   string      The path where the zipfile should be unpacked, if false the directory of the zip-file is used
 * @param   boolean     Indicates if the files will be unpacked in a directory with the name of the zip-file (true) or not (false) (only if the destination directory is set to false!)
 * @param   boolean     Overwrite existing files (true) or not (false)
 *  
 * @return  boolean     Succesful or not
 *
 * Code from http://php.net/manual/fr/ref.zip.php
 *
 */
function unzip($src_file, $dest_dir=false, $create_zip_name_dir=true, $overwrite=true) {

  ini_set('memory_limit', 200000000);

  if ($zip = zip_open($src_file)) {
    if ($zip) {

      $splitter = ($create_zip_name_dir === true) ? "." : "/";
      if ($dest_dir === false) $dest_dir = substr($src_file, 0, strrpos($src_file, $splitter))."/";
      
      // Create the directories to the destination dir if they don't already exist
      create_dirs($dest_dir);

      // For every file in the zip-packet
      while ($zip_entry = zip_read($zip)) {

        // Now we're going to create the directories in the destination directories
        
        // If the file is not in the root dir
        $pos_last_slash = strrpos(zip_entry_name($zip_entry), "/");
        if ($pos_last_slash !== false) {
          // Create the directory where the zip-entry should be saved (with a "/" at the end)
          create_dirs($dest_dir.substr(zip_entry_name($zip_entry), 0, $pos_last_slash+1));
        }

        // Open the entry
        if (zip_entry_open($zip,$zip_entry,"r")) {
          
          // The name of the file to save on the disk
          $file_name = $dest_dir.zip_entry_name($zip_entry);
          
          // Check if the files should be overwritten or not
          if ($overwrite === true || $overwrite === false && !is_file($file_name)) {
            // Get the content of the zip entry
            $fstream = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));

            file_put_contents($file_name, $fstream);
            // Set the rights
            chmod($file_name, 0755);
            //echo "save: ".$file_name."<br />";
          }
          
          // Close the entry
          zip_entry_close($zip_entry);
        }       
      }
      // Close the zip-file
      zip_close($zip);
    }
  } 
  else {
    return false;
  }
  
  return true;
}

/**
 * This function creates recursive directories if it doesn't already exist
 *
 * @param String  The path that should be created
 *  
 * @return  void
 *
 */
function create_dirs($path) {
  if (!is_dir($path)) {
    $directory_path = "";
    $directories = explode("/",$path);
    array_pop($directories);
    
    foreach($directories as $directory) {
      $directory_path .= $directory."/";
      if (!is_dir($directory_path)) {
        mkdir($directory_path);
        chmod($directory_path, 0755);
      }
    }
  }
}
?>