L.Control.MinZoomIdenticator = L.Control.extend({
	options : {
		position : 'bottomleft',
	},

	/**
	 * map: layerId -> zoomlevel
	 */
	_layers : {},

	/** TODO check if nessesary
	 */
	initialize : function(options) {
		L.Util.setOptions(this, options);
		this._layers = new Object();
	},

	/**
	 * adds a layer with minzoom information to this._layers
	 */
	_addLayer : function(layer) {
		var minzoom = 15;
		if (layer.options.minzoom) {
			minzoom = layer.options.minzoom;
		}
		this._layers[layer._leaflet_id] = minzoom;
		this._updateBox(null);
	},

	/**
	 * removes a layer from this._layers
	 */
	_removeLayer : function(layer) {
		this._layers[layer._leaflet_id] = null;
		this._updateBox(null);
	},

	_getMinZoomLevel : function() {
		var minZoomlevel = -1;
		for (var key in this._layers) {
			if ((this._layers[key] != null) && (this._layers[key] > minZoomlevel)) {
				minZoomlevel = this._layers[key];
			}
		}
		return minZoomlevel;
	},

	onAdd : function(map) {
		this._map = map;
		map.zoomIndecator = this;

		var className = this.className;
		container = this._container = L.DomUtil.create('div', className);
		container.style.fontSize = "2em";
		container.style.background = "#ffffff";
		container.style.backgroundColor = "rgba(255,255,255,0.7)";
		container.style.borderRadius = "10px";
		container.style.padding = "1px 15px";
		container.style.oppacity = "0.5";
		map.on('moveend', this._updateBox, this);
		this._updateBox(null);

		//        L.DomEvent.disableClickPropagation(container);
		return container;
	},

	onRemove : function(map) {
		L.Control.prototype.onRemove.call(this, map);
		map.off({
			'moveend' : this._updateBox
		}, this);

		this._map = null;
	},

	_updateBox : function(event) {
		//console.log("map moved -> update Container...");
		if (event != null) {
			L.DomEvent.preventDefault(event);
		}
		var minzoomlevel = this._getMinZoomLevel();
		if (minzoomlevel == -1) {
			this._container.innerHTML = "no layer assigned";
		} else {
			this._container.innerHTML = "current Zoom-Level: " + this._map.getZoom() + " all data at Level: " + minzoomlevel;
		}

		if (this._map.getZoom() >= minzoomlevel) {
			this._container.style.display = 'block';
		} else {
			this._container.style.display = 'block';
		}
	},

	className : 'leaflet-control-minZoomIndecator'
});

L.LatLngBounds.prototype.toOverpassBBoxString = function() {
	var a = this._southWest, b = this._northEast;
	return [a.lat, a.lng, b.lat, b.lng].join(",");
}

L.OverPassLayer = L.FeatureGroup.extend({
	options : {
		minzoom : 15,
		query : "http://overpass-api.de/api/interpreter?data=[out:json];(node(BBOX)[organic];node(BBOX)[second_hand];);out qt;",
		callback : function(data) {
			if (this.instance._map == null) {
				console.error("_map == null");
			}
			for ( i = 0; i < data.elements.length; i++) {
				e = data.elements[i];

				if (e.id in this.instance._ids)
					return;
				this.instance._ids[e.id] = true;
				var pos = new L.LatLng(e.lat, e.lon);
				var popup = this.instance._poiInfo(e.tags, e.id);
				var circle = L.circle(pos, 50, {
					color : 'green',
					fillColor : '#3f0',
					fillOpacity : 0.5
				}).bindPopup(popup);
				this.instance.addLayer(circle);
			}
		}
	},

	initialize : function(options) {
		L.Util.setOptions(this, options);
		this._layers = {};
		// save position of the layer or any options from the constructor
		this._ids = {};
		this._requested = {};
	},

	_poiInfo : function(tags, id) {
		var link = '<a href="http://www.openstreetmap.org/edit?editor=id&node=' + id + '">Edit this entry in iD</a><br>';
		var r = $('<table>');
		for (key in tags)
		r.append($('<tr>').append($('<th>').text(key)).append($('<td>').text(tags[key])));
		return link + $('<div>').append(r).html();
	},

	/**
	 * splits the current view in uniform bboxes to allow caching
	 */
	long2tile : function(lon, zoom) {
		return (Math.floor((lon + 180) / 360 * Math.pow(2, zoom)));
	},
	lat2tile : function(lat, zoom) {
		return (Math.floor((1 - Math.log(Math.tan(lat * Math.PI / 180) + 1 / Math.cos(lat * Math.PI / 180)) / Math.PI) / 2 * Math.pow(2, zoom)));
	},
	tile2long : function(x, z) {
		return (x / Math.pow(2, z) * 360 - 180);
	},
	tile2lat : function(y, z) {
		var n = Math.PI - 2 * Math.PI * y / Math.pow(2, z);
		return (180 / Math.PI * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n))));
	},
	_view2BBoxes : function(l, b, r, t) {
		//console.log(l+"\t"+b+"\t"+r+"\t"+t);
		//this.addBBox(l,b,r,t);
		//console.log("calc bboxes");
		var requestZoomLevel = 14;
		//get left tile index
		var lidx = this.long2tile(l, requestZoomLevel);
		var ridx = this.long2tile(r, requestZoomLevel);
		var tidx = this.lat2tile(t, requestZoomLevel);
		var bidx = this.lat2tile(b, requestZoomLevel);

		//var result;
		var result = new Array();
		for (var x = lidx; x <= ridx; x++) {
			for (var y = tidx; y <= bidx; y++) {//in tiles tidx<=bidx
				var left = Math.round(this.tile2long(x, requestZoomLevel) * 1000000) / 1000000;
				var right = Math.round(this.tile2long(x + 1, requestZoomLevel) * 1000000) / 1000000;
				var top = Math.round(this.tile2lat(y, requestZoomLevel) * 1000000) / 1000000;
				var bottom = Math.round(this.tile2lat(y + 1, requestZoomLevel) * 1000000) / 1000000;
				//console.log(left+"\t"+bottom+"\t"+right+"\t"+top);
				//this.addBBox(left,bottom,right,top);
				//console.log("http://osm.org?bbox="+left+","+bottom+","+right+","+top);
				result.push(new L.LatLngBounds(new L.LatLng(bottom, left), new L.LatLng(top, right)));
			}
		}
		//console.log(result);
		return result;
	},

	addBBox : function(l, b, r, t) {
		var polygon = L.polygon([[t, l], [b, l], [b, r], [t, r]]).addTo(this._map);
	},

	onMoveEnd : function() {
		//console.log(this._map.getBounds());
		if (this._map.getZoom() >= this.options.minzoom) {
			//var bboxList = new Array(this._map.getBounds());
			var bboxList = this._view2BBoxes(this._map.getBounds()._southWest.lng, this._map.getBounds()._southWest.lat, this._map.getBounds()._northEast.lng, this._map.getBounds()._northEast.lat);

			console.log("load Pois tiles to load: " + bboxList.length);
			for (var i = 0; i < bboxList.length; i++) {
				var bbox = bboxList[i];
				var x = bbox._southWest.lng;
				var y = bbox._northEast.lat;
				/* if ((x in this._requested) && (y in this._requested[x]) && (this._requested[x][y] == true)) {
				continue;
				}
				if (!(x in this._requested)) {
				this._requested[x] = {};
				}
				this._requested[x][y] = true;*/
				//this.addBBox(x,bbox._southWest.lat,bbox._northEast.lng,y);
				var serverApis = ['http://overpass.osm.rambler.ru/cgi/', 'http://overpass-api.de/api/'];
				var index =(i%2) ? 0 : 1;
				//var serverApi = serverApis[1];
				var serverApi = serverApis[Math.round(Math.random())];
				var apiUrl = this.options.query.replace('\(SERVERAPI\)', serverApi).replace(/(BBOX)/g, bbox.toOverpassBBoxString());
				
				$.ajax({
					url : apiUrl,
					context : {
						instance : this
					},
					crossDomain : true,
					dataType : "json",
					data : {},
					success : this.options.callback
				});
			}
		}
	},
	onAdd : function(map) {
		this._map = map;
		if (map.zoomIndecator) {
			this._zoomControl = map.zoomIndecator;
			this._zoomControl._addLayer(this);
		} else {
			this._zoomControl = new L.Control.MinZoomIdenticator();
			map.addControl(this._zoomControl);
			this._zoomControl._addLayer(this);
		}

		this.onMoveEnd();
		if (this.options.query.indexOf("(BBOX)") != -1) {
			map.on('moveend', this.onMoveEnd, this);

		}
	},

	onRemove : function(map) {
		L.LayerGroup.prototype.onRemove.call(this, map);
		this._ids = {};
		this._requested = {};
		this._zoomControl._removeLayer(this);

		map.off({
			'moveend' : this.onMoveEnd
		}, this);

		this._map = null;
	},

	getData : function() {
		console.log(this._data);
		return this._data;
	}
});

//FIXME no idea why the browser crashes with this code
//L.OverPassLayer = function (options) {
//  return new L.OverPassLayer(options);
//};
