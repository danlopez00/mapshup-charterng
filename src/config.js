(function(c) {

    /*
     * Update configuration options
     */
    c["general"].rootUrl = 'http://disasterschartercatalog.org/cecec';
    c["general"].serverRootUrl = c["general"].rootUrl + '/s';
    c["general"].indexPath = "/index.html";
    c["general"].timeLine = {
        enable: true, //true to enable timeLine, false otherwise
        disablable: false,
        position: {
            'bottom': 37
        },
        absolutes: {
            min: 2000,
            max: (new Date()).getFullYear() + 1
        },
        bounds: {
            min: new Date(2000, 0, 1),
            max: new Date()
        },
        values: {
            min: new Date(2000, 9, 1),
            max: new Date()
        }
    };
    /*
     * Remove plugins
     */
    c.remove("plugins", "Search");
    c.add("plugins", {
        name: "Search",
        options: {
            hint: "Search for keywords or enter a Call ID"
        }
    });

    c.add("plugins", {
        name: "CharterNG",
        options: {
            service:c["general"].serverRootUrl + "/plugins/charterng/opensearch_act.xml?"
        }
    });

    c.add("layers", {
        type: 'Catalog',
        title: 'Charter Acquisitions',
        url: c["general"].serverRootUrl + '/plugins/charterng/opensearch_acq.xml',
        connectorName: 'OpenSearch',
        inactive:true,
        MID:'midcgt123456',
        unremovable: true,
        color: '#FF007F',
	onSearch:{
            zoom:true
        },
        microInfoTemplate:{
	    enable:true,
            body:'<h2 class="shorten_50">$identifier$</h2><br/><img class="center square" src="$thumbnail$"/>'
        },
        sort: {
            attribute: 'beginPosition',
            order: 'd',
            type: 'd'
        },
        featureInfo: {
            keys: {
                'identifier': {
                    transform: function(v) {
                        return v.replace(/urn:ogc:def:EOP:/, "");
                    }
                },
                'callid': {
                    transform: function(v) {
                        if (v) {
                            if (v.length === 1) {
                                v = "00" + v;
                            }
                            else if (v.length === 2) {
                                v = "0" + v;
                            }
                            return '<a href="http://www.disasterscharter.org/web/charter/activation_details?p_r_p_1415474252_assetId=ACT-' + v + '" target="_blank">' + v + '</a>';

                        }
                        return "";
                    }

                }
            }
        }
    });

    c.add("layers", {
        type: 'Catalog',
        title: 'Activations',
        url: c["general"].serverRootUrl + '/plugins/charterng/opensearch_act.xml',
        connectorName: 'OpenSearch',
        clusterized: true,
        unremovable: true,
	featureType:"Point",
        searchOnLoad: function(sc, layer){
            if (layer) {
                var features = M.Map.Util.getFeatures(layer);
                M.Map.zoomTo(features[0].geometry.getBounds());
                M.Map.featureInfo.select(features[0]);
            }
        },
        onSearch:{
            zoom:true,
            callback:function(layer) {
                M.Map.featureInfo.clear();
                var c = M.Map.Util.getLayerByMID("midcgt123456");
		if (c) {
                	c.destroyFeatures();
                	M.Map.events.trigger("layersend", {
                    		action:"features",
                    		layer:c
                	});
		}
            }
        },
        opacity: 0.9,
        microInfoTemplate:{
            enable:true,
            body:'<h2>$title$</h2><p>$disasterdate$</p><p>Call ID - <a target="_blank" href="http://www.disasterscharter.org/web/charter/activation_details?p_r_p_1415474252_assetId=ACT-$callid$">$callid$</a></p><br/><p class="shorten_350">$description$</p>'
        },
	featureInfo: {
            title: "CALL ID $callid$ : $title$",
	    onSelect:function(f){
	        var c = M.Map.Util.getLayerByMID("midcgt123456");
                if (c) {
                        c.destroyFeatures();
                        M.Map.events.trigger("layersend", {
                                action:"features",
                                layer:c
                        });
                } 
	    },
            keys: {
                "type": {
                    v: "Type"
                },
                'callid': {
                    v: "CALL ID",
                    transform: function(v) {
                        if (v) {
                            if (v.length === 1) {
                                v = "00" + v;
                            }
                            else if (v.length === 2) {
                                v = "0" + v;
                            }
                            return '<a href="http://www.disasterscharter.org/web/charter/activation_details?p_r_p_1415474252_assetId=ACT-' + v + '" target="_blank">' + v + '</a>';

                        }
                        return "";
                    }

                }
            },
            action: {
                tt: "Display acquisitions",
                title: "Search",
                icon: "layers.png",
                callback: function(a, f) {
                    if (f && f.geometry) {
                        // mid822859269 is the unique mapshup ID of localhost OpenSearch Charter catalog
                        var c = M.Map.Util.getLayerByMID("midcgt123456");
                        if (c) {
                            var sc = c["_M"].searchContext,
                                    auto = sc.autoSearch;
                            sc.autoSearch = false;
                            sc.setGeo(false);
                            sc.clear();
                            sc.setFilter("callid", f.attributes["callid"]);
                            sc.search();
                            sc.setGeo(true);
                            sc.autoSearch = auto;
                        }
                    }
                }
            }
        },
        ol: {
            displayInLayerSwitcher: false
        }
    });
})(window.M.Config);
