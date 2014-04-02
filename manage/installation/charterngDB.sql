-- ==================================================================
-- charterNGDB.sql
--
-- Create the CharterNG database tables
--
-- ==================================================================


-- ==================================================================
--
-- Table disasters
--
-- XML activations feed are retrieved here :
-- 
--          http://www.disasterscharter.org/DisasterCharter/CnesXml?articleType=activation&locale=en_US&companyId=1&communityId=10729 
-- 
-- 
-- The structure is the following :
--      
-- <dch:disasters dch:updated="2012-11-08T23:00:00+0000">
--          <dch:disaster>
--              <dch:title>Earthquake Guatemala</dch:title>
--              <dch:date>2012-11-08T23:00:00+0000</dch:date>
--              <dch:call-id>420</dch:call-id>
--              <dch:type>EARTHQUAKE</dch:type>
--              <dch:description>....</dch:description>
--              <dch:link>http://www.disasterscharter.org/web/charter/activation_details?p_r_p_1415474252_assetId=ACT-420</dch:link>
--              <dch:image>http://www.disasterscharter.org/image/journal/article?img_id=136977</dch:image>
--              <dch:location>
--                  <gml:Point gml:id="p420" srsName="urn:ogc:def:crs:EPSG:6.6:4326">
--                      <gml:pos dimension="2">15.6 -91.54</gml:pos>
--                  </gml:Point>
--              </dch:location>
--          </dch:disaster>
--          [...]
--      </dch:disasters>
--
-- ===================================================================
CREATE TABLE disasters (
    callid            VARCHAR(4) PRIMARY KEY,
    disasterdate      TIMESTAMP,
    title             TEXT,
    type              VARCHAR(50),
    description       TEXT,
    link              VARCHAR(250),
    image             VARCHAR(250),
    modifieddate      TIMESTAMP
);
SELECT AddGeometryColumn(
    'disasters',
    'location',
    '4326',
    'POINT',
    2
);
SELECT AddGeometryColumn(
    'disasters',
    'footprint',
    '4326',
    'POLYGON',
    2
);
-- Index on CALLID --
CREATE INDEX callid_act_idx ON disasters USING btree (callid);
-- Index on DisasterDate --
CREATE INDEX disasterdate_act_idx ON disasters USING btree (disasterdate);
-- Index on Description --
CREATE INDEX desc_act_idx ON disasters (description text_pattern_ops);
-- Index on Title --
CREATE INDEX title_act_idx ON disasters (title text_pattern_ops);
-- Index on Type --
CREATE INDEX type_act_idx ON disasters USING btree (type);
-- Geometry --
CREATE INDEX location_act_idx ON disasters USING GIST (location);
CREATE INDEX footprint_act_idx ON disasters USING GIST (footprint);

-- ==================================================================
--
-- Associative table between KMLs and disaster
--
-- ==================================================================
CREATE TABLE kmls (
    callid            VARCHAR(4),
    kmlurl            TEXT
);
-- Index on CALLID --
CREATE INDEX callid_kmls_idx ON kmls USING btree (callid);

-- ==================================================================
--
-- Table acquisitions
--
-- Structure of EOP (not all parameters are processed)
--
-- <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
-- <sar:EarthObservation xmlns:gml="http://www.opengis.net/gml" xmlns:ns2="http://www.w3.org/1999/xlink" xmlns:eop="http://earth.esa.int/eop" xmlns:sar="http://earth.esa.int/sar" xmlns:ns5="http://earth.esa.int/atm" xmlns:ns6="http://earth.esa.int/opt" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2.1">
--     <gml:metaDataProperty>
--         <eop:EarthObservationMetaData>
--             <eop:identifier>ogc:def:EOP:ESA:ESA.EECF.ENVISAT_ASA_WSx_xS:ENVISAT-1-11122913513940-10119.ASA_WS_0P</eop:identifier>
--             <eop:parentIdentifier>urn:ogc:def:EOP:ESA:ESA.EECF.ENVISAT_ASA_WSx_xS</eop:parentIdentifier>
--             <eop:acquisitionType>NOMINAL</eop:acquisitionType>
--             <eop:productType></eop:productType>
--             <eop:status>ACQUIRED</eop:status>
--             <eop:archivedIn>
--                <eop:ArchivingInformation>
--                     <eop:archivingCenter>ESA</eop:archivingCenter>
--                     <eop:archivingDate>2011-12-29T13:52:40.59Z</eop:archivingDate>
--                     <eop:archivingIdentifier codeSpace="">ASA_WS__0PNPDK20111229_135132_000000783110_00111_51418_4094.N1</eop:archivingIdentifier>
--                 </eop:ArchivingInformation>
--             </eop:archivedIn>
--             <eop:vendorSpecific>
--                  ....NOT PROCESSED...
--            </eop:vendorSpecific>
--        </eop:EarthObservationMetaData>
--    </gml:metaDataProperty>
--    <gml:validTime>
--        <gml:TimePeriod>
--            <gml:beginPosition>2011-12-29T13:51:39.40Z</gml:beginPosition>
--            <gml:endPosition>2011-12-29T13:52:40.59Z</gml:endPosition>
--        </gml:TimePeriod>
--    </gml:validTime>
--    <gml:using>
--        <eop:EarthObservationEquipment>
--            <eop:platform>
--                <eop:Platform>
--                    <eop:shortName>ENVISAT</eop:shortName>
--                    <eop:serialIdentifier>1</eop:serialIdentifier>
--                </eop:Platform>
--            </eop:platform>
--            <eop:instrument>
--                <eop:Instrument>
--                    <eop:shortName>ASAR/WS</eop:shortName>
--                </eop:Instrument>
--            </eop:instrument>
--            <eop:sensor>
--                <eop:Sensor>
--                    <eop:swathIdentifier>WS</eop:swathIdentifier>
--                </eop:Sensor>
--            </eop:sensor>
--            <eop:acquisitionParameters>
--                <sar:Acquisition>
--                    <eop:orbitNumber>51418</eop:orbitNumber>
--                    <eop:lastOrbitNumber>51418</eop:lastOrbitNumber>
--                    <eop:orbitDirection>ASCENDING</eop:orbitDirection>
--                    <eop:wrsLongitudeGrid>111</eop:wrsLongitudeGrid>
--                    <eop:ascendingNodeLongitude uom="deg">124.17</eop:ascendingNodeLongitude>
--                    <eop:startTimeFromAscendingNode uom="ms">102333.0</eop:startTimeFromAscendingNode>
--                    <eop:completionTimeFromAscendingNode uom="ms">163523.0</eop:completionTimeFromAscendingNode>
--                    <sar:polarisationChannels>VV</sar:polarisationChannels>
--                </sar:Acquisition>
--            </eop:acquisitionParameters>
--        </eop:EarthObservationEquipment>
--    </gml:using>
--    <gml:target>
--        <eop:Footprint>
--            <gml:multiExtentOf>
--                <gml:MultiSurface srsName="urn:ogc:def:crs:EPSG:6.3:4326">
--                    <gml:surfaceMembers>
--                        <gml:Polygon srsName="urn:ogc:def:crs:EPSG:6.3:4326">
--                            <gml:exterior>
--                                <gml:LinearRing srsName="urn:ogc:def:crs:EPSG:6.3:4326">
--                                    <gml:posList>7.29 128.18 6.52 124.57 10.0 123.83 10.21 123.78 10.97 127.42 10.76 127.47 7.29 128.18</gml:posList>
--                                </gml:LinearRing>
--                            </gml:exterior>
--                        </gml:Polygon>
--                    </gml:surfaceMembers>
--                </gml:MultiSurface>
--            </gml:multiExtentOf>
--            <gml:centerOf>
--                <gml:Point srsName="urn:x-ogc:def:crs:EPSG:6.7:4326">
--                    <gml:pos>8.75 125.99</gml:pos>
--                </gml:Point>
--            </gml:centerOf>
--        </eop:Footprint>
--    </gml:target>
--    <gml:resultOf>
--        <eop:EarthObservationResult>
--            <eop:browse>
--                <eop:BrowseInformation>
--                    <eop:type>QUICKLOOK</eop:type>
--                    <eop:referenceSystemIdentifier codeSpace="EPSG">urn:ogc:def:crs:EPSG:6.3:4326</eop:referenceSystemIdentifier>
--                    <eop:fileName>http://www.disasterschartercatalog.org/test/tmp2/esa_sar/callid382/urn:ogc:def:EOP:ESA:ESA.EECF.ENVISAT_ASA_WSx_xS:ENVISAT-1-11122913513940-10119.ASA_WS_0P/PREVIEW.jpeg</eop:fileName>
--                </eop:BrowseInformation>
--                <eop:BrowseInformation>
--                    <eop:type>THUMBNAIL</eop:type>
--                    <eop:fileName>http://www.disasterschartercatalog.org/test/tmp2/esa_sar/callid382/urn:ogc:def:EOP:ESA:ESA.EECF.ENVISAT_ASA_WSx_xS:ENVISAT-1-11122913513940-10119.ASA_WS_0P/ICON.JPG</eop:fileName>
--                </eop:BrowseInformation>
--            </eop:browse>
--        </eop:EarthObservationResult>
--    </gml:resultOf>
-- </sar:EarthObservation>
--
-- ===================================================================
CREATE TABLE acquisitions (
    identifier        VARCHAR(250) PRIMARY KEY,     -- identifier
    parentidentifier  VARCHAR(250),                 -- parentIdentifier
    callid            VARCHAR(4),                   -- !! Attached disaster callid !!
    startdate         TIMESTAMP,                    -- beginPosition
    enddate           TIMESTAMP,                    -- endPosition
    platform          VARCHAR(250),                 -- Platform/shortName + Platform/identifier
    instrument        VARCHAR(250),                 -- Instrument/shortName
    metadata          TEXT,                         -- relative path from the CHARTERNG_ROOT_HTTP to the unzipped XML metadata file
    archive           TEXT,                         -- relative path from the CHARTERNG_ROOT_HTTP to the source image if available
    quicklook         VARCHAR(250),                 -- relative path from the CHARTERNG_ROOT_HTTP to the quicklook
    thumbnail         VARCHAR(250),                 -- relative path from the CHARTERNG_ROOT_HTTP to the thumbnail
    creationdate      TIMESTAMP,
    modifieddate      TIMESTAMP
);
select AddGeometryColumn(
    'acquisitions',
    'footprint',
    '4326',
    'POLYGON',
    2
);
-- Indexes
CREATE INDEX callid_acq_idx ON acquisitions USING btree (callid);
CREATE INDEX footprint_act_idx ON acquisitions USING GIST (footprint);

-- ==================================================================
--
-- Table AOIS
--
-- Created from shp2pgsql script
--
-- ==================================================================
CREATE TABLE aois (
    gid             SERIAL PRIMARY KEY,
    "call_id_1"     FLOAT8,
    "act_nb_1"      FLOAT8,
    "act_date_1"    DATE,
    "requesto_1"    VARCHAR(42),
    "pm_1"          VARCHAR(37),
    "type_1"        VARCHAR(24),
    "country_1"     VARCHAR(38),
    "esa_releva"    VARCHAR(50)
);
SELECT AddGeometryColumn(
    'aois',
    'the_geom',
    '4326',
    'MULTIPOLYGON',
    2
);


