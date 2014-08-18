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
Array.prototype.unique = function() {
	var unique = [];
	for (var i = 0; i < this.length; i++) {
		if (unique.indexOf(this[i]) == -1) {
			unique.push(this[i]);
		}
	}
	return unique;
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

		/*var clouds = L.tileLayer('http://{s}.tile.openweathermap.org/map/clouds/{z}/{x}/{y}.png', {
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
		 })*/

		map = L.map('map', {
			center : new L.LatLng(39.73, -104.99),
			zoom : 10,
			layers : [mapnik]
		});

		var baseMaps = {
			"MapBox" : mapbox,
			"Mapnik" : mapnik,
			"Black and White" : blackAndWhite,
			"Airial" : airial
		};

		/*var overlayMaps = {
		"-None-" : none,
		"Clouds" : clouds,
		"Wind" : wind,
		"Temperature" : temperature,
		"Sea Markers" : seamarks
		};*/

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

		map.on('mousedown', function(e) {
			console.log('mousedown');
		});
		map.on('mouseup', function(e) {
			console.log('mouseup');
		});
		$(document).on('click', '.enable-layer', function() {
			var layer = $(this).attr('data-layer');
			var active = ($(this).attr('data-layer-enabled')) ? true : false;
			var visibleIcon = '<i class="icon-toggle fright micon"></i>';

			if (active) {
				$(this).removeAttr('data-layer-enabled');
				$(this).find('i').remove();
			} else {
				$(this).attr('data-layer-enabled', 'true');
				$(this).append(visibleIcon);
			}

			switch(layer) {
			case "contacts":
				if (active) {
					toolKit.removeFavMarkers();

				} else {
					Maps.loadAdressBooks();
				}
				break;

			case "tourism":
				if (active) {
					Maps.removeLayer('tourism-all');
					$('#tourism-items').html('')
				} else {
					Maps.addLayer('tourism', 'tourism-all');
				}
				break;

			case "amenity":
				if (active) {
					Maps.removeLayer('amenity-all');
					$('#amenity-items').html('')
				} else {
					Maps.addLayer('amenity', 'amenity-all');
				}
				break;

			case "shop":
				if (active) {
					Maps.removeLayer('shop-all');
					$('#shop-items').html('')
				} else {
					Maps.addLayer('shop', 'shop-all');
				}
				break;
			}
		})

		$(document).on('click', '.subLayer', function() {
			var subLayer = $(this).attr('data-subLayer');
			var isVisible = $(this).find('i').length;

			if (isVisible == 1) {
				Maps.hiddenPOITypes.push(subLayer)
				$('.' + subLayer).addClass('hidden');
				$(this).find('i').remove();
			} else {
				delete Maps.hiddenPOITypes[subLayer];
				$('.' + subLayer).removeClass('hidden');
				Maps.hiddenPOITypes.splice(Maps.hiddenPOITypes.indexOf(subLayer), 1);
				$(this).append('<i class="icon-toggle fright micon"></i>');
			}
		})
	})
	// End document ready
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

	Maps = {
		hiddenPOITypes : [],
		addressbooks : [],
		tempArr : [],
		tempCounter : 0,
		tempTotal : 0,
		activeLayers : [],
		loadAdressBooks : function() {
			Maps.addressbooks = [];
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
			Maps.tempArr = [];
			Maps.tempTotal = 0;
			Maps.tempCounter = 0;
			$.each(Maps.addressbooks, function(ai) {
				$.get(OC.generateUrl('/apps/contacts/addressbook/' + this.backend + '/' + this.id + '/contacts'), function(r) {
					$.each(r.contacts, function(ci) {
						Maps.tempArr.push(this);
						if (ai == Maps.addressbooks.length - 1 && ci == r.contacts.length - 1) {
							Maps.getContactPositionData();
							Maps.tempTotal = Maps.tempArr.length;
							$('#loadingContacts').show();
						}
					});
				});
			});
		},
		getContactPositionData : function() {

			if (Maps.tempArr.length > 0) {
				temp = Maps.tempArr.pop();
				var contact = toolKit.vcardToObject(temp);
				toolKit.adresLookup(contact.adr, function(d) {
					var curperson = $.extend({}, d, contact);
					try {
						toolKit.addFavContactMarker(curperson);
					} catch(e) {

					}
					var total = Maps.tempTotal;
					var index = Maps.tempCounter
					var percent = Math.round((index / total * 100) * 100) / 100;
					toolKit.setProgress(percent);
					$('#cCounter').text(index + 1 + ' of ' + (total * 1 + 1));
					Maps.tempCounter++;
					if (index == total)
						$('#loadingContacts').hide()
					Maps.getContactPositionData();
				})
			}
		},
		showContact : function(data) {

		},

		addLayer : function(group, layer) {
			//OverPassAPI overlay
			//k:amenity  v:postbox
			var tmpTypes = []
			Maps.activeLayers[layer] = new L.OverPassLayer({
				minzoom : 14,
				query : OC.generateUrl('apps/maps/router?url=http://overpass-api.de/api/interpreter?data=[out:json];node(BBOX)[' + group + '];out;'),
				callback : function(data) {
					for ( i = 0; i < data.elements.length; i++) {
						e = data.elements[i];
						// if (e.id in this.instance._ids) return;
						this.instance._ids[e.id] = true;
						var pos = new L.LatLng(e.lat, e.lon);
						var popup = this.instance._poiInfo(e.tags, e.id);
						var color = e.tags.collection_times ? 'green' : 'red';
						var icon = e.tags[group].split(';');
						if(icon[0].indexOf(',')!=-1){
							icon = icon[0].split(',');
						}
						var isVisible = ($.inArray(group + '-' + icon[0], Maps.hiddenPOITypes) == -1) ? '' : 'hidden';
						console.log(group + '-' + icon[0]);
						if (icon[0] != 'yes') {
							var iconImage = L.icon({
								iconUrl : OC.filePath('maps', 'img', 'mapIcons/' + icon[0].toLowerCase() + '.png'),
								iconSize : [42, 49],
								iconAnchor : [21, 49],
								popupAnchor : [0, -49],
								className : group + '-' + icon[0] + ' ' + isVisible
							});

							var marker = L.marker(pos, {
								icon : iconImage
							}).bindPopup(popup);
							this.instance.addLayer(marker);
							tmpTypes.push(icon[0]);
						}
					}
					tmpTypes = tmpTypes.unique().clean('');
					tmpHTML = ''
					$.each(tmpTypes, function() {
						isVisible = $.inArray(group + '-' + this, Maps.hiddenPOITypes);
						vIcon = (isVisible == -1) ? '<i class="icon-toggle fright micon"></i>' : ''
						tmpHTML += '<li><a class="subLayer" data-subLayer="' + group + '-' + this + '">' + this + vIcon + '</a><li>'
					})

					$('#' + group + '-items').html(tmpHTML)
					$('#' + group + '-items').show();
				},
			});
			map.addLayer(Maps.activeLayers[layer]);
		},
		removeLayer : function(layer) {
			map.removeLayer(Maps.activeLayers[layer])
		}
	}

	toolKit = {
		vcardToObject : function(vcard) {
			var contact = {};

			$.each(vcard.data, function(k, v) {
				if (v[0]) {
					if ($.isArray(v[0]['value'])) {
						if (k === 'ADR') {
							var adr = {
								street : v[0]['value'][0] + v[0]['value'][1] + v[0]['value'][2],
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
				} else {
					console.log(k, v)
				}
			})
			contact.thumbnail = vcard.data.thumbnail
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
		favMarkers : [],
		addFavContactMarker : function(contact) {
			if (contact.thumbnail) {
				var imagePath = 'data:image/png;base64,' + contact.thumbnail
				var iconImage = L.icon({
					iconUrl : imagePath,
					iconSize : [42, 49],
					iconAnchor : [21, 49],
					popupAnchor : [0, -49],
					className : 'contact-icon'
				});
			} else {
				var imagePath = OC.filePath('maps', 'img', 'icons/marker_anonPerson.png');
				var iconImage = L.icon({
					iconUrl : imagePath,
					iconSize : [42, 49],
					iconAnchor : [21, 49],
					popupAnchor : [0, -49]
				});
			}

			var markerHTML = '<b>' + contact.fn + "</b>";
			markerHTML += '<br />' + contact.adr.street + " " + contact.adr.city;
			markerHTML += (contact.tel) ? '<br />Tel: ' + escape(contact.tel) : '';
			var marker = L.marker([contact.lat * 1, contact.lon * 1], {
				icon : iconImage
			}).addTo(map).bindPopup(markerHTML);
			toolKit.favMarkers.push(marker);
		},
		removeFavMarkers : function() {
			for ( i = 0; i < toolKit.favMarkers.length; i++) {
				map.removeLayer(toolKit.favMarkers[i]);
			}
			toolKit.favMarkers = []
		},

		setProgress : function(percent) {
			var $element = $('#progressBar');
			var progressBarWidth = percent * $element.width() / 100;
			$element.find('div').animate({
				width : progressBarWidth
			}, 50).html(percent + "%&nbsp;");
		}
	}

})(jQuery, OC);
