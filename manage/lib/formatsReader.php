<?php
/*
 * Charter NG - Formats Reader
 *
 * Author : Jerome Gasperi @ CNES
 * Date   : 2012.12.03
 *
 * Some parts of code are from mapshup (http://mapshup.info)
 *
 *
 * Each reader take a metadata content (string) as input and
 * return a json object with the following structure :
 * 
 *      {
 *          identifier
 *          parentidentifier
 *          startdate,
 *          enddate,
 *          platform, // Platform/shortName + Platform/identifier
 *          instrument,
 *          footprint // WKT POLYGON representation
 *      }
 *
 * Note on the parentIndentifier (aka OGC urns)
 * The parentIdentifier is added to the product identifier following :
 * 
 *    ALOS         => Read within the EOP.xml metadata file (urn:ogc:def:EOP:JAXA:ALOS)
 *    DIMAP (SPOT) => urn:ogc:def:EOP:SPOT:ALL
 *    DIMAP (DMC)  => urn:ogc:def:EOP:DMC:<Satellite name>
 *    ENVISAT      => Read within the EOP.xml or SAR.xml metadata file
 *    FORMOSAT     => urn:ogc:def:EOP:FORMOSAT
 *    IRS          => urn:ogc:def:EOP:ISRO:IRS
 *    KOMPSAT-2    => Read within the eop.xml metadata file (urn:ogc:def:EOP:KOMPSAT-2:ALL)
 *    PHR(Pleiades)=> urn:ogc:def:EOP:PHR:<1A or 1B>
 *    Radarsat 1   => urn:ogc:def:EOP:CSA:RSAT1
 *    Radarsat 2   => urn:ogc:def:EOP:CSA:RSAT2
 *    SAC-C        => urn:ogc:def:EOP:CONAE:SAC-C
 *    LANDSAT      => urn:ogc:def:EOP:USGS:LANDSAT
 *
 */

/**
 *
 * Check if polygon auto-intersect
 *
 * @param {array} coords (i.e. array of ("lon", "lat"))
 *
 * From http://stackoverflow.com/questions/563198/how-do-you-detect-where-two-line-segments-intersect/563275#563275
 *
 * The problem reduces to this question: Do two lines from A to B and from C to D intersect?
 *
 * Here's the vector math for doing it. I'm assuming the line from A to B is the line in question
 * and the line from C to D is one of the rectangle lines. My notation is that Ax is the "x-coordinate of A"
 * and Cy is the "y-coordinate of C." And "*" means dot-product, so e.g. A*B = Ax*Bx + Ay*By.
 *
 *  E = B-A = ( Bx-Ax, By-Ay )
 *  F = D-C = ( Dx-Cx, Dy-Cy ) 
 *  P = ( -Ey, Ex )
 *  h = ( (A-C) * P ) / ( F * P )
 *
 *  This h number is the key. If h is between 0 and 1, the lines intersect, otherwise they don't.
 *  If F*P is zero, of course you cannot make the calculation, but in this case the lines are parallel
 *  and therefore only intersect in the obvious cases.
 *  
 *  The exact point of intersection is C + F*h.
 *
 *  If h is exactly 0 or 1 the lines touch at an end-point. You can consider this an "intersection" or not as you see fit.
 *  Specifically, h is how much you have to multiply the length of the line in order to exactly touch the other line.
 *  Therefore, If h<0, it means the rectangle line is "behind" the given line (with "direction" being "from A to B"),
 *  and if h>1 the rectangle line is "in front" of the given line.
 *
 */

/**
 *
 * From Kaboum : http://code.google.com/p/geoadminsuite/source/browse/branches/kaboum/src/org/kaboum/algorithm/KaboumAlgorithms.java
 *
 * Compute the magnitude of the cross Product between two
 * Vectors AB and AC. The magnitude of the cross product is twice
 * the area of the triangle they determine.
 * (From: "Computational Geometry in C" by Joseph O'Rourke)
 * (Image coordinates version)
 *
 * @param $a ("lon", "lat")
 * @param $b ("lon", "lat")
 * @param $c ("lon", "lat")
 *
 */
function area2($a, $b, $c) {
    $ax = $a["lon"];
    $ay = $a["lat"];
    $bx = $b["lon"];
    $by = $b["lat"];
    $cx = $c["lon"];
    $cy = $c["lat"];
    return ($bx - $ax) * ($cy - $ay) - ($cx - $ax) * ($by - $ay);
}

/**
 *
 * From Kaboum : http://code.google.com/p/geoadminsuite/source/browse/branches/kaboum/src/org/kaboum/algorithm/KaboumAlgorithms.java
 *
 * Determine if a point c is colinear with segment [ab].
 * This is true if the area of triangle (a,b,c) is null.
 * (From: "Computational Geometry in C" by Joseph O'Rourke)
 * (Image coordinates version)
 *
 * @param $a ("lon", "lat")
 * @param $b ("lon", "lat")
 * @param $c ("lon", "lat")
 *
 */
function isColinear($a, $b, $c) {
    return area2($a, $b, $c) === 0;
}

/**
 *
 * From Kaboum : http://code.google.com/p/geoadminsuite/source/browse/branches/kaboum/src/org/kaboum/algorithm/KaboumAlgorithms.java
 *
 * Determine if a point c is to the left of segment [ab].
 * This is true if the area of triangle (a,b,c) is positive
 * (From: "Computational Geometry in C" by Joseph O'Rourke)
 * (Image coordinates version)
 *
 * @param $a ("lon", "lat")
 * @param $b ("lon", "lat")
 * @param $c ("lon", "lat")
 *
 */
function isLeft($a, $b, $c) {
    return area2($a, $b, $c) > 0;
}

/**
 *
 * From Kaboum : http://code.google.com/p/geoadminsuite/source/browse/branches/kaboum/src/org/kaboum/algorithm/KaboumAlgorithms.java
 *
 * Exclusive or: true if exactly one argument is true
 * (From: "Computational Geometry in C" by Joseph O'Rourke)
 *
 * @param $x Condition 1
 * @param $y Condition 2
 *
 */
function xxor($x,$y) {
    return $x ^ $y;
}  

/**
 *
 * From Kaboum : http://code.google.com/p/geoadminsuite/source/browse/branches/kaboum/src/org/kaboum/algorithm/KaboumAlgorithms.java
 *
 * Return true if segment [ab] intersects segment [cd]
 *
 * @param $a ("lon", "lat")
 * @param $b ("lon", "lat")
 * @param $c ("lon", "lat")
 * @param $d ("lon", "lat")
 *
 */
function intersect($a, $b, $c, $d) {
    
    // Eliminates improper cases
    if (isColinear($a, $b, $c) || isColinear($a, $b, $d) || isColinear($c, $d, $a) || isColinear($c, $d, $b)) {
        return false;
    }
    
    return xxor(isLeft($a, $b, $c), isLeft($a, $b, $d)) && xxor(isLeft($c, $d, $a), isLeft($c, $d, $b));
    
}

/**
 * 
 * Check if a polygon is self intersecting
 *
 * Back to the past...from Kaboum source code:)
 * http://code.google.com/p/geoadminsuite/source/browse/branches/kaboum/src/org/kaboum/algorithm/KaboumAlgorithms.java
 *
 */
function selfIntersect($coords) {
    
    $numPoints = count($coords);
    
    // Obvious
    if ($numPoints < 3) {
        return true;
    }
    
    // Suppress the last point if first and last points are the same
    if (($coords[0]["lon"] === $coords[$numPoints - 1]["lon"]) && ($coords[0]["lat"] === $coords[$numPoints - 1]["lat"])) {
        $numPoints = $numPoints - 1;
    }
    
    // Obvious
    if ($numPoints < 3) {
        return true;
    }
    
    $positionA = -1;
    $positionB = -1;
    $positionC = -1;
    $positionD = -1;
    $tmpPositionExternal = -1;
    $tmpPosition = -1;
    
    for ($i = 0; $i < $numPoints; $i++) {
        
        $positionA = $i;
        
        $positionB = $i < $numPoints - 1 ? $i + 1 : 0;
        
        for ($j = 0; $j < $numPoints; $j++) {
            
            if ($i === $j) {
                continue;
            }
            
            if ($positionB === $j) {
                continue;
            }
            
            $positionC = $j;
            
            $positionD = $j < $numPoints - 1 ? $j + 1 : 0;
            
            if ($positionD == $positionA) {
                continue;
            }
            
            if (intersect($coords[$positionA], $coords[$positionB], $coords[$positionC], $coords[$positionD])) {
                return true;
            }
            
        }
    }

    return false;
    
}

/**
 * Return a valid WKT Polygon (i.e. without auto-intersection)
 * from 4 unordered coordinates pairs
 * 
 * @param {Array} array of 4 ("lon", "lat") pairs
 */
function polygonFromQuad($quads) {
  $good = array($quads[0], $quads[1], $quads[2], $quads[3]);
  if (selfIntersect($good)) {
    $good = array($quads[0], $quads[2], $quads[1], $quads[3]);
    if (selfIntersect($good)) {
      $good = array($quads[0], $quads[2], $quads[3], $quads[1]);
      if (selfIntersect($good)) {
        $good = array($quads[0], $quads[3], $quads[2], $quads[1]);
        if (selfIntersect($good)) {
          $good = array($quads[0], $quads[3], $quads[1], $quads[2]);
          if (selfIntersect($good)) {
            $good = array($quads[0], $quads[1], $quads[3], $quads[2]);
          }
        }
      }
    }
  }
  return 'POLYGON((' . $good[0]["lon"] . ' ' . $good[0]["lat"] . ',' . $good[1]["lon"] . ' ' . $good[1]["lat"] . ',' . $good[2]["lon"] . ' ' . $good[2]["lat"] . ',' . $good[3]["lon"] . ' ' . $good[3]["lat"] . ',' . $good[0]["lon"] . ' ' . $good[0]["lat"] . '))';
}

/**
 * Clean identifier by replacing :
 *    - " ", ":" and "." by "_"
 *    - "/" by "-"
 */
function correctIdentifier($identifier) {
    $search  = array(' ', ':', '.', '/');
    $replace = array('_', '_', '_', '-');
    return str_replace($search, $replace, $identifier);
}

/**
 * Clean date by replacing :
 *    - "Z" and "+00:00" by ""
 */
function correctDate($identifier) {
    $search  = array('+00:00', 'Z');
    $replace = array('','');
    return str_replace($search, $replace, $identifier);
}


/**
 * Return WKT polygon geometry from poslist
 *
 * @param <String> $posList : coordinates string (lon1 lat1 lon2 lat2 ... lonn latn)
 * @param <String> $order : coordinates order of the input poslist ("LONLAT" for lon then lat; "LATLON" for lat then lon)
 *
 * @return WKT polygon
 */
function posListToWKT($posList, $order) {

    /*
     * Explode posList into the $coordinates array
     * Note the trim() to avoid weird results :)
     */
    $posList = preg_replace('!\s+!', ' ', $posList);
    $coordinates = explode(' ', trim($posList));
    $count = count($coordinates);
    $polygon = '';

    /*
     * Parse each coordinates
     */
    for ($i = 0; $i < $count; $i = $i + 2) {

        /*
         * Case 1 : coordinates order is latitude then longitude
         */
        if ($order === "LATLON") {
            $polygon .= ((float)$coordinates[$i + 1]) . ' ' . ((float)$coordinates[$i]) . ',';
        }
        /*
         * Case 2 : coordinates order is longitude then latitude
         */
        else {
            $polygon .= ((float)$coordinates[$i]) . ' ' . ((float)$coordinates[$i + 1]) . ',';
        }
    }

    // Substring to remove the last ',' character
    return 'POLYGON((' . substr($polygon, 0, -1) . '))';
}



/**
 * Read HMA EOP format.
 * 
 * Used by the following agencies :
 *    - ESA
 *    - DLR
 *    - CSA (Radarsat 1)
 *    - CNSA (CBERS)
 *    - JAXA
 *    - KARI
 *
 * Example of HMA OPT (only keeping interesting elements - other are removed)

          <?xml version="1.0" encoding="utf-8" standalone="yes"?>
          <opt:EarthObservation xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:pfn="http://hma.cnes.fr/phr/xslt-functions" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:fn="http://www.w3.org/2005/xpath-functions" xmlns:gml="http://www.opengis.net/gml" xmlns:eop="http://earth.esa.int/eop" xmlns:opt="http://earth.esa.int/opt" gml:id="ALPSRP160026890" version="1.2.1">
            <gml:metaDataProperty>
              <eop:EarthObservationMetaData>
                <eop:identifier>ALPSRP160026890</eop:identifier>
              </eop:EarthObservationMetaData>
            </gml:metaDataProperty>
            <gml:validTime>
              <gml:TimePeriod>
                <gml:beginPosition>2009-01-25T03:16:16.545+00:00</gml:beginPosition>
                <gml:endPosition>2009-01-25T03:16:25.134+00:00</gml:endPosition>
              </gml:TimePeriod>
            </gml:validTime>
            <gml:using>
              <eop:EarthObservationEquipment>
                <eop:platform>
                  <eop:Platform>
                    <eop:shortName>ALOS</eop:shortName>
                    <eop:serialIdentifier>1</eop:serialIdentifier>
                  </eop:Platform>
                </eop:platform>
                <eop:instrument>
                  <eop:Instrument>
                    <eop:shortName>PSR</eop:shortName>
                  </eop:Instrument>
                </eop:instrument>
              </eop:EarthObservationEquipment>
            </gml:using>
            <gml:target>
              <eop:Footprint>
                <gml:multiExtentOf>
                  <gml:MultiSurface srsName="EPSG:4326">
                    <gml:surfaceMembers>
                      <gml:Polygon>
                        <gml:exterior>
                          <gml:LinearRing>
                            <gml:posList>-14.266 -65.303 -14.120 -64.672 -14.637 -64.547 -14.783 -65.179 -14.266 -65.303</gml:posList>
                          </gml:LinearRing>
                        </gml:exterior>
                      </gml:Polygon>
                    </gml:surfaceMembers>
                  </gml:MultiSurface>
                </gml:multiExtentOf>
              </eop:Footprint>
              <gml:resultOf>
                <opt:EarthObservationResult />
              </gml:resultOf>
            </gml:target>
          </opt:EarthObservation>

 * @param String : HMA EOP (SAR or OPT XML) file path
 *
 * @return JSON
 */
function readEOP($filePath) {

    libxml_use_internal_errors(true);

    $doc = new DOMDocument();
    $doc->loadXML(file_get_contents($filePath));
    
    $errors = libxml_get_errors();

    // Footprint as WKT POLYGON
    $footprint = posListToWKT($doc->getElementsByTagname('Polygon')->item(0)->getElementsByTagname('posList')->item(0)->nodeValue, "LATLON");

    // Identifier and parentIdentifier
    $info = $doc->getElementsByTagname('EarthObservationMetaData')->item(0);
    $identifier = $info->getElementsByTagname('identifier')->item(0)->nodeValue;

    // parentIdentifier is set ?
    if ($info->getElementsByTagname('parentIdentifier')->item(0)) {
        $parentIdentifier = $info->getElementsByTagname('parentIdentifier')->item(0)->nodeValue;
    }
    else {

      // We try to detect the mission urn based on the identifier
      $key = substr($identifier, 0, 3);
      switch($key) {
        // JAXA
        case 'ALP':
           $parentIdentifier = "urn:ogc:def:EOP:JAXA:ALOS";
           break;
        case 'ALA':
           $parentIdentifier = "urn:ogc:def:EOP:JAXA:ALOS";
           break;
        default:
          $parentIdentifier = ":";
      }
      $identifier = $parentIdentifier . ':' . $identifier;
    }

    // Platform and instrument
    $platform = $doc->getElementsByTagname('Platform')->item(0);
    if ($platform) {
      $shortName = $platform->getElementsByTagname('shortName')->item(0);
      $serialIdentifier = $platform->getElementsByTagname('serialIdentifier')->item(0);
      $platform = ($shortName ? $shortName->nodeValue : '') . ($serialIdentifier ? $serialIdentifier->nodeValue : '');
    }
    else {
      $plaform = '';
    }
    $instrument = $doc->getElementsByTagname('Instrument')->item(0) ? $doc->getElementsByTagname('Instrument')->item(0)->getElementsByTagname('shortName')->item(0)->nodeValue : '';


    return array(
      'identifier' => $identifier,
      'parentidentifier' => $parentIdentifier,
      'startdate' => correctDate($doc->getElementsByTagname('beginPosition')->item(0)->nodeValue),
      'enddate' => correctDate($doc->getElementsByTagname('endPosition')->item(0)->nodeValue),
      'platform' => $platform,
      'instrument' => $instrument,
      'footprint' => $footprint
    );

}

/**
 * Read DIMAP v1.1 format.
 *
 * Used by the following agencies :
 *    - CNES
 *    - DMC
 *
 * Example of DIMAP (only keeping interesting elements - other are removed)

           <Dimap_Document xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xsi:noNamespaceSchemaLocation='Spot_Scene.xsd' name='METADATA.DIM'>
            <Dataset_Id>
                <DATASET_NAME>SCENE 5 157-373/3 05/12/08 07:23:52 1 T</DATASET_NAME>
                <COPYRIGHT>COPYRIGHT CNES 08 12 2005 07 H 23 MN 52 S</COPYRIGHT>
            </Dataset_Id>
            <Dataset_Frame>
                <Vertex>
                    <FRAME_ROW>1</FRAME_ROW>
                    <FRAME_COL>1</FRAME_COL>
                    <FRAME_LON>43.191744</FRAME_LON>
                    <FRAME_LAT>-11.353272</FRAME_LAT>
                </Vertex>
                <Vertex>
                    <FRAME_LON>43.727661</FRAME_LON>
                    <FRAME_LAT>-11.473516</FRAME_LAT>
                </Vertex>
                <Vertex>
                    <FRAME_LON>43.605659</FRAME_LON>
                    <FRAME_LAT>-12.003823</FRAME_LAT>
                </Vertex>
                <Vertex>
                    <FRAME_LON>43.068656</FRAME_LON>
                    <FRAME_LAT>-11.883452</FRAME_LAT>
                </Vertex>
            </Dataset_Frame>
            <Dataset_Sources>
                <Source_Information>
                    <SOURCE_TYPE>SCENE</SOURCE_TYPE>
                    <SOURCE_ID>51573730512080723521A</SOURCE_ID>
                    <SOURCE_DESCRIPTION>SCENE HRG1 A</SOURCE_DESCRIPTION>
                    <Scene_Source>
                        <IMAGING_DATE>2005-12-08</IMAGING_DATE>
                        <IMAGING_TIME>07:23:55</IMAGING_TIME>
                        <MISSION>SPOT</MISSION>
                        <MISSION_INDEX>5</MISSION_INDEX>
                        <INSTRUMENT>HRG</INSTRUMENT>
                        <INSTRUMENT_INDEX>1</INSTRUMENT_INDEX>
                        <SENSOR_CODE>A</SENSOR_CODE>
                        <SCENE_PROCESSING_LEVEL>0</SCENE_PROCESSING_LEVEL>
                        <INCIDENCE_ANGLE>-2.640282</INCIDENCE_ANGLE>
                        <VIEWING_ANGLE>-2.258720</VIEWING_ANGLE>
                        <SUN_AZIMUTH>118.834519</SUN_AZIMUTH>
                        <SUN_ELEVATION>65.123363</SUN_ELEVATION>
                    </Scene_Source>
                </Source_Information>
                <Source_Information>
                    ...etc...
                </Source_Information>
            </Dataset_Sources>
            <Data_Processing>
                <PROCESSING_LEVEL>1A</PROCESSING_LEVEL>
            </Data_Processing>
            <Data_Strip>
                <Data_Strip_Identification>
                    <DATA_STRIP_ID>S5G1T0512080723506</DATA_STRIP_ID>
                </Data_Strip_Identification>
            </Data_Strip>
        </Dimap_Document>

 *
 * @param String : DIMAP file path
 * @param boolean : true if the metadata is formosat
 *
 * @return JSON
 */
function readDIMAP($filePath, $isFormosat) {

    libxml_use_internal_errors(true);

    $doc = new DOMDocument();
    $doc->loadXML(file_get_contents($filePath));
    
    $errors = libxml_get_errors();

    if (!$isFormosat) {
      $isFirst = 1;
      $footprint = 'POLYGON((';
      
      $vertices = $doc->getElementsByTagname('Dataset_Frame')->item(0)->getElementsByTagname('Vertex');
      // Bug of some F2 files...VERTEX is set instead of Vertex !!
      if ($vertices->length === 0) {
        $vertices = $doc->getElementsByTagname('Dataset_Frame')->item(0)->getElementsByTagname('VERTEX');     
      }
      foreach($vertices as $vertex) {
        $lon = trim($vertex->getElementsByTagName('FRAME_LON')->item(0)->nodeValue);
        $lat = trim($vertex->getElementsByTagName('FRAME_LAT')->item(0)->nodeValue);
        if ($isFirst === 1) {
            $lon1 = $lon;
            $lat1 = $lat;
            $isFirst = 0;
        }
        $footprint .= ((float) $lon) . ' ' . ((float) $lat) . ',';
        
      }
      $footprint .= ((float) $lon1) . ' ' . ((float) $lat1) . '))';
    }

    else {
      /*
       *  Footprint as WKT POLYGON
       *  
       *  Formosat metadata are buggy and coordinates are not ordered so we
       *  must use the line/pixel info to reorder geographical
       *  coordinates...
       */
      $quads = array();
      $firstLine = 999999999;
      $lastLine = -999999999;
      $firstPixel = 999999999;
      $lastPixel = -999999999;

      $vertices = $doc->getElementsByTagname('Dataset_Frame')->item(0)->getElementsByTagname('Vertex');
      // Bug of some F2 files...VERTEX is set instead of Vertex !!
      if ($vertices->length === 0) {
        $vertices = $doc->getElementsByTagname('Dataset_Frame')->item(0)->getElementsByTagname('VERTEX');     
      }
      
      foreach($vertices as $vertex) {
        $lon = trim($vertex->getElementsByTagName('FRAME_LON')->item(0)->nodeValue);
        $lat = trim($vertex->getElementsByTagName('FRAME_LAT')->item(0)->nodeValue);
        $line = trim($vertex->getElementsByTagName('FRAME_ROW')->item(0)->nodeValue);
        $pixel = trim($vertex->getElementsByTagName('FRAME_COL')->item(0)->nodeValue);
        
        // Find the image extrema
        $firstLine = min($firstLine, $line);
        $firstPixel = min($firstPixel, $pixel);
        $lastLine = max($lastLine, $line);
        $lastPixel = max($lastPixel, $pixel);
        
        array_push($quads, array(
          'line' => $line,
          'pixel' => $pixel,
          'lon' => ((float) $lon),
          'lat' => ((float) $lat)
        ));
      }

      // Simple computation UL,UR,LL,LR coordinates
      $footprint = polygonFromQuad($quads);
    }

    // Only process first scene
    $scene = $doc->getElementsByTagname('Source_Information')->item(0);
    $sceneInfo = $scene->getElementsByTagname('Scene_Source')->item(0);
    $time = $sceneInfo->getElementsByTagname('IMAGING_DATE')->item(0)->nodeValue . 'T' . $sceneInfo->getElementsByTagname('IMAGING_TIME')->item(0)->nodeValue;
    $mission = $sceneInfo->getElementsByTagname('MISSION')->item(0)->nodeValue;
    
    // CNES (SPOT) or DMC ?
    if ($isFormosat) {
      $parentIdentifier = "urn:ogc:def:EOP:FORMOSAT"; 
    }
    else if (strrpos($doc->getElementsByTagname('METADATA_PROFILE')->item(0)->nodeValue, "DMC") === 0) {
      $parentIdentifier = "urn:ogc:def:EOP:DMC:" . $mission;
    }
    else {
      $parentIdentifier = "urn:ogc:def:EOP:SPOT:ALL"; 
    }

    return array(
      'identifier' => $parentIdentifier . ':' . correctIdentifier($scene->getElementsByTagname('SOURCE_ID')->item(0)->nodeValue),
      'parentidentifier' => $parentIdentifier,
      'startdate' => $time,
      'enddate' => $time,
      'platform' => $mission . ($sceneInfo->getElementsByTagname('MISSION_INDEX')->item(0) ? ' ' . $sceneInfo->getElementsByTagname('MISSION_INDEX')->item(0)->nodeValue : ''),
      'instrument' => $sceneInfo->getElementsByTagname('INSTRUMENT')->item(0)->nodeValue . ($sceneInfo->getElementsByTagname('INSTRUMENT_INDEX')->item(0) ? ' ' . $sceneInfo->getElementsByTagname('INSTRUMENT_INDEX')->item(0)->nodeValue : ''),
      'footprint' => $footprint
    );

}

/**
 * Read DIMAP v2 format.
 *
 * Used by the following agencies :
 *    - CNES (for Pleiades PHR)
 *
 * Example of DIMAP v2 (only keeping interesting elements - other are removed)

        <Dimap_Document>
          <Metadata_Identification>
            <METADATA_FORMAT version="2.0">DIMAP</METADATA_FORMAT>
            <METADATA_PROFILE>PHR_SENSOR</METADATA_PROFILE>
            <METADATA_SUBPROFILE>PRODUCT</METADATA_SUBPROFILE>
            <METADATA_LANGUAGE>en</METADATA_LANGUAGE>
          </Metadata_Identification>
          <Dataset_Identification>
            <DATASET_NAME version="1.0">DS_PHR1A_201210040818488_FR1_PX_E033N09_0924_01859</DATASET_NAME>
            <Legal_Constraints>
              <COPYRIGHT>Copyright@2012 CNES</COPYRIGHT>
            </Legal_Constraints>
          </Dataset_Identification>
          <Dataset_Content>
            <Dataset_Extent>
              <EXTENT_TYPE>Bounding_Polygon</EXTENT_TYPE>
              <Vertex>
                <LON>33.57708307493774</LON>
                <LAT>10.07080532946307</LAT>
                <COL>1</COL>
                <ROW>1</ROW>
              </Vertex>
              <Vertex>
                <LON>33.76169809689237</LON>
                <LAT>10.06956601458526</LAT>
                <COL>41500</COL>
                <ROW>1</ROW>
              </Vertex>
              <Vertex>
                <LON>33.76152215778689</LON>
                <LAT>9.883986427793989</LAT>
                <COL>41500</COL>
                <ROW>41008</ROW>
              </Vertex>
              <Vertex>
                <LON>33.57728269408435</LON>
                <LAT>9.884587971623581</LAT>
                <COL>1</COL>
                <ROW>41008</ROW>
              </Vertex>
            </Dataset_Extent>
          </Dataset_Content>
          <Processing_Information>
            <Production_Facility>
              <SOFTWARE version="V_03_09">IPU V_03_09</SOFTWARE>
              <PROCESSING_CENTER>FCMUGC</PROCESSING_CENTER>
              <PROCESSING_PLACE/>
            </Production_Facility>
            <Product_Settings>
                ...
                <SPECTRAL_PROCESSING>P</SPECTRAL_PROCESSING>
                ...
          </Processing_Information>
          <Dataset_Sources>
            <Source_Identification>
              <SOURCE_ID>DS_PHR1A_201210040819238_FR1_PX_E033N09_0924_01863</SOURCE_ID>
              <Strip_Source>
                <MISSION>PHR</MISSION>
                <MISSION_INDEX>1A</MISSION_INDEX>
                <INSTRUMENT>PHR</INSTRUMENT>
                <INSTRUMENT_INDEX>1A</INSTRUMENT_INDEX>
                <IMAGING_DATE>2012-10-04</IMAGING_DATE>
                <IMAGING_TIME>08:19:23.8Z</IMAGING_TIME>
                <BAND_MODE>PX</BAND_MODE>
              </Strip_Source>
            </Source_Identification>
          </Dataset_Sources>
        <Dimap_Document>
 *
 * @param String : DIMAPv2 file path
 *
 * @return JSON
 */
function readDIMAPv2($filePath) {

    libxml_use_internal_errors(true);

    $doc = new DOMDocument();
    $doc->loadXML(file_get_contents($filePath));
    
    $errors = libxml_get_errors();

    $isFirst = 1;
    $footprint = 'POLYGON((';
    
    $spectralMode = $doc->getElementsByTagname('Processing_Information')->item(0)->getElementsByTagname('Product_Settings')->item(0)->getElementsByTagname('SPECTRAL_PROCESSING')->item(0)->nodeValue;
    $vertices = $doc->getElementsByTagname('Dataset_Extent')->item(0)->getElementsByTagname('Vertex');
    foreach($vertices as $vertex) {
      $lon = trim($vertex->getElementsByTagName('LON')->item(0)->nodeValue);
      $lat = trim($vertex->getElementsByTagName('LAT')->item(0)->nodeValue);
      if ($isFirst === 1) {
          $lon1 = $lon;
          $lat1 = $lat;
          $isFirst = 0;
      }
      $footprint .= ((float) $lon) . ' ' . ((float) $lat) . ',';
      
    }
    $footprint .= ((float) $lon1) . ' ' . ((float) $lat1) . '))';
    
    // Only process first scene
    $scene = $doc->getElementsByTagname('Source_Identification')->item(0);
    $sceneInfo = $scene->getElementsByTagname('Strip_Source')->item(0);
    $time = $sceneInfo->getElementsByTagname('IMAGING_DATE')->item(0)->nodeValue . 'T' . $sceneInfo->getElementsByTagname('IMAGING_TIME')->item(0)->nodeValue;
    $mission = $sceneInfo->getElementsByTagname('MISSION')->item(0)->nodeValue;
    
    // CNES (SPOT) or DMC ?
    $parentIdentifier = "urn:ogc:def:EOP:PHR:" . $sceneInfo->getElementsByTagname('MISSION_INDEX')->item(0)->nodeValue; 
   
    return array(
      'identifier' => $parentIdentifier . ':' . correctIdentifier($scene->getElementsByTagname('SOURCE_ID')->item(0)->nodeValue . $spectralMode),
      'parentidentifier' => $parentIdentifier,
      'startdate' => $time,
      'enddate' => $time,
      'platform' => $mission . ($sceneInfo->getElementsByTagname('MISSION_INDEX')->item(0) ? ' ' . $sceneInfo->getElementsByTagname('MISSION_INDEX')->item(0)->nodeValue : ''),
      'instrument' => $sceneInfo->getElementsByTagname('INSTRUMENT')->item(0)->nodeValue . ($sceneInfo->getElementsByTagname('INSTRUMENT_INDEX')->item(0) ? ' ' . $sceneInfo->getElementsByTagname('INSTRUMENT_INDEX')->item(0)->nodeValue : ''),
      'footprint' => $footprint
    );

}

/**
 * Read FORMOSAT format.
 * 
 * FORMOSAT is a DIMAP format
 *
 * Used by the following agencies :
 *    - CNES
 * 
 * @param String : FORMOSAT (DIMAP) file path
 *
 * @return JSON
 */
function readF2($filePath) {
  return readDIMAP($filePath, true);
}


/*
 * Read SACC format.
 * 
 * Used by the following agencies :
 *    - CONAE
 *
 *  Example of SACC text file (only keeping interesting elements - other are removed)
 *
          satellite: SAC-C
          sensor: MMRS
          imageID: 20080510_123944_rs_9_mmrs-hr
          start: 2008/05/10 12:49:47
          stop: 2008/05/10 12:51:06
          lat_upper_left: -41.40099
          lon_upper_left: -74.34486
          lat_upper_right: -42.01559
          lon_upper_right: -69.93225
          lat_lower_left: -46.01735
          lon_lower_left: -76.28397
          lat_lower_right: -46.69219
          lon_lower_right: -71.50719
 *
 * 
 * @param String : SACC file path
 *
 * @return JSON
 */
function readSACC($filePath) {

  // See comment at the beginning of this script
  $parentIdentifier = "urn:ogc:def:EOP:CONAE:SAC-C";
  
  // Initialize empty array
  $json = array(
    'parentIdentifier' => $parentIdentifier
  );

  // Read file line by line ignoring carriage return
  $lines = file($filePath, FILE_IGNORE_NEW_LINES);

  // Parse each line to find interesting metadata
  foreach ($lines as $line_num => $line) {
    $kvp = explode(":", $line);

    /*
     * This is a bit tricky - since time are ":" separated" the value
     * is not $kvp[1] but $kvp with "$kvp[0]:" removed...
     */
    $value = trim(substr($line, strlen($kvp[0]) + 1));

    switch($kvp[0]) {
      case "imageID":
        $json["identifier"]= $parentIdentifier . ':' . $value;
        break;
      // Date format is YYYY/MM/DD HH:MM:SS to be converted in YYYY-MM-DDTHH:MM:SS
      case "start":
        $search  = array('/', ' ');
        $replace = array('-', 'T');
        $json["startDate"]= str_replace($search, $replace, $value);
        break;
      // Date format is YYYY/MM/DD HH:MM:SS to be converted in YYYY-MM-DDTHH:MM:SS
      case "stop":
        $search  = array('/', ' ');
        $replace = array('-', 'T');
        $json["endDate"]= str_replace($search, $replace, $value);
        break;
      case "satellite":
        $json["platform"]= $value;
        break;
      case "sensor":
        $json["instrument"]= $value;
      case "lat_upper_left":
        $ULlat = $value;
        break;
      case "lon_upper_left":
        $ULlon = $value;
        break;
      case "lat_upper_right":
        $URlat = $value;
        break;
      case "lon_upper_right":
        $URlon = $value;
        break;
      case "lat_lower_left":
        $LLlat = $value;
        break;
      case "lon_lower_left":
        $LLlon = $value;
        break;
      case "lat_lower_right":
        $LRlat = $value;
        break;
      case "lon_lower_right":
        $LRlon = $value;
        break;
    }
  }

  // Footprint
  $json["footprint"] = 'POLYGON(('
    . ((float) $ULlon) . ' ' . ((float) $ULlat) . ','
    . ((float) $URlon) . ' ' . ((float) $URlat) . ','
    . ((float) $LRlon) . ' ' . ((float) $LRlat) . ','
    . ((float) $LLlon) . ' ' . ((float) $LLlat) . ','
    . ((float) $ULlon) . ' ' . ((float) $ULlat) . '))';

  return $json;

}

/*
 * Read SACC format.
 * 
 * Used by the following agencies :
 *    - ISRO
 *
 *  Example of IRS text file (only keeping interesting elements - other are removed)
 *
          Satellite   P6
          Sensor  AWIF
          DateOfPass  01-NOV-2010
          North West Latitude   17.096
          North West Longitude  95.171
          North East Latitude 15.612
          North East Longitude  101.926
          South East Latitude   9.103
          South East Longitude  100.341
          South West Latitude   10.574
          South West Longitude  93.758
          Scene Start Time  305040752717
          Scene End Time  305040943515
 *
 * 
 * @param String : IRS file path
 *
 * @return JSON
 */
function readIRS($filePath) {

  // See comment at the beginning of this script
  $parentIdentifier = "urn:ogc:def:EOP:ISRO:IRS";
  $fileName = explode("/", $filePath);

  // Initialize json array
  $json = array(
    'parentIdentifier' => $parentIdentifier,
    'identifier' => $parentIdentifier . ':' . substr($fileName[count($fileName) - 1], 0, -4)
  );

  // Read file line by line ignoring carriage return
  $lines = file($filePath, FILE_IGNORE_NEW_LINES);

  // Parse each line to find interesting metadata
  foreach ($lines as $line_num => $line) {
    
    /*
     * Basic grep...
     */
    if (strpos($line, "Satellite") === 0) {
      $json["platform"] = "IRS " . trim(substr($line, strlen("Satellite")));
    }
    else if (strpos($line, "Sensor") === 0) {
      $json["instrument"] = trim(substr($line, strlen("Sensor")));
    }
    else if (strpos($line, "North West Latitude") === 0) {
      $ULlat = trim(substr($line, strlen("North West Latitude")));
    }
    else if (strpos($line, "North West Longitude") === 0) {
      $ULlon = trim(substr($line, strlen("North West Longitude")));
    }
    else if (strpos($line, "North East Latitude") === 0) {
      $URlat = trim(substr($line, strlen("North East Latitude")));
    }
    else if (strpos($line, "North East Longitude") === 0) {
      $URlon = trim(substr($line, strlen("North East Longitude")));
    }
    else if (strpos($line, "South East Latitude") === 0) {
      $LRlat = trim(substr($line, strlen("South East Latitude")));
    }
    else if (strpos($line, "South East Longitude") === 0) {
      $LRlon = trim(substr($line, strlen("South East Longitude")));
    }
    else if (strpos($line, "South West Latitude") === 0) {
      $LLlat = trim(substr($line, strlen("South West Latitude")));
    }
    else if (strpos($line, "South West Longitude") === 0) {
      $LLlon = trim(substr($line, strlen("South West Longitude")));
    }

  }

  // Footprint
  $json["footprint"] = 'POLYGON(('
    . ((float) $ULlon) . ' ' . ((float) $ULlat) . ','
    . ((float) $URlon) . ' ' . ((float) $URlat) . ','
    . ((float) $LRlon) . ' ' . ((float) $LRlat) . ','
    . ((float) $LLlon) . ' ' . ((float) $LLlat) . ','
    . ((float) $ULlon) . ' ' . ((float) $ULlat) . '))';

  return $json;

}

/*
 * Read LANDSAT format.
 * 
 * Used by the following agencies :
 *    - USGS
 *
 *  Example of LANDSAT text file (only keeping interesting elements - other are removed)
 *
        PRODUCT_TYPE = "L1T"
        SPACECRAFT_ID = "Landsat7"
        SENSOR_ID = "ETM+"
        ACQUISITION_DATE = 2010-10-24
        SCENE_CENTER_SCAN_TIME = 03:11:17.8745641Z
        PRODUCT_UL_CORNER_LAT = 16.8445483
        PRODUCT_UL_CORNER_LON = 104.6066884
        PRODUCT_UR_CORNER_LAT = 16.8358584
        PRODUCT_UR_CORNER_LON = 106.9295682
        PRODUCT_LL_CORNER_LAT = 14.9515140
        PRODUCT_LL_CORNER_LON = 104.6103499
        PRODUCT_LR_CORNER_LAT = 14.9438476
        PRODUCT_LR_CORNER_LON = 106.9116176
 *
 * 
 * @param String : LANDSAT file path
 *
 * @return JSON
 */
function readLANDSAT($filePath) {

  // See comment at the beginning of this script
  $parentIdentifier = "urn:ogc:def:EOP:USGS:LANDSAT";
  $fileName = explode("/", $filePath);

  // Initialize json array
  $json = array(
    'parentIdentifier' => $parentIdentifier,
    'identifier' => $parentIdentifier . ':' . substr($fileName[count($fileName) - 1], 0, -4)
  );

  // Read file line by line ignoring carriage return
  $lines = file($filePath, FILE_IGNORE_NEW_LINES);

  // Parse each line to find interesting metadata
  foreach ($lines as $line_num => $line) {

    $kvp = explode("=", $line);
    if (count($kvp) !== 2) {
        continue;
    }
    $value = trim(str_replace('"', '', $kvp[1]));

    switch(trim($kvp[0])) {
      case "ACQUISITION_DATE":
        $date = $value;
        break;
      case "SCENE_CENTER_SCAN_TIME":
        $time = $value;
        break;
      case "SPACECRAFT_ID":
        $json["platform"]= $value;
        break;
      case "SENSOR_ID":
        $json["instrument"]= $value;
      case "PRODUCT_UL_CORNER_LAT":
        $ULlat = $value;
        break;
      case "PRODUCT_UL_CORNER_LON":
        $ULlon = $value;
        break;
      case "PRODUCT_UR_CORNER_LAT":
        $URlat = $value;
        break;
      case "PRODUCT_UR_CORNER_LON":
        $URlon = $value;
        break;
      case "PRODUCT_LL_CORNER_LAT":
        $LLlat = $value;
        break;
      case "PRODUCT_LL_CORNER_LON":
        $LLlon = $value;
        break;
      case "PRODUCT_LR_CORNER_LAT":
        $LRlat = $value;
        break;
      case "PRODUCT_LR_CORNER_LON":
        $LRlon = $value;
        break;
    }
  }

  // Footprint
  $json["footprint"] = 'POLYGON(('
    . ((float) $ULlon) . ' ' . ((float) $ULlat) . ','
    . ((float) $URlon) . ' ' . ((float) $URlat) . ','
    . ((float) $LRlon) . ' ' . ((float) $LRlat) . ','
    . ((float) $LLlon) . ' ' . ((float) $LLlat) . ','
    . ((float) $ULlon) . ' ' . ((float) $ULlat) . '))';
  
  // Date
  $json["startDate"] = correctDate($date . "T" . $time);
  $json["endDate"] = correctDate($date . "T" . $time);

  return $json;

}


/**
 * Read Radarsat 1 format.
 * 
 * RS1 is an HMA SAR format
 *
 * Used by the following agencies :
 *    - CSA
 * 
 * @param String : Radarsat1 (HMA SAR) file path
 *
 * @return JSON
 */
function readRS1($filePath) {
  return readEOP($filePath);
}

/**
 * Read Radarsat 2 format.
 * 
 * Used by the following agencies :
 *    - CSA

              <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
              <product xmlns="http://www.rsi.ca/rs2/prod/xml/schemas" copyright="RADARSAT-2 Data and Products (c) MacDonald, Dettwiler and Associates Ltd., 2010 - All Rights Reserved." xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.rsi.ca/rs2/prod/xml/schemas schemas/rs2prod_product.xsd">
                <productId>PDS_01035090</productId>
                <sourceAttributes>
                  <satellite>RADARSAT-2</satellite>
                  <sensor>SAR</sensor>
                  <rawDataStartTime>2010-04-27T00:01:42.119000Z</rawDataStartTime>
                </sourceAttributes>
                <imageAttributes>
                  <geographicInformation>
                    <geolocationGrid>
                      <imageTiePoint>
                        <imageCoordinate>
                          <line>2.20410000e+04</line>
                          <pixel>1.21663633e+04</pixel>
                        </imageCoordinate>
                        <geodeticCoordinate>
                          <latitude units="deg">2.966289178383143e+01</latitude>
                          <longitude units="deg">-9.083989311588336e+01</longitude>
                          <height units="m">1.006103992462158e+01</height>
                        </geodeticCoordinate>
                      </imageTiePoint>
                      <imageTiePoint>
                        <imageCoordinate>
                          <line>2.20410000e+04</line>
                          <pixel>1.21663633e+04</pixel>
                        </imageCoordinate>
                        <geodeticCoordinate>
                          <latitude units="deg">2.971459236503227e+01</latitude>
                          <longitude units="deg">-9.053882320404446e+01</longitude>
                          <height units="m">1.006103992462158e+01</height>
                        </geodeticCoordinate>
                      </imageTiePoint>
                      ...etc...
                    </geolocationGrid>
                  </geographicInformation>
                </imageAttributes>
              </product>



 * 
 * @param String : Radarsat2 file path
 *
 * @return JSON
 */
function readRS2($filePath) {

    libxml_use_internal_errors(true);

    $doc = new DOMDocument();
    $doc->loadXML(file_get_contents($filePath));
    
    $errors = libxml_get_errors();

    // See comment at the beginning of this script
    $parentIdentifier = "urn:ogc:def:EOP:CSA:RSAT2";

    /*
     *  Footprint as WKT POLYGON
     *  
     *  Radarsat 2 coordinates are not ordered so we
     *  must use the line/pixel info to reorder geographical
     *  coordinates...
     */
    $quads = array();
    $firstLine = 999999999;
    $lastLine = -999999999;
    $firstPixel = 999999999;
    $lastPixel = -999999999;
    $imageTiePoints = $doc->getElementsByTagname('geographicInformation')->item(0)->getElementsByTagname('geolocationGrid')->item(0)->getElementsByTagname('imageTiePoint');
    foreach($imageTiePoints as $imageTiePoint) {
      $imageCoordinates = $imageTiePoint->getElementsByTagname('imageCoordinate');
      foreach($imageCoordinates as $imageCoordinate) {
        $line = floatval(trim($imageCoordinate->getElementsByTagName('line')->item(0)->nodeValue));
        $pixel = floatval(trim($imageCoordinate->getElementsByTagName('pixel')->item(0)->nodeValue));
      }
      $geodeticCoordinates = $imageTiePoint->getElementsByTagname('geodeticCoordinate');
      foreach($geodeticCoordinates as $geodeticCoordinate) {
        $lon = floatval(trim($geodeticCoordinate->getElementsByTagName('longitude')->item(0)->nodeValue));
        $lat = floatval(trim($geodeticCoordinate->getElementsByTagName('latitude')->item(0)->nodeValue));
      }

      // Find the image extrema
      $firstLine = min($firstLine, $line);
      $firstPixel = min($firstPixel, $pixel);
      $lastLine = max($lastLine, $line);
      $lastPixel = max($lastPixel, $pixel);
      
      array_push($quads, array(
        'line' => $line,
        'pixel' => $pixel,
        'lon' => (float)$lon,
        'lat' => (float)$lat
      ));
    }

    // Simple computation UL,UR,LL,LR coordinates

    foreach($quads as $quad) {
      if ($quad["line"] === $firstLine && $quad["pixel"] === $firstPixel) {
        $UL = $quad["lon"] . ' ' . $quad["lat"];
      }
      if ($quad["line"] === $firstLine && $quad["pixel"] === $lastPixel) {
        $UR = $quad["lon"] . ' ' . $quad["lat"];
      }
      if ($quad["line"] === $lastLine && $quad["pixel"] === $firstPixel) {
        $LL = $quad["lon"] . ' ' . $quad["lat"];
      }
      if ($quad["line"] === $lastLine && $quad["pixel"] === $lastPixel) {
        $LR = $quad["lon"] . ' ' . $quad["lat"];
      }
    }

    $footprint = 'POLYGON((' . $UL . ',' . $UR . ',' . $LR . ',' . $LL . ',' . $UL . '))';

    // Other infos
    $sourceAttributes = $doc->getElementsByTagname('sourceAttributes')->item(0);
    $date = correctDate($sourceAttributes->getElementsByTagname('rawDataStartTime')->item(0)->nodeValue);

    return array(
      'identifier' => $parentIdentifier . ':' . $doc->getElementsByTagname('productId')->item(0)->nodeValue,
      'parentidentifier' => $parentIdentifier,
      'startdate' => $date,
      'enddate' => $date,
      'platform' => $sourceAttributes->getElementsByTagname('satellite')->item(0)->nodeValue,
      'instrument' => $sourceAttributes->getElementsByTagname('sensor')->item(0)->nodeValue,
      'footprint' => $footprint
    );

}

?>