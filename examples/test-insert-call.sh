#!/bin/sh

curl -X POST -F "file[]=@sample-act-kml.xml" http://localhost/disasterscharter/s/plugins/charterng/admin/insertCall.php