(function(c) {
    c.add("layers", {
        type: "WMS",
        title: "AOIs",
        url: "http://engine.mapshup.info/cgi-bin/mapserv?map=/home/projects/charterng/mapserver/aois.map&",
        layers: "aois",
        unremovable: true,
        queryable: true,
        time:{
            default:"2000-01-01/2015-01-01"
        },
        srs: "EPSG:3857",
        ol:{
            singleTile:true
        }
    });
})(window.M.Config);
