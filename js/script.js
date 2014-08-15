/**
 * ownCloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Sander Brand <brantje@gmail.com>
 * @copyright Sander Brand 2014
 */
/** Testing
 */

Array.prototype.clean = function(deleteValue) {
	for (var i = 0; i < this.length; i++) {
		if (this[i] == deleteValue) {
			this.splice(i, 1);
			i--;
		}
	}
	return this;
};

(function($, OC) {

	// initialize map when page ready
	$(document).ready(function() {
		marker = null;
		circle = null;
		firstRun = true;

		/*var map = L.map('map').setView([51.505, -0.09], 13);
		 L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		 attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery © <a href="http://mapbox.com">Mapbox</a>',
		 maxZoom: 18
		 }).addTo(map);

		 */
		var attribution = '&copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>';

		var mapnik = L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution : attribution
		})
		var mapbox = L.tileLayer('https://a.tiles.mapbox.com/v3/liedman.h9ekn0f1/{z}/{x}/{y}.png', {
			attribution : attribution + ' Tiles <a href="https://www.mapbox.com/about/maps/">MapBox</a>'
		})
		var blackAndWhite = L.tileLayer('http://{s}.www.toolserver.org/tiles/bw-mapnik/{z}/{x}/{y}.png', {
			attribution : attribution
		})

		var airial = L.tileLayer('http://server.arcgisonline.com/ArcGIS/' + 'rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
			attribution : attribution + ' Tiles © Esri',
			subdomains : "1234"
		})

		var clouds = L.tileLayer('http://{s}.tile.openweathermap.org/map/clouds/{z}/{x}/{y}.png', {
			attribution : 'Map data &copy; <a href="http://openweathermap.org">OpenWeatherMap</a>',
			opacity : 0.5
		})
		var wind = L.tileLayer('http://{s}.tile.openweathermap.org/map/wind/{z}/{x}/{y}.png', {
			attribution : 'Map data &copy; <a href="http://openweathermap.org">OpenWeatherMap</a>',
			opacity : 0.5
		})
		var temperature = L.tileLayer('http://{s}.tile.openweathermap.org/map/temp/{z}/{x}/{y}.png', {
			attribution : 'Map data &copy; <a href="http://openweathermap.org">OpenWeatherMap</a>',
			opacity : 0.5
		})

		var seamarks = L.tileLayer('http://tiles.openseamap.org/seamark/{z}/{x}/{y}.png', {
			attribution : 'Map data &copy; <a href="http://openweathermap.org">OpenSeaMap</a>',
			opacity : 1.0
		})

		var none = L.tileLayer('http://{s}.tile.openweathermap.org/map/temp/{z}/{x}/{y}.png', {
			attribution : '',
			opacity : 0.0
		})

		map = L.map('map', {
			center : new L.LatLng(39.73, -104.99),
			zoom : 10,
			layers : [mapnik, none]
		});

		var baseMaps = {
			"MapBox" : mapbox,
			"Mapnik" : mapnik,
			"Black and White" : blackAndWhite,
			"Airial" : airial
		};

		var overlayMaps = {
			"-None-" : none,
			"Clouds" : clouds,
			"Wind" : wind,
			"Temperature" : temperature,
			"Sea Markers" : seamarks
		};

		//var control = L.control.layers(baseMaps, overlayMaps)
		//var control = L.control.selectLayers(baseMaps, overlayMaps)
		//control.addTo(map);
		map.locate({
			setView : false,
			maxZoom : 16,
			watch : true,
			enableHighAccuracy : true
		});
		map.on('locationfound', onLocationFound);
		//map.addControl( new L.Control.Compass() );
		$('.leaflet-control-layers-overlays').removeProp('multiple');

		L.Routing.control({
			waypoints : [],
			geocoder : L.Control.Geocoder.nominatim(),
			plan : L.Routing.plan(null, {
				waypointIcon : function(i) {
					return new L.Icon.Label.Default({
						labelText : String.fromCharCode(65 + i)
					});
				}
			})
		}).addTo(map);
		$(".leaflet-routing-geocoders").appendTo("#searchContainer");
		$(".leaflet-routing-container").appendTo("#searchContainer");
	})
	function onLocationFound(e) {
		var radius = e.accuracy / 2;
		if (marker != null) {
			map.removeLayer(marker);
		}
		if (circle != null) {
			map.removeLayer(circle);
		}

		marker = L.marker(e.latlng).addTo(map);
		//.bindPopup("You are within " + radius + " meters from this point").openPopup();
		if (radius < 5000) {
			circle = L.circle(e.latlng, radius).addTo(map);
		}

		if (firstRun) {
			map.panTo(e.latlng);
			var maxZoom = 16;
			map.setZoom(14);
			firstRun = false;
		}
	}

	var Maps = {
		addressbooks : [],
		contacts : [],
		tempArr : [],
		loadAdressBooks : function() {
			$.get(OC.generateUrl('apps/contacts/addressbooks/'), function(r) {
				$.each(r.addressbooks, function() {
					var book = {
						'id' : this.id,
						'backend' : this.backend
					}
					Maps.addressbooks.push(book);
				})
				Maps.loadContacts();
			})
		},
		loadContacts : function() {
			$.each(Maps.addressbooks, function(ai) {
				$.get(OC.generateUrl('/apps/contacts/addressbook/' + this.backend + '/' + this.id + '/contacts'), function(r) {
					$.each(r.contacts, function(ci) {
						Maps.tempArr.push(this);
						if (ai == Maps.addressbooks.length - 1 && ci == r.contacts.length - 1) {
							Maps.getContactPositionData();
						}
					});
				});
			});
		},
		getContactPositionData : function() {
			$.each(Maps.tempArr, function() {
				var contact = toolKit.vcardToObject(this);
				toolKit.adresLookup(contact.adr, function(d) {
					var curperson = $.extend({}, d, contact);
					toolKit.addFavContactMarker(curperson);

				})
			})
		},
		showContact : function(data) {

		}
	}
	Maps.loadAdressBooks();

	var toolKit = {
		vcardToObject : function(vcard) {
			var contact = {};
			
			$.each(vcard.data, function(k, v) {
				if ($.isArray(v[0]['value'])) {
					if (k === 'ADR') {
						var adr = {
							street : v[0]['value'][0],
							city : v[0]['value'][3],
							postalCode : v[0]['value'][5],
							country : v[0]['value'][6]
						}
						contact[k.toLowerCase()] = adr;
					} else {
						contact[k.toLowerCase()] = v[0]['value'].clean('').join(',');
					}
				} else {
					contact[k.toLowerCase()] = v[0]['value'];
				}

			})
			return contact;
		},
		/**
		 *
		 * @param {Object} street+houseno,City,Country
		 * @param {Function} callback func
		 */
		adresLookup : function(address, callback) {
			var getData = {
				street : address.street,
				city : address.city,
				country : address.country
			}
			$.getJSON(OC.generateUrl('/apps/maps/adresslookup'), getData, function(r) {
				callback(r)
			})
		},
		favMarkers: [],
		addFavContactMarker : function(contact) {
			
			console.log(contact)
			
			/*
			var favData = {
				"type" : "Feature",
				"properties" : {
					"popupContent" : "Coors Field"
				},
				"geometry" : {
					"type" : "Point",
					"coordinates" : [,]
				}
			};
			var coorsLayer = L.geoJson(favData, {
				pointToLayer : function(feature, latlng) {
					return L.marker(latlng, {
						icon : favIcon
					});
				},
				onEachFeature : function(feature, layer) {
					var popupContent = contact.adr.street +', '+contact.adr.city;
					if (feature.properties && feature.properties.popupContent) {
						
					}

					layer.bindPopup(popupContent);
				}
			}).addTo(map);*/
			var favIcon = L.icon({
				iconUrl : OC.filePath('maps', 'img', 'icons/favMarker.png'),
				iconSize : [32, 37],
				iconAnchor : [16, 37],
				popupAnchor : [0, -28]
			});
			var markerHTML = '<b>'+ contact.fn +"</b>";
				markerHTML += contact.adr.street + " "+ contact.adr.city;
				markerHTML += '<br />Tel: '+ escape(contact.tel)
			var marker = L.marker([contact.lat*1,contact.lon*1],{icon: favIcon}).addTo(map).bindPopup(markerHTML);   
			toolKit.favMarkers.push(marker);
		}
	}

})(jQuery, OC);
